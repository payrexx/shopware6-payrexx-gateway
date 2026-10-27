<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace PayrexxPaymentGateway\Webhook;

use Payrexx\Models\Response\Gateway;
use Payrexx\Models\Response\Transaction as PayrexxTransaction;
use PayrexxPaymentGateway\Handler\TransactionHandler;
use PayrexxPaymentGateway\Service\ConfigService;
use PayrexxPaymentGateway\Service\PayrexxApiService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class Dispatcher
{

    protected OrderTransactionStateHandler $transactionStateHandler;
    protected ContainerInterface $container;
    protected ConfigService $configService;
    protected PayrexxApiService $payrexxApiService;
    protected TransactionHandler $transactionHandler;
    protected LoggerInterface $logger;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        ConfigService $configService,
        PayrexxApiService $payrexxApiService,
        TransactionHandler $transactionHandler,
        LoggerInterface $logger
    )
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->configService = $configService;
        $this->payrexxApiService = $payrexxApiService;
        $this->transactionHandler = $transactionHandler;
        $this->logger = $logger;
    }

    /**
     * @Route(
     *     "/payrexx-payment/webhook",
     *     name="frontend.payrexx-payment.webhook",
     *     methods={"POST"},
     *     defaults={"csrf_protected"=false}
     * )
     */
    #[Route(path: '/payrexx-payment/webhook', name: 'frontend.payrexx-payment.webhook', methods: ['POST'], defaults: ['csrf_protected' => false])]
    public function executeWebhook(Request $request, Context $context): Response
    {
        $content = json_decode($request->getContent());

        $requestTransaction = $content->transaction;
        $swOrderNumber = $requestTransaction->referenceId;
        $requestGatewayId = $requestTransaction->invoice->paymentRequestId;

        // check required data
        if (!$swOrderNumber || !$requestGatewayId || !$requestTransaction->status || !$requestTransaction->id) {
            return new Response('Data incomplete', Response::HTTP_BAD_REQUEST);
        }

        try {
            $orderRepo = $this->container->get('order.repository');
            $transactionRepo = $this->container->get('order_transaction.repository');

            // TODO: Remove if and only keep else content
            if (preg_match("/[a-z]{30,}/i", $swOrderNumber)) {
                $transactionDetails = $transactionRepo->search(
                    (new Criteria())
                        ->addFilter(new EqualsFilter('id', $swOrderNumber))
                        ->addAssociation('order')
                        ->addAssociation('stateMachineState'),
                    $context
                );
                $order = $transactionDetails->first()->getOrder();
            } else {
                $orderDetails = $orderRepo->search(
                    (new Criteria())
                        ->addFilter(new EqualsFilter('orderNumber', $swOrderNumber))
                        ->addAssociation('transactions')
                        ->addAssociation('transactions.stateMachineState'),
                    $context
                );
                $order = $orderDetails->first();
            }

            if (!$order) {
                return new Response('Order does not exists.', Response::HTTP_OK);
            }
            $transaction = false;
            foreach($order->getTransactions() as $orderTransaction) {
                // Check all transaction if has already paid
                $state = $orderTransaction->getStateMachineState();

                if ($state && $state->getTechnicalName() === OrderTransactionStates::STATE_PAID) {
                    if (!in_array($requestTransaction->status, ['refunded', 'partially-refunded'])) {
                        return new Response('Already Paid State', Response::HTTP_OK);
                    }
                }
                $savedGatewayIds = explode(',', (string) $orderTransaction->getCustomFields()['gateway_id']);
                if (in_array($requestGatewayId, $savedGatewayIds)) {
                    $transaction = $orderTransaction;
                    break;
                }
            }

        } catch (\Payrexx\PayrexxException $e) {
            return new Response('Data incorrect', Response::HTTP_BAD_REQUEST);
        }

        // Validate request by gateway ID
        if (!$transaction) {
            return new Response(
                'Validation: Gateway ID not found in shopware. Transaction might be unsuccessful.',
                Response::HTTP_OK
            );
        }

        $salesChannelId = $order->getSalesChannelId();
        $payrexxTransaction = $this->payrexxApiService->getPayrexxTransaction(
            (int) $requestTransaction->id,
            $salesChannelId
        );
        // Only a failed lookup is worth a retry, a non-2xx response makes Payrexx send the webhook again
        if (!$payrexxTransaction) {
            return new Response('Transaction could not be loaded from Payrexx', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Validate request by status
        if ($payrexxTransaction->getStatus() !== $requestTransaction->status) {
            return new Response('Validation: Status Mismatch', Response::HTTP_BAD_REQUEST);
        }

        // Webhook is unsigned: trust the posted order and gateway only once the fetched transaction confirms them
        if ((string) $payrexxTransaction->getReferenceId() !== (string) $swOrderNumber) {
            $this->logger->warning(
                'Payrexx webhook: transaction ' . $payrexxTransaction->getId()
                . ' does not belong to order ' . $swOrderNumber
            );
            return new Response('Validation: Reference Mismatch', Response::HTTP_BAD_REQUEST);
        }

        // The transaction API does not expose the gateway id, so the gateway is asked for its transactions instead
        $payrexxGateway = $this->payrexxApiService->getPayrexxGateway((int) $requestGatewayId, $salesChannelId);
        if (!$payrexxGateway) {
            return new Response('Gateway could not be loaded from Payrexx', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        if (!$this->isTransactionOfGateway($payrexxGateway, $payrexxTransaction)) {
            $this->logger->warning(
                'Payrexx webhook: transaction ' . $payrexxTransaction->getId()
                . ' does not belong to gateway ' . $payrexxGateway->getId()
            );
            return new Response('Validation: Gateway Mismatch', Response::HTTP_BAD_REQUEST);
        }

        // A bank transfer can be paid in parts, so only the sum of all confirmed transactions settles the order.
        // An overpayment still settles it, otherwise the order would stay open since the webhook is not resent.
        $orderAmount = (int) round($transaction->getAmount()->getTotalPrice() * 100);
        if (
            $payrexxTransaction->getStatus() === PayrexxTransaction::CONFIRMED
            && $this->getConfirmedAmount($payrexxGateway) < $orderAmount
        ) {
            $this->logger->warning(
                'Payrexx webhook: confirmed amount of gateway ' . $payrexxGateway->getId()
                . ' is lower than the total of order ' . $swOrderNumber
            );
            return new Response('Validation: Amount Mismatch', Response::HTTP_OK);
        }

        $this->transactionHandler->handleTransactionStatus($transaction, $payrexxTransaction->getStatus(), $context);

        return new Response('', Response::HTTP_OK);
    }

    private function isTransactionOfGateway(Gateway $gateway, PayrexxTransaction $transaction): bool
    {
        foreach ($gateway->getInvoices() ?? [] as $invoice) {
            foreach ($invoice['transactions'] ?? [] as $gatewayTransaction) {
                if ((int) ($gatewayTransaction['id'] ?? 0) === (int) $transaction->getId()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getConfirmedAmount(Gateway $gateway): int
    {
        $amount = 0;
        foreach ($gateway->getInvoices() ?? [] as $invoice) {
            foreach ($invoice['transactions'] ?? [] as $gatewayTransaction) {
                if (($gatewayTransaction['status'] ?? '') === PayrexxTransaction::CONFIRMED) {
                    $amount += (int) ($gatewayTransaction['amount'] ?? 0);
                }
            }
        }

        return $amount;
    }
}
