<?php

namespace PayrexxPaymentGateway\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CustomerService
{
    /**
     * @var EntityRepository 
     */
    protected $customerRepository;

    /**
     * @var LoggerInterface 
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param EntityRepository $customerRepository
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepository $customerRepository, LoggerInterface $logger)
    {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Returns the customer details
     *
     * @param string $customerId
     * @param Context $context
     * @return array
     */
    public function getCustomerDetails(string $customerId, Context $context): array
    {
        $customer = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $customerId));
            $criteria->addAssociation('activeShippingAddress');
            $criteria->addAssociation('activeBillingAddress');
            $criteria->addAssociation('defaultShippingAddress');
            $criteria->addAssociation('defaultBillingAddress');

            /** @var CustomerEntity $customer */
            $customer = $this->customerRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        $address = $customer->getDefaultBillingAddress();

        if ($address === null) {
            return [];
        }
        
        return [
            'forename' => $address->getFirstName(),
            'surname' => $address->getLastName(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'postcode' => $address->getZipCode(),
            'place' => $address->getCity(),
            'email' => $customer->getEmail(),
        ];
    }
}
