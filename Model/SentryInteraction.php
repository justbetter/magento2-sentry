<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use JustBetter\Sentry\Helper\Data;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigInterface;
use ReflectionClass;
use Sentry\State\Scope;
use Throwable;

use function Sentry\captureException;
use function Sentry\configureScope;
use function Sentry\init;

class SentryInteraction
{
    /**
     * @var ?UserContextInterface
     */
    private ?UserContextInterface $userContext = null;

    /**
     * SentryInteraction constructor.
     *
     * @param State           $appState
     * @param ConfigInterface $omConfigInterface
     * @param Data            $sentryHelper
     */
    public function __construct(
        private State $appState,
        private ConfigInterface $omConfigInterface,
        private Data $sentryHelper
    ) {
    }

    /**
     * Initialize Sentry with passed config.
     *
     * @param array $config Sentry config @see: https://docs.sentry.io/platforms/php/configuration/
     *
     * @return void
     */
    public function initialize($config): void
    {
        init($config);
    }

    /**
     * Check if we might be able to get user context.
     */
    public function canGetUserContext(): bool
    {
        try {
            // @phpcs:ignore Generic.PHP.NoSilencedErrors
            return in_array(@$this->appState->getAreaCode(), [Area::AREA_ADMINHTML, Area::AREA_FRONTEND, Area::AREA_WEBAPI_REST, Area::AREA_WEBAPI_SOAP, Area::AREA_GRAPHQL]);
        } catch (LocalizedException $ex) {
            return false;
        }
    }

    /**
     * Get a class instance, but only if it is already available within the objectManager.
     *
     * @param string $class The classname of the type you want from the objectManager.
     */
    public function getObjectIfInitialized($class): ?object
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $reflectionClass = new ReflectionClass($objectManager);
        $sharedInstances = $reflectionClass->getProperty('_sharedInstances');
        $sharedInstances->setAccessible(true);
        $class = $this->omConfigInterface->getPreference($class);

        if (!array_key_exists(ltrim($class, '\\'), $sharedInstances->getValue($objectManager))) {
            return null;
        }

        return $objectManager->get($class);
    }

    /**
     * Attempt to get userContext from the objectManager, so we don't request it too early.
     */
    public function getUserContext(): ?UserContextInterface
    {
        if ($this->userContext) {
            return $this->userContext;
        }

        return $this->userContext = $this->getObjectIfInitialized(UserContextInterface::class);
    }

    /**
     * Check if we might be able to get the user data.
     */
    public function canGetUserData(): bool
    {
        try {
            // @phpcs:ignore Generic.PHP.NoSilencedErrors
            return in_array(@$this->appState->getAreaCode(), [Area::AREA_ADMINHTML, Area::AREA_FRONTEND]);
        } catch (LocalizedException $ex) {
            return false;
        }
    }

    /**
     * Attempt to get userdata from the current session.
     */
    private function getSessionUserData(): array
    {
        if (!$this->canGetUserData()) {
            return [];
        }

        if ($this->appState->getAreaCode() === Area::AREA_ADMINHTML) {
            $adminSession = $this->getObjectIfInitialized(AdminSession::class);
            if ($adminSession === null) {
                return [];
            }

            if ($adminSession->isLoggedIn()) {
                return [
                    'id'        => $adminSession->getUser()->getId(),
                    'email'     => $adminSession->getUser()->getEmail(),
                    'user_type' => UserContextInterface::USER_TYPE_ADMIN,
                ];
            }
        }

        if ($this->appState->getAreaCode() === Area::AREA_FRONTEND) {
            $customerSession = $this->getObjectIfInitialized(CustomerSession::class);
            if ($customerSession === null) {
                return [];
            }

            if ($customerSession->isLoggedIn()) {
                return [
                    'id'         => $customerSession->getCustomer()->getId(),
                    'email'      => $customerSession->getCustomer()->getEmail(),
                    'website_id' => $customerSession->getCustomer()->getWebsiteId(),
                    'store_id'   => $customerSession->getCustomer()->getStoreId(),
                    'user_type'  => UserContextInterface::USER_TYPE_CUSTOMER,
                ];
            }
        }

        return [];
    }

    /**
     * Attempt to add the user context to the exception.
     */
    public function addUserContext(): void
    {
        $userId = null;
        $userType = null;
        $userData = [];

        \Magento\Framework\Profiler::start('SENTRY::add_user_context');

        try {
            if ($this->canGetUserContext()) {
                $userId = $this->getUserContext()->getUserId();
                if ($userId) {
                    $userType = $this->getUserContext()->getUserType();
                }
            }

            if (count($userData = $this->getSessionUserData())) {
                $userId = $userData['id'] ?? $userId;
                $userType = $userData['user_type'] ?? $userType;
                unset($userData['user_type']);
            }

            if (!$userId) {
                return;
            }

            configureScope(function (Scope $scope) use ($userType, $userId, $userData) {
                $scope->setUser([
                    'id' => $userId,
                    ...$userData,
                    'user_type' => match ($userType) {
                        UserContextInterface::USER_TYPE_INTEGRATION => 'integration',
                        UserContextInterface::USER_TYPE_ADMIN       => 'admin',
                        UserContextInterface::USER_TYPE_CUSTOMER    => 'customer',
                        UserContextInterface::USER_TYPE_GUEST       => 'guest',
                        default                                     => 'unknown'
                    },
                ]);
            });
        } catch (\Throwable $e) {
            // User context could not be found or added.
            \Magento\Framework\Profiler::stop('SENTRY::add_user_context');

            return;
        }
        \Magento\Framework\Profiler::stop('SENTRY::add_user_context');
    }

    /**
     * Capture passed exception.
     *
     * @param \Throwable $ex
     *
     * @return void
     */
    public function captureException(\Throwable $ex): void
    {
        if (!$this->sentryHelper->shouldCaptureException($ex)) {
            return;
        }

        $this->addUserContext();

        ob_start();

        try {
            captureException($ex);
        } catch (Throwable) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        }
        ob_end_clean();
    }
}
