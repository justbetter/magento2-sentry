<?php

namespace JustBetter\Sentry\Plugin;

// phpcs:disable Magento2.CodeAnalysis.EmptyBlock

use JustBetter\Sentry\Helper\Data as SentryHelper;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryInteraction;
use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\AppInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Sentry\Integration\IntegrationInterface;
use Throwable;

class GlobalExceptionCatcher
{
    /**
     * GlobalExceptionCatcher constructor.
     *
     * @param SentryHelper          $sentryHelper
     * @param ReleaseIdentifier     $releaseIdentifier
     * @param SentryInteraction     $sentryInteraction
     * @param EventManagerInterface $eventManager
     * @param DataObjectFactory     $dataObjectFactory
     * @param SentryPerformance     $sentryPerformance
     */
    public function __construct(
        private SentryHelper $sentryHelper,
        private ReleaseIdentifier $releaseIdentifier,
        private SentryInteraction $sentryInteraction,
        private EventManagerInterface $eventManager,
        private DataObjectFactory $dataObjectFactory,
        private SentryPerformance $sentryPerformance
    ) {
    }

    /**
     * Wrap launch, start watching for exceptions.
     *
     * @param AppInterface $subject
     * @param callable     $proceed
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
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

        $disabledDefaultIntegrations = $this->sentryHelper->getDisabledDefaultIntegrations();
        $config->setData('integrations', static fn (array $integrations) => array_filter(
            $integrations,
            static fn (IntegrationInterface $integration) => !in_array(get_class($integration), $disabledDefaultIntegrations)
        ));

        if ($this->sentryHelper->isPerformanceTrackingEnabled()) {
            $config->setTracesSampleRate($this->sentryHelper->getTracingSampleRate());
        }

        if ($rate = $this->sentryHelper->getPhpProfileSampleRate()) {
            $config->setData('profiles_sample_rate', $rate);
        }

        $this->eventManager->dispatch('sentry_before_init', [
            'config' => $config,
        ]);

        $this->sentryInteraction->initialize($config->getData());
        $this->sentryPerformance->startTransaction($subject);

        try {
            return $response = $proceed();
        } catch (Throwable $exception) {
            $this->sentryInteraction->captureException($exception);

            throw $exception;
        } finally {
            $this->sentryPerformance->finishTransaction($response ?? 500);
        }
    }
}
