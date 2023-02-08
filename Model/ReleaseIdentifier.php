<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Version;

class ReleaseIdentifier
{
    /**
     * ReleaseIdentifier constructor.
     *
     * @param Version $version
     */
    public function __construct(
        private readonly Version $version
    ) {
    }

    /**
     * Get release ID from magento internal release number.
     *
     * @return string
     */
    public function getReleaseId(): string
    {
        return $this->version->getValue();
    }
}
