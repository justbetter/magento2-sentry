<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Version;

class ReleaseIdentifier
{
    /**
     * @var Version
     */
    private $version;

    /**
     * ReleaseIdentifier constructor.
     *
     * @param Version $version
     */
    public function __construct(
        Version $version
    ) {
        $this->version = $version;
    }

    /**
     * Get release ID from magento internal release number.
     *
     * @return string
     */
    public function getReleaseId()
    {
        return $this->version->getValue();
    }
}
