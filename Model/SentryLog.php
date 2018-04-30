<?php

namespace JustBetter\Sentry\Model;

use Magento\Framework\Logger\Monolog;
use Magento\Customer\Model\Session;
use JustBetter\Sentry\Helper\Data;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RavenHandler;
use Monolog\Logger;
use Raven_Client;

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

    /**
     * SentryLog constructor.
     *
     * @param string          $name
     * @param array           $handlers
     * @param array           $processors
     * @param Data|Data\Proxy $data
     * @param Session\Proxy   $customerSession
     */
    public function __construct(
        $name,
        array $handlers = [],
        array $processors = [],
        Data\Proxy $data,
        Session\Proxy $customerSession
    )
    {
        $this->data = $data;
        $this->customerSession = $customerSession;
        parent::__construct($name, $handlers, $processors);
    }


    /**
     * Adds a log record.
     *
     * @param integer $level   The logging level
     * @param string  $message The log message
     * @param array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [])
    {
        if ($this->data->isProductionMode()) {
            $this->sendRecordToSentry($message, $level);
        }

        if ($message instanceof \Exception && ! isset($context['exception'])) {
            $context['exception'] = $message;
        }

        $message = $message instanceof \Exception ? $message->getMessage() : $message;

        return parent::addRecord($level, $message, $context);
    }

    protected function sendRecordToSentry($message, $logLevel)
    {
        $this->config = $this->data->collectModuleConfig();

        if ($this->data->isActive() && $logLevel >= (int) $this->config['log_level']) {

            $client = (new Raven_Client(
                $this->config['domain'] ?? null
            ));

            $handler = new RavenHandler(
                $client,
                $this->config['log_level'] ?? Logger::ERROR
            );

            $client->tags_context([
                'mage_mode' => $this->data->getAppState()
            ]);

            $handler->setFormatter(
                new LineFormatter("%message% %context% %extra%\n")
            );

            $this->pushHandler($handler);
            $sentryId = $client->captureMessage($message);

            /** when printing this error reference on an error page for a feedback form */
            $this->customerSession->setSentryEventId($sentryId);
        }
    }

    protected function captureUserData()
    {
        if ($this->customerSession && ! $this->customerSession->getCustomer()->isEmpty()) {
            $this->pushProcessor(function ($record) {
                $customerData = $this->customerSession->getCustomer();

                foreach ($customerData->getData() as $key => $value) {
                    $record['content']['user'][ $key ] = $value;
                }

                return $record;
            });
        }
    }
}
