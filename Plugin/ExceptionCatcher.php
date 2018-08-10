<?php

namespace JustBetter\Sentry\Plugin;

use Exception;
use Magento\Framework\App\Http;
use JustBetter\Sentry\Helper\Data;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Logger\Monolog;
use JustBetter\Sentry\Model\SentryLog;

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
    protected $sentryHelper;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var SentryLog
     */
    protected $sentryLog;


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
        Monolog $monolog,
        SentryLog $sentryLog
    ) {
        $this->sentryHelper = $data;
        $this->monolog = $monolog;
        $this->sentryLog = $sentryLog;
    }

    /**
     * Catch any exceptions and notify an instance of \Sentry\Client
     *
     * @param Http       $subject
     * @param Bootstrap  $bootstrap
     * @param Exception $exception
     * @return array
     */
    public function beforeCatchException(Http $subject, Bootstrap $bootstrap, Exception $exception)
    {
        if ($this->sentryHelper->isActive()) {
            $this->sentryLog->send($exception, 500, $this->monolog);
        }

        return [$bootstrap, $exception];
    }
}
