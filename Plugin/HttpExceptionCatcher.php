<?php
declare(strict_types=1);

namespace JustBetter\Sentry\Plugin;

use Exception;
use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Http;
use Magento\Framework\Logger\Monolog;

class HttpExceptionCatcher
{
    /**
     * @var Data
     */
    protected $sentryHelper;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Monolog
     */
    protected $monolog;

    /**
     * @var SentryLog
     */
    protected $sentryLog;

    /**
     * HttpExceptionCatcher constructor.
     * @param Data $data
     * @param Monolog $monolog
     * @param SentryLog $sentryLog
     */
    public function __construct(Data $data, Monolog $monolog, SentryLog $sentryLog)
    {
        $this->sentryHelper = $data;
        $this->monolog = $monolog;
        $this->sentryLog = $sentryLog;
    }

    /**
     * Catch any exceptions and notify an instance of \Sentry\Client
     *
     * @param Http $subject
     * @param Bootstrap $bootstrap
     * @param Exception $exception
     * @return array
     */
    public function beforeCatchException(Http $subject, Bootstrap $bootstrap, Exception $exception)
    {
//        if ($this->sentryHelper->isActive()) {
//            $this->sentryLog->send($exception, 500, $this->monolog);
//        }

        return [$bootstrap, $exception];
    }
}
