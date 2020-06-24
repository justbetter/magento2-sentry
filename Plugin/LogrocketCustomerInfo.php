<?php

namespace JustBetter\Sentry\Plugin;

use Magento\Customer\CustomerData\Customer;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Customer\Model\Session;

class LogrocketCustomerInfo
{
    protected $currentCustomer;
    protected $customerSession;

    public function __construct(
        CurrentCustomer $currentCustomer,
        Session $session
    ) {
        $this->currentCustomer = $currentCustomer;
        $this->customerSession = $session;
    }

    public function afterGetSectionData(Customer $subject, $result)
    {
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->currentCustomer->getCustomer();

            $result['email'] = $customer->getEmail();
            $result['fullname'] = $customer->getFirstname().' '.$customer->getLastname();
        }

        return $result;
    }
}
