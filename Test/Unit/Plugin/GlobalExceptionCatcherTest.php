<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Plugin;

use JustBetter\Sentry\Helper\Data as SentryHelper;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryInteraction;
use JustBetter\Sentry\Model\SentryPerformance;
use JustBetter\Sentry\Plugin\GlobalExceptionCatcher;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use PHPUnit\Framework\TestCase;

class GlobalExceptionCatcherTest extends TestCase
{
    public function testSampleRatesAreNotPassedToSentryWhenTracingIsDisabled(): void
    {
        $options = $this->prepareConfig(false)->getData();

        $this->assertArrayNotHasKey('traces_sample_rate', $options);
        $this->assertArrayNotHasKey('profiles_sample_rate', $options);
    }

    public function testConfiguredSampleRatesArePassedToSentryWhenTracingIsEnabled(): void
    {
        $options = $this->prepareConfig(true)->getData();

        $this->assertSame(0.5, $options['traces_sample_rate']);
        $this->assertSame(0.25, $options['profiles_sample_rate']);
    }

    private function prepareConfig(bool $tracingEnabled): DataObject
    {
        $helper = $this->createStub(SentryHelper::class);
        $helper->method('collectModuleConfig')->willReturn([
            'traces_sample_rate' => 0.5,
            'profiles_sample_rate' => 0.25,
        ]);
        $helper->method('isPerformanceTrackingEnabled')->willReturn($tracingEnabled);
        $helper->method('getTracingSampleRate')->willReturn(0.5);
        $helper->method('getTracingSampleRateCli')->willReturn(0.5);
        $helper->method('getDisabledDefaultIntegrations')->willReturn([]);
        $helper->method('getErrorTypes')->willReturn(E_ALL);
        $helper->method('getPrefixes')->willReturn([]);

        $dataObjectFactory = $this->createStub(DataObjectFactory::class);
        $dataObjectFactory->method('create')->willReturnCallback(static fn (): DataObject => new DataObject());

        $plugin = new GlobalExceptionCatcher(
            $helper,
            $this->createStub(ReleaseIdentifier::class),
            $this->createStub(SentryInteraction::class),
            $this->createStub(EventManagerInterface::class),
            $dataObjectFactory,
            $this->createStub(SentryPerformance::class)
        );

        return $plugin->prepareConfig();
    }
}
