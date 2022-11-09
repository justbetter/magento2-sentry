<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\DB\Logger\LoggerProxy;

/**
 * Plugin to add DB Queries from the ProxyLogger to Sentry
 */
class LoggerProxyPlugin
{
    /** @var SentryPerformance */
    private $sentryPerformance;

    /** @var float|null */
    private $timer;

    public function __construct(SentryPerformance $sentryPerformance)
    {
        $this->sentryPerformance = $sentryPerformance;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeStartTimer()
    {
        $this->timer = microtime(true);
    }

    /**
     * @param LoggerProxy $subject
     * @param string $type
     * @param string $sql
     * @param array $bind
     * @param \Zend_Db_Statement_Pdo|null $result
     * @return void
     */
    public function beforeLogStats(LoggerProxy $subject, $type, $sql, $bind = [], $result = null)
    {
        $this->sentryPerformance->addSqlQuery($sql, $this->timer);
    }
}
