<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Model\SentryCron;
use Magento\Cron\Model\Schedule;

class CronScheduleCheckIn
{
    /**
     * CronScheduleCheckIn constructor.
     *
     * @param SentryCron   $sentryCron
     */
    public function __construct(
        private SentryCron $sentryCron
    ) {
    }

    /**
     * Wrap launch, start watching for exceptions.
     *
     * @param Schedule $subject
     * @param callable $proceed
     * @param mixed    $field
     * @param mixed    $value
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
