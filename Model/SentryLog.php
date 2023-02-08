<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Data;
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
     * SentryLog constructor.
     *
     * @param string $name
     * @param Data $data
     * @param Session $customerSession
     * @param State $appState
     * @param array $handlers
     * @param array $processors
     * @param array $config
     */
    public function __construct(
        public string          $name,
        protected Data         $data,
        protected Session      $customerSession,
        private readonly State $appState,
        protected array        $handlers = [],
        protected array        $processors = [],
        protected array        $config = []
    ) {
        parent::__construct($name, $handlers, $processors);
    }

    /**
     * @param $message
     * @param int $logLevel
     * @param array $context
     */
    public function send($message, int $logLevel, array $context = [])
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
                $this->setUser($scope);
                if (false === empty($context)) {
                    $scope->setContext('Custom context', $context);
                }
            }
        );

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
     * @param SentryScope $scope
     * @return void
     */
    private function setUser(SentryScope $scope): void
    {
        try {
            if (!$this->canGetCustomerData()
                || !$this->customerSession->isLoggedIn()) {
                return;
            }

            $customerData = $this->customerSession->getCustomer();
            $scope->setUser([
                'id'         => $customerData->getEntityId(),
                'email'      => $customerData->getEmail(),
                'website_id' => $customerData->getWebsiteId(),
                'store_id'   => $customerData->getStoreId(),
            ]);
        } catch (SessionException $e) {
            return;
        }
    }

    /**
     * @return bool
     */
    private function canGetCustomerData(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (LocalizedException $ex) {
            return false;
        }
    }

    /**
     * @param SentryScope $scope
     * @param array       $customTags
     */
    private function setTags(SentryScope $scope, array $customTags): void
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
