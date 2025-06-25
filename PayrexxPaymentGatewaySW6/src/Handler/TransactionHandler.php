<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Handler;

use Payrexx\Models\Response\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;

class TransactionHandler
{
    protected OrderTransactionStateHandler $transactionStateHandler;
    protected ContainerInterface $container;
    protected LoggerInterface $logger;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        LoggerInterface $logger
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->logger = $logger;
    }

    public function saveTransactionCustomFields(Context $context, string $transactionId, array $details): void
    {
        $transactionRepo = $this->container->get('order_transaction.repository');

        $transactionRepo->upsert([[
            'id' => $transactionId,
            'customFields' => $details
        ]], $context);
    }

    public function handleTransactionStatus(OrderTransactionEntity $orderTransaction, string $payrexxTransactionStatus, Context $context): void
    {
        $state = $orderTransaction->getStateMachineState();
        switch ($payrexxTransactionStatus) {
            case OrderTransactionStates::STATE_UNCONFIRMED:
                if ($state !== null && OrderTransactionStates::STATE_OPEN === $state->getTechnicalName()) {
                    $this->transactionStateHandler->processUnconfirmed($orderTransaction->getId(), $context);
                }
                break;
            case Transaction::CONFIRMED:
                if ($state !== null && OrderTransactionStates::STATE_PAID === $orderTransaction->getStateMachineState()->getTechnicalName()) break;
                $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
                break;
            case Transaction::WAITING:
                if ($state !== null && in_array($orderTransaction->getStateMachineState()->getTechnicalName(), [OrderTransactionStates::STATE_IN_PROGRESS, OrderTransactionStates::STATE_PAID])) break;
                $this->transactionStateHandler->reopen($orderTransaction->getId(), $context);
                break;
            case Transaction::REFUNDED:
                if ($state !== null && OrderTransactionStates::STATE_REFUNDED === $orderTransaction->getStateMachineState()->getTechnicalName()) break;
                $this->transactionStateHandler->refund($orderTransaction->getId(), $context);
                break;
            case Transaction::PARTIALLY_REFUNDED:
                if ($state !== null && OrderTransactionStates::STATE_PARTIALLY_REFUNDED === $orderTransaction->getStateMachineState()->getTechnicalName()) break;
                $this->transactionStateHandler->refundPartially($orderTransaction->getId(), $context);
                break;
            case Transaction::CANCELLED:
            case Transaction::DECLINED:
            case Transaction::EXPIRED:
                if ($state !== null && !in_array($orderTransaction->getStateMachineState()->getTechnicalName(), [OrderTransactionStates::STATE_OPEN, OrderTransactionStates::STATE_UNCONFIRMED])) {
                    break;
                }
                $this->transactionStateHandler->cancel($orderTransaction->getId(), $context);
                break;
            case Transaction::ERROR:
                if ($state !== null && !in_array($orderTransaction->getStateMachineState()->getTechnicalName(), [OrderTransactionStates::STATE_OPEN, OrderTransactionStates::STATE_UNCONFIRMED])) {
                    break;
                }
                $this->transactionStateHandler->fail($orderTransaction->getId(), $context);
                break;
        }
    }
}
