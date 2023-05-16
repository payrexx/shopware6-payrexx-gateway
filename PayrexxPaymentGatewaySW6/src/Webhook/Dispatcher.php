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
class Dispatcher
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
     * @var ConfigService
     */
    protected $configService;

    /**
     * @var PayrexxApiService
     */
    protected $payrexxApiService;

    /**
     * @var TransactionHandler
     */
    protected $transactionHandler;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ContainerInterface $container
     * @param ConfigService $configService
     * @param PayrexxApiService $payrexxApiService
     * @param TransactionHandler $transactionHandler
     * @param type $logger
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        ConfigService $configService,
        PayrexxApiService $payrexxApiService,
        TransactionHandler $transactionHandler,
        $logger
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
     *
     * @param Request $request
     * @param Context $context
     * @return Response
     */
    public function executeWebhook(Request $request, Context $context): Response
    {
        $content = json_decode($request->getContent());

        $requestTransaction = $content->transaction;
        $swOrderNumber = $requestTransaction->referenceId;
        $requestGatewayId = $requestTransaction->invoice->paymentRequestId;

        // check required data
        if (!$swOrderNumber || !$requestTransaction->status || !$requestTransaction->id) {
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
                        ->addAssociation('order'),
                    $context
                );
                $transaction = $transactionDetails->first();
                $order = $transaction->getOrder();
            } else {
                $orderDetails = $orderRepo->search(
                    (new Criteria())
                        ->addFilter(new EqualsFilter('orderNumber', $swOrderNumber))
                        ->addAssociation('transactions'),
                    $context
                );
                $order = $orderDetails->first();
                $transaction = $order->getTransactions()->first();
            }


        } catch (\Payrexx\PayrexxException $e) {
            return new Response('Data incorrect', Response::HTTP_BAD_REQUEST);
        }

        // Validate request by gateway ID
        if ($transaction->getCustomFields()['gateway_id'] != $requestGatewayId) {
            return new Response('Malicious request', Response::HTTP_BAD_REQUEST);
        }

        // Validate request by status
        $payrexxTransaction = $this->payrexxApiService->getPayrexxTransaction($requestTransaction->id, $order->getSalesChannelId());
        if ($payrexxTransaction->getStatus() !== $requestTransaction->status) {
            return new Response('Malicious request', Response::HTTP_BAD_REQUEST);
        }

        $this->transactionHandler->handleTransactionStatus($transaction, $requestTransaction->status, $context);

        return new Response('', Response::HTTP_OK);
    }
}
