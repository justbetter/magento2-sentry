<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Helper\Version;
use Laminas\Uri\UriFactory;
use Magento\Csp\Api\Data\ModeConfiguredInterface;
use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Model\Mode\Data\ModeConfigured;

class CspModeConfigManagerPlugin
{
    /**
     * CspModeConfigManagerPlugin constructor.
     *
     * @param Version $version
     * @param Data    $config
     */
    public function __construct(
        private readonly Version $version,
        private readonly Data $config
    ) {
    }

    /**
     * Gather the report-uri based on the Sentry DSN, if no uri has been set.
     *
     * @param ModeConfigManagerInterface $subject
     * @param ModeConfiguredInterface    $result
     *
     * @return ModeConfiguredInterface
     */
    public function afterGetConfigured(ModeConfigManagerInterface $subject, ModeConfiguredInterface $result): ModeConfiguredInterface
    {
        if ($result->getReportUri() !== null || !$this->config->isActive() || !$this->config->isEnableCspReportUrl()) {
            return $result;
        }

        $dsn = $this->config->getDSN();
        if (!is_string($dsn) || empty($dsn)) {
            return $result;
        }

        // DSN
        // https://<key>@<organisation>.ingest.<region>.sentry.io/<project>
        // https://<key>@example.com/<project>

        // security-url
        // https://<organisation>.ingest.<region>.sentry.io/api/<project>/security/?sentry_key=<key>
        // https://example.com/api/<project>/security/?sentry_key=<key>

        $uriParsed = UriFactory::factory($dsn);

        $dsnPaths = explode('/', $uriParsed->getPath()); // the last one is the project-id
        $reportUri = sprintf('https://%s/api/%s/security', $uriParsed->getHost(), $dsnPaths[count($dsnPaths) - 1]);

        $params = [
            'sentry_key'         => $uriParsed->getUserInfo(),
            'sentry_release'     => $this->version->getValue(),
            'sentry_environment' => $this->config->getEnvironment(),
        ];

        $params = array_filter($params);
        if ($params !== []) {
            $reportUri .= '&'.http_build_query($params);
        }

        return new ModeConfigured($result->isReportOnly(), $reportUri);
    }
}
