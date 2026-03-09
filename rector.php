<?php

declare(strict_types=1);

use Rector\Configuration\RectorConfigBuilder;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;

/** @var RectorConfigBuilder $rectorConfig */
$rectorConfig = require 'vendor/justbetter/magento2-coding-standard/rector.php';

$rectorConfig->withPaths([
    __DIR__.'/Adminhtml',
    __DIR__.'/Block',
    __DIR__.'/Cache',
    __DIR__.'/Collector',
    __DIR__.'/Config',
    __DIR__.'/Controller',
    __DIR__.'/etc',
    __DIR__.'/Handler',
    __DIR__.'/Helper',
    __DIR__.'/Model',
    __DIR__.'/Observer',
    __DIR__.'/Plugin',
    __DIR__.'/view',
]);

/** Define additional rules here
 * @see: https://getrector.com/find-rule?activeRectorSetGroup=php
 * @see: https://getrector.com/find-rule?activeRectorSetGroup=core
 */

$rectorConfig->withSkip([
    RemoveExtraParametersRector::class,
    MixedTypeRector::class,
]);

/** @see: https://getrector.com/documentation/levels */
$rectorConfig->withTypeCoverageLevel(4);         // 1 is least intrusive changes, higher is more intrusive
$rectorConfig->withCodeQualityLevel(10);          // 1 is least intrusive changes, higher is more intrusive
$rectorConfig->withDeadCodeLevel(1);             // 1 is least intrusive changes, higher is more intrusive

$rectorConfig->withPreparedSets(
    // Only enable these when the levels above are completed and their config is removed
    // It will automatically set their level to the highest possible.
    // typeDeclarations: true,  // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-type-declarations
    // codeQuality: true,       // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-code-quality
    // deadCode: true,          // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-dead-code
    instanceOf: false,       // https://getrector.com/find-rule?rectorSet=core-instanceof&activeRectorSetGroup=core
    earlyReturn: false,      // https://getrector.com/find-rule?rectorSet=core-early-return&activeRectorSetGroup=core
);

return $rectorConfig;
