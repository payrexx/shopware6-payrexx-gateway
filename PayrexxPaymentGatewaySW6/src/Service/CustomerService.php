<?php

namespace PayrexxPaymentGateway\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CustomerService
{
    /**
     * @var EntityRepository
     */
    protected $addressRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param EntityRepository $addressRepository
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepository $addressRepository, LoggerInterface $logger)
    {
        $this->addressRepository = $addressRepository;
        $this->logger = $logger;
    }

    /**
     * Returns the customer details
     *
     * @param OrderEntity $order
     * @param Context $context
     * @return array
     */
    public function getCustomerDetails(OrderEntity $order, Context $context): array
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $order->getBillingAddressId()));
            $criteria->addAssociation('country');

            /** @var OrderAddressEntity $address */
            $address = $this->addressRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        $customer = $order->getOrderCustomer();

        $delivery = $order->getDeliveries()?->first();
        if ($delivery) {
            $shippingAddress = $delivery->getShippingOrderAddress();
        }

        return [
            'forename' => $address->getFirstName(),
            'surname' => $address->getLastName(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'postcode' => $address->getZipCode(),
            'place' => $address->getCity(),
            'email' => $customer->getEmail(),
            'country' => $address->getCountry()->getIso(),
            'delivery_forename' => $shippingAddress->getFirstName() ?? $address->getFirstName(),
            'delivery_surname' => $shippingAddress->getLastName() ?? $address->getLastName(),
            'delivery_company' => $shippingAddress->getCompany() ?? $address->getCompany(),
            'delivery_street' => $shippingAddress->getStreet() ?? $address->getStreet(),
            'delivery_postcode' => $shippingAddress->getZipCode() ?? $address->getZipCode(),
            'delivery_place' => $shippingAddress->getCity() ?? $address->getCity(),
            'delivery_country' => $shippingAddress->getCountry()->getIso() ?? $address->getCountry()->getIso(),
        ];
    }
}
