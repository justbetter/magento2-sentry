<?php

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
     * @var Data
     */
    protected $data;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var array
     */
    protected $config = [];

    /** @var State */
    private $appState;

    /**
     * SentryLog constructor.
     *
     * @param string          $name
     * @param array           $handlers
     * @param array           $processors
     * @param Data|Data\Proxy $data
     * @param Session         $customerSession
     */
    public function __construct(
        $name,
        Data $data,
        Session $customerSession,
        State $appState,
        array $handlers = [],
        array $processors = []
    ) {
        $this->data = $data;
        $this->customerSession = $customerSession;
        $this->appState = $appState;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * @param $message
     * @param $logLevel
     * @param Monolog $monolog
     * @param array   $context
     */
    public function send($message, $logLevel, Monolog $monolog, $context = [])
    {
        $config = $this->data->collectModuleConfig();

        if ($logLevel < (int) $config['log_level']) {
            return;
        }

        \Sentry\configureScope(function (SentryScope $scope): void {
            $this->setTags($scope);
            $this->setUser($scope);
        });

        if ($message instanceof \Throwable) {
            $lastEventId = \Sentry\captureException($message);
        } else {
            $lastEventId = \Sentry\captureMessage($message, \Sentry\Severity::fromError($logLevel));
        }

        /// when using JS SDK you can use this for custom error page printing
        try {
            $this->customerSession->setSentryEventId($lastEventId);
        } catch (SessionException $e) {
            return;
        }
    }

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

    private function canGetCustomerData()
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (LocalizedException $ex) {
            return false;
        }
    }

    private function setTags(SentryScope $scope): void
    {
        $store = $this->data->getStore();
        $scope->setTag('mage_mode', $this->data->getAppState());
        $scope->setTag('version', $this->data->getMagentoVersion());
        $scope->setTag('website_id', $store ? $store->getWebsiteId() : null);
        $scope->setTag('store_id', $store ? $store->getStoreId() : null);
        $scope->setTag('store_code', $store ? $store->getCode() : null);
    }
}
