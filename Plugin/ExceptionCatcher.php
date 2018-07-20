<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Model\SentryLog;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Http;
use Magento\Framework\App\Bootstrap;
use JustBetter\Sentry\Helper\Data;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RavenHandler;
use Monolog\Logger;
use Raven_Client;

class ExceptionCatcher
{
    protected $configKeys = [
        'domain',
        'enabled',
        'log_level',
    ];

    /**
     * @var Data
     */
    protected $data;

    /**
     * @var
     */
    protected $config;

    /**
     * @var Session
     */
    protected $catalogSession;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * ExceptionCatcher constructor
     *
     * @param Data      $data
     * @param Session   $catalogSession
     * @param State     $state
     * @param SentryLog $logger
     */
    public function __construct(
        Data $data,
        Session $catalogSession,
        State $state,
        SentryLog $logger
    )
    {
        $this->data = $data;
        $state->setAreaCode(Area::AREA_GLOBAL);
        $this->state = $state;
        $this->logger = $logger;
        $this->customerSession = $catalogSession;
    }

    /**
     * Catch any exceptions and notify an instance of \Sentry\Client
     *
     * @param Http       $subject
     * @param Bootstrap  $bootstrap
     * @param \Exception $exception
     * @return array
     */
    public function beforeCatchException(
        Http $subject,
        Bootstrap $bootstrap,
        \Exception $exception
    )
    {
        $this->config = $this->data->collectModuleConfig();

        if ($this->data->isActive() && ($this->data->isProductionMode() || $this->data->isOverwriteProductionMode())) {
            $client = (new Raven_Client(
                $this->config['domain'] ?? null
            ));

            $client->tags_context([
                'mage_mode' => $this->data->getAppState()
            ]);

            $handler = new RavenHandler(
                $client,
                $this->config['log_level'] ?? Logger::ERROR
            );

            $handler->setFormatter(
                new LineFormatter("%message% %context% %extra%\n")
            );

            $this->logger->pushHandler($handler);
            $this->captureUserData();
            $this->customerSession->setSentryEventId($client->captureException($exception));
        }

        return [$bootstrap, $exception];
    }

    protected function captureUserData()
    {
        if ($this->customerSession && ! $this->customerSession->getCustomer()->isEmpty()) {
            $this->logger->pushProcessor(function ($record) {
                $customerData = $this->customerSession->getCustomer();

                foreach ($customerData->getData() as $key => $value) {
                    $record['content']['user'][ $key ] = $value;
                }

                return $record;
            });
        }
    }
}
