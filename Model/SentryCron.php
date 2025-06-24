<?php

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Data;
use Magento\Cron\Model\Schedule;
use Magento\Customer\Model\Session;
use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;

class SentryCron
{
    /**
     * @var array
     */
    protected array $runningCheckins = [];

    /**
     * SentryLog constructor.
     *
     * @param Data    $data
     * @param Session $customerSession
     */
    public function __construct(
        protected Data $data,
        protected Session $customerSession,
    ) {
    }

    /**
     * Send the status of a cron schedule to Sentry.
     *
     * @param Schedule $schedule
     */
    public function sendScheduleStatus(Schedule $schedule)
    {
        if (!$this->data->isActive() ||
            !$this->data->isCronMonitoringEnabled() ||
            !in_array(
                $schedule->getJobCode(),
                $this->data->getTrackCrons()
            )
        ) {
            return;
        }

        $status = $schedule->getStatus();
        if (!in_array($status, [
            Schedule::STATUS_RUNNING,
            Schedule::STATUS_SUCCESS,
            Schedule::STATUS_ERROR,
        ])) {
            return;
        }

        /** @var array|null $cronExpressionArr */
        $cronExpressionArr = $schedule->getCronExprArr();
        $monitorConfig = null;
        if (!empty($cronExpressionArr)) {
            $cronExpression = implode(' ', $cronExpressionArr);
            $monitorConfig = new MonitorConfig(MonitorSchedule::crontab($cronExpression));
        }

        if ($status === Schedule::STATUS_RUNNING) {
            if (!isset($this->runningCheckins[$schedule->getId()])) {
                $this->startCheckin($schedule, $monitorConfig);
            }

            return;
        } else {
            $this->finishCheckin($schedule, $monitorConfig);
        }
    }

    /**
     * Start the check-in for a given schedule.
     *
     * @param Schedule           $schedule
     * @param MonitorConfig|null $monitorConfig
     */
    public function startCheckin(Schedule $schedule, ?MonitorConfig $monitorConfig = null)
    {
        $this->runningCheckins[$schedule->getId()] = [
            'started_at'  => microtime(true),
            'check_in_id' => \Sentry\captureCheckIn(
                slug: $schedule->getJobCode(),
                status: CheckInStatus::inProgress(),
                monitorConfig: $monitorConfig,
            ),
        ];
    }

    /**
     * Finish the check-in for a given schedule.
     *
     * @param Schedule           $schedule
     * @param MonitorConfig|null $monitorConfig
     */
    public function finishCheckin(Schedule $schedule, ?MonitorConfig $monitorConfig = null)
    {
        if (!isset($this->runningCheckins[$schedule->getId()])) {
            return;
        }

        \Sentry\captureCheckIn(
            slug: $schedule->getJobCode(),
            status: $schedule->getStatus() === Schedule::STATUS_SUCCESS ? CheckInStatus::ok() : CheckInStatus::error(),
            duration: microtime(true) - $this->runningCheckins[$schedule->getId()]['started_at'],
            monitorConfig: $monitorConfig,
            checkInId: $this->runningCheckins[$schedule->getId()]['check_in_id'],
        );

        unset($this->runningCheckins[$schedule->getId()]);
    }
}
