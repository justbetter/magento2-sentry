<?php

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryInteraction;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\Logger\Monolog;
use Sentry\State\Scope as SentryScope;

class SentryLog extends Monolog
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * SentryLog constructor.
     *
     * @param string            $name
     * @param Data              $data
     * @param Session           $customerSession
     * @param State             $appState
     * @param SentryInteraction $sentryInteraction
     * @param array             $handlers
     * @param array             $processors
     */
    public function __construct(
        $name,
        protected Data $data,
        protected Session $customerSession,
        private State $appState,
        private SentryInteraction $sentryInteraction,
        array $handlers = [],
        array $processors = []
    ) {
        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Check and send log information to Sentry.
     *
     * @param \Throwable|string $message
     * @param int               $logLevel
     * @param array             $context
     */
    public function send($message, $logLevel, $context = [])
    {
        $config = $this->data->collectModuleConfig();
        $customTags = [];

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
            $lastEventId = \Sentry\captureMessage($message, \Sentry\Severity::fromError($logLevel));
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
        $scope->setTag('website_id', $store ? $store->getWebsiteId() : null);
        $scope->setTag('store_id', $store ? $store->getStoreId() : null);
        $scope->setTag('store_code', $store ? $store->getCode() : null);

        if (false === empty($customTags)) {
            foreach ($customTags as $tag => $value) {
                $scope->setTag($tag, $value);
            }
        }
    }
}
