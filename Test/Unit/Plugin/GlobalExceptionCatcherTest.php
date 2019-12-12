<?php

declare(strict_types=1);
/**
 * @by SwiftOtter, Inc., 2019/09/13
 * @website https://swiftotter.com
 **/

namespace JustBetter\Sentry\Test\Unit\Plugin;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\ReleaseIdentifier;
use JustBetter\Sentry\Model\SentryInteraction;
use JustBetter\Sentry\Plugin\GlobalExceptionCatcher;
use Magento\Framework\AppInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class GlobalExceptionCatcherTest extends TestCase
{
    public function testNotActiveReturnsImmediately()
    {
        $helper = $this->getSentryHelperMock(false);
        $helper->expects($this->never())
            ->method('getDSN');

        /** @var GlobalExceptionCatcher $plugin */
        $plugin = (new ObjectManager($this))->getObject(
            GlobalExceptionCatcher::class,
            [
                'sentryHelper'      => $helper,
                'releaseIdentifier' => $this->getReleaseIdentifierMock(),
                'sentryInteraction' => $this->getSentryInteractionMock(false, false),
            ]
        );

        $called = false;
        $plugin->aroundLaunch(
            $this->getAppInterfaceMock(),
            function () use (&$called) {
                $called = true;
            }
        );

        $this->assertTrue($called);
    }

    public function testInitializeIsCalledWithCorrectCredentials()
    {
        $helper = $this->getSentryHelperMock(true, 'test');
        $helper->expects($this->once())
            ->method('getDSN');

        $helper->expects($this->once())
            ->method('getEnvironment');

        /** @var GlobalExceptionCatcher $plugin */
        $plugin = (new ObjectManager($this))->getObject(
            GlobalExceptionCatcher::class,
            [
                'sentryHelper'      => $helper,
                'releaseIdentifier' => $this->getReleaseIdentifierMock(),
                'sentryInteraction' => $this->getSentryInteractionMock(true, false),
            ]
        );

        $called = false;
        $plugin->aroundLaunch(
            $this->getAppInterfaceMock(),
            function () use (&$called) {
                $called = true;
            }
        );

        $this->assertTrue($called);
    }

    public function testCaptureExceptionIsCalled()
    {
        $helper = $this->getSentryHelperMock(true, 'test');

        /** @var GlobalExceptionCatcher $plugin */
        $plugin = (new ObjectManager($this))->getObject(
            GlobalExceptionCatcher::class,
            [
                'sentryHelper'      => $helper,
                'releaseIdentifier' => $this->getReleaseIdentifierMock(),
                'sentryInteraction' => $this->getSentryInteractionMock(true, true),
            ]
        );

        $called = false;
        $exceptionMessage = 'Big problem';
        $this->expectExceptionMessage($exceptionMessage);

        $plugin->aroundLaunch(
            $this->getAppInterfaceMock(),
            function () use (&$called, $exceptionMessage) {
                $called = true;

                throw new \Exception($exceptionMessage);
            }
        );

        $this->assertTrue($called);
    }

    private function getAppInterfaceMock()
    {
        return $this->getMockForAbstractClass(AppInterface::class);
    }

    private function getSentryInteractionMock(bool $expectsInitialize, bool $expectsCaptureException)
    {
        $mock = $this->createConfiguredMock(
            SentryInteraction::class,
            [
                'initialize'       => '',
                'captureException' => '',
            ]
        );

        $mock->expects($expectsInitialize ? $this->atLeastOnce() : $this->never())
            ->method('initialize');

        $mock->expects($expectsCaptureException ? $this->atLeastOnce() : $this->never())
            ->method('captureException');

        return $mock;
    }

    private function getReleaseIdentifierMock()
    {
        $mock = $this->createConfiguredMock(
            ReleaseIdentifier::class,
            [
                'getReleaseId' => 1,
            ]
        );

        return $mock;
    }

    private function getSentryHelperMock(bool $isActive = true, $environment = null)
    {
        $mock = $this->createConfiguredMock(Data::class, [
            'isActive'       => $isActive,
            'getDSN'         => 'dsn',
            'getEnvironment' => $environment,
        ]);

        $mock->expects($this->once())
            ->method('isActive');

        return $mock;
    }
}
