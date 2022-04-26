<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Handler;

use Payrexx\Models\Response\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;

class TransactionHandler
{
    /**
     * @var OrderTransactionStateHandler
     */
    protected $transactionStateHandler;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param OrderTransactionStateHandler $transactionStateHandler
     * * @param ContainerInterface $container
     * @param type $logger
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        $logger
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * @param $payrexxGateway
     * @param $salesChannelContext
     * @param $transactionId
     */
    public function saveTransactionCustomFields($salesChannelContext, $transactionId, $details)
    {
        $transactionRepo = $this->container->get('order_transaction.repository');
        $transactionRepo->upsert([[
            'id' => $transactionId,
            'customFields' => $details
        ]], $salesChannelContext->getContext());
    }

    /**
     * @param OrderTransactionEntity $transaction
     * @param $payrexxTransactionStatus
     * @param Context $context
     */
    public function handleTransactionStatus(OrderTransactionEntity $orderTransaction, string $payrexxTransactionStatus, Context $context)
    {
        switch ($payrexxTransactionStatus) {
            case Transaction::CONFIRMED:
                if (OrderTransactionStates::STATE_PAID === $orderTransaction->getStateMachineState()->getTechnicalName()) break;
                $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
                break;
            case Transaction::WAITING:
                if (in_array($orderTransaction->getStateMachineState()->getTechnicalName(), [OrderTransactionStates::STATE_IN_PROGRESS, OrderTransactionStates::STATE_PAID])) break;
                $this->transactionStateHandler->process($orderTransaction->getId(), $context);
                break;
            case Transaction::REFUNDED:
                if (OrderTransactionStates::STATE_REFUNDED === $orderTransaction->getStateMachineState()->getTechnicalName()) break;
                $this->transactionStateHandler->refund($orderTransaction->getId(), $context);
                break;
            case Transaction::PARTIALLY_REFUNDED:
                if (OrderTransactionStates::STATE_PARTIALLY_REFUNDED === $orderTransaction->getStateMachineState()->getTechnicalName()) break;
                $this->transactionStateHandler->refundPartially($orderTransaction->getId(), $context);
                break;
            case Transaction::CANCELLED:
            case Transaction::DECLINED:
            case Transaction::EXPIRED:
                if (in_array($orderTransaction->getStateMachineState()->getTechnicalName(), [OrderTransactionStates::STATE_CANCELLED, OrderTransactionStates::STATE_PAID])) break;
                $this->transactionStateHandler->cancel($orderTransaction->getId(), $context);
                break;
            case Transaction::ERROR:
                if (in_array($orderTransaction->getStateMachineState()->getTechnicalName(), [OrderTransactionStates::STATE_FAILED, OrderTransactionStates::STATE_PAID])) break;
                $this->transactionStateHandler->fail($orderTransaction->getId(), $context);
                break;
        }
    }
}
