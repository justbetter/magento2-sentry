<?php

namespace JustBetter\Sentry\Cache\Frontend;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\ObjectManagerInterface;

class Factory extends \Magento\Framework\App\Cache\Frontend\Factory
{
    /**
     * @param ObjectManagerInterface $objectManager
     * @param Filesystem             $filesystem
     * @param ResourceConnection     $resource
     * @param array                  $enforcedOptions
     * @param array                  $decorators
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Filesystem $filesystem,
        ResourceConnection $resource,
        array $enforcedOptions = [],
        array $decorators = []
    ) {
        $decorators['sentry'] = [
            'class' => \JustBetter\Sentry\Plugin\Profiling\Cache::class,
        ];

        parent::__construct($objectManager, $filesystem, $resource, $enforcedOptions, $decorators);
    }
}
