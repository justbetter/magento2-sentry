<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Helper\Data as SentryHelper;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryCron;
use JustBetter\Sentry\Model\SentryInteraction;
use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Cron\Model\Schedule;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class CronScheduleCheckIn
{
    protected array $runningCheckins = [];

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
        private SentryCron $sentryCron
    ) {
    }

    /**
     * Wrap launch, start watching for exceptions.
     *
     * @param Schedule $subject
     * @param callable $proceed
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function aroundSetData(Schedule $subject, callable $proceed, $field, $value = null)
    {
        $result = $proceed($field, $value);
        if ($field !== 'status') {
            return $result;
        }

        $this->sentryCron->sendScheduleStatus($subject);

        return $result;
    }
}
