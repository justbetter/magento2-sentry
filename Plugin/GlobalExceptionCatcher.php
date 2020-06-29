<?php

namespace JustBetter\Sentry\Plugin;

// phpcs:disable Magento2.CodeAnalysis.EmptyBlock

use JustBetter\Sentry\Helper\Data as SenteryHelper;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryInteraction;
use Magento\Framework\AppInterface;

class GlobalExceptionCatcher
{
    /** @var SenteryHelper */
    protected $sentryHelper;

    /** @var ReleaseIdentifier */
    private $releaseIdentifier;

    /** @var SentryInteraction */
    private $sentryInteraction;

    /**
     * ExceptionCatcher constructor.
     *
     * @param SenteryHelper     $sentryHelper
     * @param ReleaseIdentifier $releaseIdentifier
     * @param SentryInteraction $sentryInteraction
     */
    public function __construct(
        SenteryHelper $sentryHelper,
        ReleaseIdentifier $releaseIdentifier,
        SentryInteraction $sentryInteraction
    ) {
        $this->sentryHelper = $sentryHelper;
        $this->releaseIdentifier = $releaseIdentifier;
        $this->sentryInteraction = $sentryInteraction;
    }

    public function aroundLaunch(AppInterface $subject, callable $proceed)
    {
        if (!$this->sentryHelper->isActive()) {
            return $proceed();
        }

        $config = ['dsn' => $this->sentryHelper->getDSN()];
        if ($release = $this->releaseIdentifier->getReleaseId()) {
            $config['release'] = (string) $release;
        }

        if ($environment = $this->sentryHelper->getEnvironment()) {
            $config['environment'] = $environment;
        }

        $this->sentryInteraction->initialize($config);

        try {
            return $proceed();
        } catch (\Throwable $ex) {
            try {
                $this->sentryInteraction->captureException($ex);
            } catch (\Throwable $bigProblem) {
                // do nothing if sentry fails
            }

            throw $ex;
        }
    }
}
