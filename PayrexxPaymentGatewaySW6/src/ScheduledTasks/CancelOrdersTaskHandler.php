<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\ScheduledTasks;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Filter\EqualsFilter;

class CancelOrdersTaskHandler extends ScheduledTaskHandler
{
    private $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function handle(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new RangeFilter('createdAt', ['lt' => new \DateTime('-30 minutes')])
        );
        $criteria->addFilter(
            new EqualsFilter('stateId', 'order_state_in_progress') // Replace with your specific state ID
        );

        $orders = $this->orderRepository->search($criteria, Context::createDefaultContext());

        foreach ($orders as $order) {
            // Update the order status to 'cancelled'
            $this->orderRepository->update([
                [
                    'id' => $order->getId(),
                    'stateId' => 'order_state_cancelled' // Replace with the actual cancelled state ID
                ]
            ], Context::createDefaultContext());
        }
    }
}