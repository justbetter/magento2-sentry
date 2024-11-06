<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use ReflectionClass;
use Sentry\State\Scope;

use function Sentry\captureException;
use function Sentry\configureScope;
use function Sentry\init;

class SentryInteraction
{
    /**
     * SentryInteraction constructor.
     *
     * @param UserContextInterface $userContext
     * @param State                $appState
     */
    public function __construct(
        private UserContextInterface $userContext,
        private State $appState
    ) {
    }

    /**
     * Initialize Sentry with passed config.
     *
     * @param array $config Sentry config @see: https://docs.sentry.io/platforms/php/configuration/
     *
     * @return void
     */
    public function initialize($config)
    {
        init($config);
    }

    /**
     * Check if we might be able to get the user data.
     */
    private function canGetUserData()
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
    private function getSessionUserData()
    {
        if (!$this->canGetUserData()) {
            return [];
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $reflectionClass = new ReflectionClass($objectManager);
        $sharedInstances = $reflectionClass->getProperty('_sharedInstances');
        $sharedInstances->setAccessible(true);

        if ($this->appState->getAreaCode() === Area::AREA_ADMINHTML) {
            if (!array_key_exists(ltrim(AdminSession::class, '\\'), $sharedInstances->getValue($objectManager))) {
                // Don't intitialise session if it has not already been started, this causes problems with dynamic resources.
                return [];
            }
            $adminSession = $objectManager->get(AdminSession::class);

            if ($adminSession->isLoggedIn()) {
                return [
                    'id'        => $adminSession->getUser()->getId(),
                    'email'     => $adminSession->getUser()->getEmail(),
                    'user_type' => UserContextInterface::USER_TYPE_ADMIN,
                ];
            }
        }

        if ($this->appState->getAreaCode() === Area::AREA_FRONTEND) {
            if (!array_key_exists(ltrim(CustomerSession::class, '\\'), $sharedInstances->getValue($objectManager))) {
                return [];
            }
            $customerSession = $objectManager->get(CustomerSession::class);

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
    public function addUserContext()
    {
        $userId = null;
        $userType = null;
        $userData = [];

        \Magento\Framework\Profiler::start('SENTRY::add_user_context');

        try {
            $userId = $this->userContext->getUserId();
            if ($userId) {
                $userType = $this->userContext->getUserType();
            }

            if ($this->canGetUserData() && count($userData = $this->getSessionUserData())) {
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
    public function captureException(\Throwable $ex)
    {
        ob_start();
        captureException($ex);
        ob_end_clean();
    }
}
