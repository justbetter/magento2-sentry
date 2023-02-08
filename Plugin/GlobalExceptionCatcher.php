<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin;

// phpcs:disable Magento2.CodeAnalysis.EmptyBlock

use JustBetter\Sentry\Helper\Data as SenteryHelper;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryInteraction;
use Magento\Framework\AppInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Throwable;

class GlobalExceptionCatcher
{
    /**
     * ExceptionCatcher constructor.
     *
     * @param SenteryHelper         $sentryHelper
     * @param ReleaseIdentifier     $releaseIdentifier
     * @param SentryInteraction     $sentryInteraction
     * @param EventManagerInterface $eventManager
     * @param DataObjectFactory     $dataObjectFactory
     */
    public function __construct(
        protected SenteryHelper            $sentryHelper,
        private readonly ReleaseIdentifier $releaseIdentifier,
        private readonly SentryInteraction          $sentryInteraction,
        private readonly EventManagerInterface      $eventManager,
        private readonly DataObjectFactory          $dataObjectFactory
    ) {
    }

    /**
     * @param AppInterface $subject
     * @param callable $proceed
     * @return mixed
     * @throws Throwable
     */
    public function aroundLaunch(AppInterface $subject, callable $proceed): mixed
    {
        if ((!$this->sentryHelper->isActive()) || (!$this->sentryHelper->isPhpTrackingEnabled())) {
            return $proceed();
        }

        $config = $this->dataObjectFactory->create();

        $config->setDsn($this->sentryHelper->getDSN());
        if ($release = $this->releaseIdentifier->getReleaseId()) {
            $config->setRelease($release);
        }

        if ($environment = $this->sentryHelper->getEnvironment()) {
            $config->setEnvironment($environment);
        }

        $this->eventManager->dispatch('sentry_before_init', [
            'config' => $config,
        ]);

        $this->sentryInteraction->initialize($config->getData());

        try {
            return $proceed();
        } catch (Throwable $ex) {
            try {
                if ($this->sentryHelper->shouldCaptureException($ex)) {
                    $this->sentryInteraction->captureException($ex);
                }
            } catch (Throwable $bigProblem) {
                // do nothing if sentry fails
            }

            throw $ex;
        }
    }
}
