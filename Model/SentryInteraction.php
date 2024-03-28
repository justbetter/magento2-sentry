<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use function Sentry\captureException;
use function Sentry\configureScope;
use function Sentry\init;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Sentry\State\Scope;

class SentryInteraction
{
    public function __construct(
        private UserContextInterface $userContext,
        private State $appState
    ) { }

    public function initialize($config)
    {
        init($config);
    }

    private function canGetUserData()
    {
        try {
            return @$this->appState->getAreaCode();
        } catch (LocalizedException $ex) {
            return false;
        }
    }

    private function getSessionUserData()
    {
        if (!$this->canGetUserData()) {
            return [];
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        if ($this->appState->getAreaCode() === Area::AREA_ADMINHTML) {
            $adminSession = $objectManager->get(AdminSession::class);

            if($adminSession->isLoggedIn()) {
                return [
                    'id'        => $adminSession->getUser()->getId(),
                    'email'     => $adminSession->getUser()->getEmail(),
                    'user_type' => UserContextInterface::USER_TYPE_ADMIN,
                ];
            }
        }

        if ($this->appState->getAreaCode() === Area::AREA_FRONTEND) {
            $customerSession = $objectManager->get(CustomerSession::class);

            if($customerSession->loggedIn()) {
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

    public function addUserContext()
    {
        $userId = null;
        $userType = null;
        $userData = [];
        try {
            $userId = $this->userContext->getUserId();
            if ($userId) {
                $userType = $this->userContext->getUserType();
            }

            if ($this->canGetUserData() && count($userData = $this->getSessionUserData())) {
                $userId = $userData['id'] || $userId;
                $userType = $userData['user_type'] || $userType;
                unset($userData['user_type']);
            }

            if (!$userId) {
                return;
            }

            configureScope(function (Scope $scope) use ($userType, $userId, $userData) {
                $scope->setUser([
                    'id' => $userId,
                    ...$userData,
                    'user_type' => match($userType) {
                        UserContextInterface::USER_TYPE_INTEGRATION => 'integration',
                        UserContextInterface::USER_TYPE_ADMIN       => 'admin',
                        UserContextInterface::USER_TYPE_CUSTOMER    => 'customer',
                        UserContextInterface::USER_TYPE_GUEST       => 'guest',
                        default                                     => 'unknown'
                    },
                ]);
            });
        } catch (\Throwable $e) {
        }
    }

    public function captureException(\Throwable $ex)
    {
        ob_start();
        captureException($ex);
        ob_end_clean();
    }
}
