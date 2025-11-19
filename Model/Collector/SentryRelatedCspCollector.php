<?php

namespace JustBetter\Sentry\Model\Collector;

use JustBetter\Sentry\Helper\Data as DataHelper;
use Laminas\Uri\UriFactory;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

class SentryRelatedCspCollector implements PolicyCollectorInterface
{
    /**
     * @param DataHelper $dataHelper
     */
    public function __construct(
        private DataHelper $dataHelper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        $policies = $defaultPolicies;
        if (!$this->dataHelper->isActive()) {
            return $policies;
        }

        if ($this->dataHelper->useScriptTag()) {
            $policies[] = new FetchPolicy(
                'script-src',
                false,
                ['https://browser.sentry-cdn.com']
            );

            $policies[] = new FetchPolicy(
                'script-src',
                false,
                ['https://js.sentry-cdn.com']
            );

            $customLoader = $this->dataHelper->getLoaderScript();
            $customLoaderHost = is_string($customLoader) ? UriFactory::factory($customLoader) : null;
            if ($customLoaderHost !== null) {
                $policies[] = new FetchPolicy(
                    'script-src',
                    false,
                    [$customLoaderHost->getScheme() . '://' . $customLoaderHost->getHost()]
                );
            }

            $dsn = $this->dataHelper->getDsn();
            $dsnHost = is_string($dsn) ? UriFactory::factory($dsn)->getHost() : null;
            if (!empty($dsnHost)) {
                $policies[] = new FetchPolicy(
                    'connect-src',
                    false,
                    [$dsnHost]
                );
            }
        }

        if ($this->dataHelper->isSpotlightEnabled()) {
            $policies[] = new FetchPolicy(
                'script-src',
                false,
                ['https://unpkg.com/@spotlightjs/']
            );
        }

        if ($this->dataHelper->useLogrocket()) {
            $policies[] = new FetchPolicy(
                'script-src',
                false,
                ['https://cdn.lr-ingest.io']
            );
        }

        return $policies;
    }
}
