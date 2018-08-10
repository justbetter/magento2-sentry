<?php

namespace JustBetter\Sentry\Model;

use Exception;
use Raven_Client;
use Monolog\Logger;
use Monolog\Handler\RavenHandler;
use JustBetter\Sentry\Helper\Data;
use Magento\Customer\Model\Session;
use Monolog\Formatter\LineFormatter;
use Magento\Framework\Logger\Monolog;

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
        Data $data,
        Session $customerSession,
        array $handlers = [],
        array $processors = []
    ) {
        $this->data = $data;
        $this->customerSession = $customerSession;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Send log rule to Sentry
     *
     * @param  string   $message    The message send to sentry
     * @param  int      $logLevel   Number of loglevel
     */
    public function send($message, $logLevel, Monolog $monolog, $context = [])
    {
        $config = $this->data->collectModuleConfig();

        if ($logLevel >= (int) $config['log_level']) {
            $client = (new Raven_Client($config['domain'] ?? null));
            $handler = new RavenHandler($client, $config['log_level'] ?? Logger::ERROR);
            $tags = $this->getTags();
            $userData = $this->getUserData();

            $client->tags_context($tags);
            $client->user_context($userData);

            $handler->setFormatter(
                new LineFormatter("%level_name%: %message% %context% %extra%\n", null, false, true)
            );

            $monolog->pushHandler($handler);

            if ($message instanceof Exception) {
                $client->captureException($message, [
                    'tags' => $tags,
                    'user' => $userData
                ]);
            }

            /// when using JS SDK you can use this for custom error page printing
            $this->customerSession->setSentryEventId($client->getLastEventID());
        }
    }

    /**
     * Get user data if user is loggedin
     *
     * @return array
     */
    protected function getUserData()
    {
        if ($this->customerSession->isLoggedIn()) {
            $customerData = $this->customerSession->getCustomer();

            return [
                'id' => $customerData->getEntityId(),
                'email' => $customerData->getEmail(),
                'website_id' => $customerData->getWebsiteId(),
                'store_id' => $customerData->getStoreId(),
            ];
        }

        return [];
    }

    /**
     * Get current tags for sentry
     * @return array of magento 2 data
     */
    protected function getTags()
    {
        $store = $this->data->getStore();

        return [
            'mage_mode'     => $this->data->getAppState(),
            'version'       => $this->data->getMagentoVersion(),
            'website_id'    => $store ? $store->getWebsiteId() : null,
            'store_id'      => $store ? $store->getStoreId() : null,
            'store_code'    => $store ? $store->getCode() : null
        ];
    }
}
