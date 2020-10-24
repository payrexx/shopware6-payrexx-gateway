<?php declare(strict_types=1);

namespace PayrexxPaymentGateway\Subscriber;

use PayrexxPaymentGateway\Handler\PaymentHandler;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BackendSubscriber implements EventSubscriberInterface
{
    private $container;
    private $paymentHandler;

    public function __construct(ContainerInterface $container, PaymentHandler $paymentHandler)
    {
        $this->container = $container;
        $this->paymentHandler = $paymentHandler;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'onUpdateOrder'
        ];
    }

    public function onUpdateOrder(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        foreach ($event->getWriteResults() as $writeResult) {

            $payload = $writeResult->getPayload();

            /** @var EntityRepositoryInterface $stateRepository */
            $deliveryRepository = $this->container->get('order_delivery.repository');
            try {
                $deliveryResult = $deliveryRepository->search(
                    (new Criteria([$payload['id']]))->addAssociation('order', 'stateMachineState'),
                    $context
                );
            } catch (InconsistentCriteriaIdsException $e) {
            }

            /** @var OrderDeliveryEntity|null $deliveryInfo */
            $deliveryInfo =  $deliveryResult->first();
            if (!($deliveryInfo instanceof OrderDeliveryEntity)) {
                continue;
            }

            $deliveryState = $deliveryInfo->getStateMachineState();

            if($deliveryState){
                $orderID = $deliveryInfo->getOrder()->getId();

                if($orderID){
                    /** @var EntityRepositoryInterface $orderRepo */
                    $orderRepo = $this->container->get('order.repository');
                    /** @var EntitySearchResult $orderR */
                    $orderResult = $orderRepo->search(
                        (new Criteria([$orderID]))->addAssociation('transactions.paymentMethod', 'transactions.customFields'),
                        $context
                    );

                    /** @var OrderEntity|null $order */
                    $order = $orderResult->first();
                    if (!($order instanceof OrderEntity)) {
                        continue;
                    }
                    $salesChannelId = $order->getSalesChannelId();

                    $transaction = $order->getTransactions()->first();

                    if($transaction && $transaction->getId()){

                        $transactionId = $transaction->getId();
                        $paymentMethod = $transaction->getPaymentMethod();
                        $paymentCustomFileds = $paymentMethod->getCustomFields();

                        if($deliveryState->getTechnicalName() == 'shipped' && $paymentCustomFileds && strpos($paymentCustomFileds['payrexx_payment_method_name'], "payrexx") !== false){

                            $transactionDetail =  $this->paymentHandler->getPayrexxTransactionDetails($transactionId, $context, $salesChannelId);

                            if ($transactionDetail->getStatus() == 'uncaptured') {
                                $status = $this->paymentHandler->captureTransaction($transactionDetail->getId(), $salesChannelId);
                                //var_dump($status)
                            }
                        }
                    }
                }
            }
        }
    }
}
