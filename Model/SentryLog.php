<?php

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Data;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\Logger\Monolog;
use Sentry\EventHint;
use Sentry\ExceptionMechanism;
use Sentry\Stacktrace;
use Sentry\State\Scope as SentryScope;

class SentryLog
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * SentryLog constructor.
     *
     * @param Data              $data
     * @param Session           $customerSession
     * @param State             $appState
     * @param SentryInteraction $sentryInteraction
     */
    public function __construct(
        protected Data $data,
        protected Session $customerSession,
        private State $appState,
        private SentryInteraction $sentryInteraction,
    ) {
    }

    /**
     * Check and send log information to Sentry.
     *
     * @param \Throwable|string $message
     * @param int               $logLevel
     * @param array             $context
     */
    public function send($message, $logLevel, $context = []): void
    {
        $config = $this->data->collectModuleConfig();
        $customTags = [];

        if ($config['enable_logs'] && $logLevel >= $config['logger_log_level']) {
            match ($logLevel) {
                Monolog::DEBUG   => \Sentry\Logger()->debug($message, $context),
                Monolog::INFO    => \Sentry\Logger()->info($message, $context),
                Monolog::WARNING => \Sentry\Logger()->warn($message, $context),
                Monolog::ERROR, MONOLOG::CRITICAL => \Sentry\Logger()->error($message, $context),
                Monolog::ALERT, MONOLOG::EMERGENCY => \Sentry\Logger()->fatal($message, $context),
                default => \Sentry\Logger()->info($message, $context)
            };
        }

        if ($logLevel < (int) $config['log_level']) {
            return;
        }

        if (true === isset($context['custom_tags']) && false === empty($context['custom_tags'])) {
            $customTags = $context['custom_tags'];
            unset($context['custom_tags']);
        }

        \Sentry\configureScope(
            function (SentryScope $scope) use ($context, $customTags): void {
                $this->setTags($scope, $customTags);
                if (false === empty($context)) {
                    $scope->setContext('Custom context', $context);
                }
            }
        );

        $this->sentryInteraction->addUserContext();

        if ($message instanceof \Throwable) {
            $lastEventId = \Sentry\captureException($message);
        } else {
            $lastEventId = \Sentry\captureMessage(
                $message,
                \Sentry\Severity::fromError($logLevel),
                $this->monologContextToSentryHint($context)
            );
        }

        /// when using JS SDK you can use this for custom error page printing
        try {
            if (true === $this->canGetCustomerData()) {
                $this->customerSession->setSentryEventId($lastEventId);
            }
        } catch (SessionException $e) {
            return;
        }
    }

    /**
     * Turn the monolog context into a format Sentrys EventHint can deal with.
     *
     * @param array $context
     *
     * @return EventHint|null
     */
    public function monologContextToSentryHint(array $context): ?EventHint
    {
        return EventHint::fromArray(
            [
                'exception'  => ($context['exception'] ?? null) instanceof \Throwable ? $context['exception'] : null,
                'mechanism'  => ($context['mechanism'] ?? null) instanceof ExceptionMechanism ? $context['mechanism'] : null,
                'stacktrace' => ($context['stacktrace'] ?? null) instanceof Stacktrace ? $context['stacktrace'] : null,
                'extra'      => array_filter(
                    $context,
                    fn ($key) => !in_array($key, ['exception', 'mechanism', 'stacktrace']),
                    ARRAY_FILTER_USE_KEY
                ) ?: [],
            ]
        );
    }

    /**
     * Check if we can retrieve customer data.
     *
     * @return bool
     */
    private function canGetCustomerData()
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (LocalizedException $ex) {
            return false;
        }
    }

    /**
     * Add additional tags to the scope.
     *
     * @param SentryScope $scope
     * @param array       $customTags
     */
    private function setTags(SentryScope $scope, $customTags): void
    {
        $store = $this->data->getStore();

        $scope->setTag('mage_mode', $this->data->getAppState());
        $scope->setTag('version', $this->data->getMagentoVersion());
        $scope->setTag('website_id', (string) $store?->getWebsiteId());
        $scope->setTag('store_id', (string) $store?->getId());
        $scope->setTag('store_code', (string) $store?->getCode());

        if (false === empty($customTags)) {
            foreach ($customTags as $tag => $value) {
                $scope->setTag($tag, $value);
            }
        }
    }

    /**
     * Send the logs to Sentry if there are any.
     */
    public function __destruct()
    {
        \Sentry\Logger()->flush();
    }
}
