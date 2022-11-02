<?php

namespace JustBetter\Sentry\Observer;

use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\App\RequestInterface;
use  \Magento\Framework\App\Response\Http;
use Magento\Framework\Event\ObserverInterface;

class ControllerActionPostdispatchObserver implements ObserverInterface
{
    /** @var SentryPerformance */
    private $sentryPerformance;

    /** @var Http  */
    private $response;

    public function __construct(SentryPerformance $sentryPerformance, Http $response)
    {
        $this->sentryPerformance = $sentryPerformance;
        $this->response = $response;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->sentryPerformance->finishTransaction($this->response);
    }
}
