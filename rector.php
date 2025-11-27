<?php

declare(strict_types=1);

use Magento2\Rector\Src\ReplaceNewDateTimeNull;
use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodingStyle\Rector\ClassConst\RemoveFinalFromConstRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Property\RemoveUselessReadOnlyTagRector;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Php\PhpVersionResolver\ComposerJsonPhpVersionResolver;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamArrayDocblockBasedOnCallableNativeFuncCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnArrayDocblockBasedOnArrayMapRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddParamTypeSplFixedArrayRector;

return static function (RectorConfig $rectorConfig): void {
    $magentoRector = require 'vendor/magento/magento-coding-standard/rector.php'; // phpcs:ignore Magento2.Security.IncludeFile.FoundIncludeFile
    $magentoRector($rectorConfig);

    $rectorConfig->paths([
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

    // register a single rule
    $rectorConfig->rules([
        InlineConstructorDefaultToPropertyRector::class,
        RemoveFinalFromConstRector::class,
        RemoveEmptyClassMethodRector::class,
        CompleteDynamicPropertiesRector::class,
        RemoveNonExistingVarAnnotationRector::class,
        RemoveUselessReadOnlyTagRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
        AddParamArrayDocblockBasedOnCallableNativeFuncCallRector::class,
        AddReturnArrayDocblockBasedOnArrayMapRector::class,
        AddParamTypeSplFixedArrayRector::class,
    ]);

    $rectorConfig->skip([
        ReadOnlyPropertyRector::class,
        ReplaceNewDateTimeNull::class,
        RemoveExtraParametersRector::class,
        MixedTypeRector::class,
    ]);

    try {
        $projectPhpVersion = ComposerJsonPhpVersionResolver::resolveFromCwdOrFail();
        $phpLevelSets = \Rector\Configuration\PhpLevelSetResolver::resolveFromPhpVersion($projectPhpVersion);
    } catch (InvalidConfigurationException) {
        $phpLevelSets = [LevelSetList::UP_TO_PHP_84];
    }
    // define sets of rules
    $rectorConfig->sets($phpLevelSets);
};
