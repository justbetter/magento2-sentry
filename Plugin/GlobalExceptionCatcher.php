<?php

namespace JustBetter\Sentry\Plugin;

// phpcs:disable Magento2.CodeAnalysis.EmptyBlock

use JustBetter\Sentry\Helper\Data as SenteryHelper;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryInteraction;
use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\AppInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class GlobalExceptionCatcher
{
    public function __construct(
        protected SenteryHelper $sentryHelper,
        private ReleaseIdentifier $releaseIdentifier,
        private SentryInteraction $sentryInteraction,
        private EventManagerInterface $eventManager,
        private DataObjectFactory $dataObjectFactory,
        private SentryPerformance $sentryPerformance
    ) {
    }

    public function aroundLaunch(AppInterface $subject, callable $proceed)
    {
        if ((!$this->sentryHelper->isActive()) || (!$this->sentryHelper->isPhpTrackingEnabled())) {
            return $proceed();
        }

        /** @var DataObject $config */
        $config = $this->dataObjectFactory->create();

        $config->setDsn($this->sentryHelper->getDSN());
        if ($release = $this->releaseIdentifier->getReleaseId()) {
            $config->setRelease((string) $release);
        }

        if ($environment = $this->sentryHelper->getEnvironment()) {
            $config->setEnvironment($environment);
        }

        $config->setBeforeSend(function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
            $data = $this->dataObjectFactory->create();
            $data->setEvent($event);
            $data->setHint($hint);
            $this->eventManager->dispatch('sentry_before_send', [
                'sentry_event' => $data,
            ]);

            return $data->getEvent();
        });

        if ($this->sentryHelper->isPerformanceTrackingEnabled()) {
            $config->setTracesSampleRate($this->sentryHelper->getTracingSampleRate());
        }

        $this->eventManager->dispatch('sentry_before_init', [
            'config' => $config,
        ]);

        $this->sentryInteraction->initialize($config->getData());
        $this->sentryPerformance->startTransaction($subject);

        try {
            return $response = $proceed();
        } catch (\Throwable $ex) {
            try {
                if ($this->sentryHelper->shouldCaptureException($ex)) {
                    $this->sentryInteraction->captureException($ex);
                }
            } catch (\Throwable $bigProblem) {
                // do nothing if sentry fails
            }

            throw $ex;
        } finally {
            try {
                $this->sentryPerformance->finishTransaction($response ?? 500);
            } catch (\Throwable $bigProblem) {
                // do nothing if sentry fails
            }
        }
    }
}
