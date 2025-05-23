<?php

namespace JustBetter\Sentry\Observer;

use JustBetter\Sentry\Helper\Data;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sentry\Event;
use Sentry\Frame;

class StripUnnecessaryFrames implements ObserverInterface
{
    /**
     * @param Data $sentryHelper
     */
    public function __construct(
        private readonly Data $sentryHelper
    ) {
    }

    /**
     * Remove useless frames like Interceptors and Proxies from the stacktrace.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->sentryHelper->getCleanStacktrace()) {
            return;
        }

        /** @var Event $event */
        $event = $observer->getEvent()->getSentryEvent()->getEvent();

        foreach ($event->getExceptions() as $exception) {
            $stacktrace = $exception->getStacktrace();
            if (!$stacktrace) {
                continue;
            }

            $stacktraceLength = count($stacktrace->getFrames()) - 1;
            // Get the last frame in the stacktrace and make sure it's never removed.
            $lastFrame = $stacktrace->getFrame($stacktraceLength--);
            for ($i = $stacktraceLength; $i >= 0; $i--) {
                $frame = $stacktrace->getFrame($i);
                $file = $frame->getFile();
                if ($file === $lastFrame->getFile()) {
                    // Anything in the file that threw the exception is relevant.
                    continue;
                }

                if ($this->shouldSkipFrame($frame)) {
                    $stacktrace->removeFrame($i);
                }
            }
        }
    }

    /**
     * Check if the frame should be skipped.
     *
     * @param Frame $frame
     * @return bool
     */
    public function shouldSkipFrame(Frame $frame): bool
    {
        $file = $frame->getFile();

        return str_ends_with($file, 'Interceptor.php')
            || str_ends_with($file, 'Proxy.php')
            || str_ends_with($file, 'Factory.php');
    }
}
