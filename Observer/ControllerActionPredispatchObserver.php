<?php

namespace JustBetter\Sentry\Observer;

use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;

class ControllerActionPredispatchObserver implements ObserverInterface
{
    /** @var SentryPerformance */
    private $sentryPerformance;

    /** @var Http */
    private $request;

    public function __construct(SentryPerformance $sentryPerformance, Http $request)
    {
        $this->sentryPerformance = $sentryPerformance;
        $this->request = $request;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->sentryPerformance->startTransaction($this->request);
    }
}
