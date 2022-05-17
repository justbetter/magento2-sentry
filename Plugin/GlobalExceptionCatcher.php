<?php

namespace JustBetter\Sentry\Plugin;

// phpcs:disable Magento2.CodeAnalysis.EmptyBlock

use JustBetter\Sentry\Helper\Data as SenteryHelper;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryInteraction;
use Magento\Framework\AppInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class GlobalExceptionCatcher
{
    /** @var SenteryHelper */
    protected $sentryHelper;

    /** @var ReleaseIdentifier */
    private $releaseIdentifier;

    /** @var SentryInteraction */
    private $sentryInteraction;

    /** @var EventManagerInterface */
    private $eventManager;

    /** @var DataObjectFactory */
    private $dataObjectFactory;

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
        SenteryHelper $sentryHelper,
        ReleaseIdentifier $releaseIdentifier,
        SentryInteraction $sentryInteraction,
        EventManagerInterface $eventManager,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->sentryHelper = $sentryHelper;
        $this->releaseIdentifier = $releaseIdentifier;
        $this->sentryInteraction = $sentryInteraction;
        $this->eventManager = $eventManager;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    public function aroundLaunch(AppInterface $subject, callable $proceed)
    {
        if ((!$this->sentryHelper->isActive()) || (!$this->sentryHelper->isPhpTrackingEnabled())) {
            return $proceed();
        }

        $config = $this->dataObjectFactory->create();

        $config->setDsn($this->sentryHelper->getDSN());
        if ($release = $this->releaseIdentifier->getReleaseId()) {
            $config->setRelease((string) $release);
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
        } catch (\Throwable $ex) {
            try {
                if ($this->sentryHelper->shouldCaptureException($ex)) {
                    $this->sentryInteraction->captureException($ex);
                }
            } catch (\Throwable $bigProblem) {
                // do nothing if sentry fails
            }

            throw $ex;
        }
    }
}
