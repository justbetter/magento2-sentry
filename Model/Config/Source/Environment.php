<?php

namespace JustBetter\Sentry\Model\Config\Source;

class Environment
{
    const ENVIRONMENTS_MAPPING = [
        'production' => 'Production',
        'staging' => 'Staging',
        'development' => 'Development',
        'review' => 'Review'
    ];

    /**
     * @return array[]
     */
    public function toOptionArray()
    {
        return array_map(
            static function (string $key, string $value) {
                return [$key => __($value)];
            },
            array_keys(Environment::ENVIRONMENTS_MAPPING),
            array_values(Environment::ENVIRONMENTS_MAPPING)
        );
    }
}