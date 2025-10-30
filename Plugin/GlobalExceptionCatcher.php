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
use Symfony\Component\Console\Command\Command;
use Throwable;

/**
 * Catch all uncaught exceptions globally, and send them to Sentry.
 *
 * It wraps the launch and run methods of the application and command classes.
 */
class GlobalExceptionCatcher
{
    /** @var bool Whether the globalExceptionHandler is already attached */
    private bool $booted = false;

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
     * @param mixed        $args
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function aroundLaunch(AppInterface $subject, callable $proceed, ...$args)
    {
        return $this->globalCatcher(
            $subject,
            $proceed,
            ...$args
        );
    }

    /**
     * Wrap command run, start watching for exceptions.
     *
     * @param Command  $subject
     * @param callable $proceed
     * @param mixed    $args
     *
     * @return int
     */
    public function aroundRun(Command $subject, callable $proceed, ...$args)
    {
        return $this->globalCatcher(
            $subject,
            $proceed,
            ...$args
        );
    }

    /**
     * Catch anything coming out of the proceed function.
     *
     * @param mixed    $subject
     * @param callable $proceed
     * @param mixed    $args
     *
     * @return mixed
     */
    public function globalCatcher($subject, $proceed, ...$args)
    {
        if ($this->booted) {
            return $proceed(...$args);
        }

        $this->booted = true;

        if ((!$this->sentryHelper->isActive()) || (!$this->sentryHelper->isPhpTrackingEnabled())) {
            return $proceed(...$args);
        }

        $config = $this->prepareConfig();

        $this->sentryInteraction->initialize(array_filter($config->getData()));
        $this->sentryPerformance->startTransaction($subject);

        try {
            return $response = $proceed(...$args);
        } catch (Throwable $exception) {
            $this->sentryInteraction->captureException($exception);

            throw $exception;
        } finally {
            $this->sentryPerformance->finishTransaction($response ?? 500);
        }
    }

    /**
     * Prepare all the config passed to sentry.
     *
     * @return DataObject
     */
    public function prepareConfig(): DataObject
    {
        /** @var DataObject $config */
        $config = $this->dataObjectFactory->create();
        $config->setData(array_intersect_key($this->sentryHelper->collectModuleConfig(), SentryHelper::NATIVE_SENTRY_CONFIG_KEYS));

        $config->setSpotlight($this->sentryHelper->isSpotlightEnabled());
        $config->setDsn($this->sentryHelper->getDSN());
        if ($release = $this->releaseIdentifier->getReleaseId()) {
            $config->setRelease((string) $release);
        }

        if ($environment = $this->sentryHelper->getEnvironment()) {
            $config->setEnvironment($environment);
        }

        $config->setBeforeBreadcrumb(function (\Sentry\Breadcrumb $breadcrumb): ?\Sentry\Breadcrumb {
            $data = $this->dataObjectFactory->create();
            $data->setBreadcrumb($breadcrumb);
            $this->eventManager->dispatch('sentry_before_breadcrumb', [
                'sentry_breadcrumb' => $data,
            ]);

            return $data->getBreadcrumb();
        });

        $config->setBeforeSendTransaction(function (\Sentry\Event $transaction): ?\Sentry\Event {
            $data = $this->dataObjectFactory->create();
            $data->setTransaction($transaction);
            $this->eventManager->dispatch('sentry_before_send_transaction', [
                'sentry_transaction' => $data,
            ]);

            return $data->getTransaction();
        });

        $config->setBeforeSendCheckIn(function (\Sentry\Event $checkIn): ?\Sentry\Event {
            $data = $this->dataObjectFactory->create();
            $data->setCheckIn($checkIn);
            $this->eventManager->dispatch('sentry_before_send_check_in', [
                'sentry_check_in' => $data,
            ]);

            return $data->getCheckIn();
        });

        $config->setBeforeSendLog(function (\Sentry\Logs\Log $log): ?\Sentry\Logs\Log {
            $data = $this->dataObjectFactory->create();
            $data->setLog($log);
            $this->eventManager->dispatch('sentry_before_send_log', [
                'sentry_log' => $data,
            ]);

            return $data->getLog();
        });

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

        $config->setErrorTypes($this->sentryHelper->getErrorTypes());

        if ($this->sentryHelper->isPerformanceTrackingEnabled()) {
            $config->setTracesSampleRate($this->sentryHelper->getTracingSampleRate());
        } else {
            $config->unsetTracesSampleRate(null);
        }

        $config->setInAppExclude([
            ...($config->getInAppExclude() ?? []),
            'cron.php',
            'get.php',
            'health_check.php',
            'index.php',
            'static.php',
            'bin/magento',
            'app/autoload.php',
            'app/bootstrap.php',
            'app/functions.php',
        ]);

        $this->eventManager->dispatch('sentry_before_init', [
            'config' => $config,
        ]);

        return $config;
    }
}
