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

use PayrexxPaymentGateway\Service\ConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Dispatcher
{
    const PAYREXX_TRANSACTION_STATUS_CONFIRMED = 'confirmed';
    const PAYREXX_TRANSACTION_STATUS_REFUNDED = 'refunded';
    const PAYREXX_TRANSACTION_STATUS_PARTIALLY_REFUNDED = 'partially-refunded';

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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ContainerInterface $container
     * @param ConfigService $configService
     * @param type $logger
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        ConfigService $configService,
        $logger
    )
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->configService = $configService;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"storefront"})
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

        $payrexxTransaction = $content->transaction;
        $swTransactionId = $payrexxTransaction->referenceId;

        // check required data
        if (!$swTransactionId || !$payrexxTransaction->status || !$payrexxTransaction->id) {
            return new Response('Data incomplete', Response::HTTP_BAD_REQUEST);
        }

        try {
            $transactionRepo = $this->container->get('order_transaction.repository');
            $transactionDetails = $transactionRepo->search(
                (new Criteria([$swTransactionId]))->addAssociation('customFields'),
                $context
            );
            $transaction = $transactionDetails->first();

            $transactionState = $transaction->getStateMachineState()->getTechnicalName();

            $orderRepo = $this->container->get('order.repository');
            $orderDetails = $orderRepo->search(
                (new Criteria([$transaction->getOrderId()]))->addAssociation('customFields'),
                $context
            );
            $order = $orderDetails->first();
        } catch (\Payrexx\PayrexxException $e) {
            return new Response('Data incorrect', Response::HTTP_BAD_REQUEST);
        }


        $payrexxStatus = $this->checkPayrexxStatus($payrexxTransaction->id, $order->getSalesChannelId());
        if ($payrexxStatus !== $payrexxTransaction->status) {
            return new Response('Wrong Status', Response::HTTP_BAD_REQUEST);
        }

        switch ($payrexxTransaction->status) {
            case self::PAYREXX_TRANSACTION_STATUS_CONFIRMED:
                if (OrderTransactionStates::STATE_PAID === $transactionState) break;
                $this->transactionStateHandler->paid($swTransactionId, $context);
                break;
            case self::PAYREXX_TRANSACTION_STATUS_REFUNDED:
                if (OrderTransactionStates::STATE_REFUNDED === $transactionState) break;
                $this->transactionStateHandler->refund($swTransactionId, $context);
                break;
            case self::PAYREXX_TRANSACTION_STATUS_PARTIALLY_REFUNDED:
                if (OrderTransactionStates::STATE_PARTIALLY_REFUNDED === $transactionState) break;
                $this->transactionStateHandler->refundPartially($swTransactionId, $context);
                break;
        }

        return new Response('', Response::HTTP_OK);
    }

    private function checkPayrexxStatus(int $payrexxTransactionId, $salesChannelId): ?string
    {

        $config = $this->configService->getPluginConfiguration($salesChannelId);
        $payrexx = new \Payrexx\Payrexx($config['instanceName'], $config['apiKey']);

        $payrexxTransaction = new \Payrexx\Models\Request\Transaction();
        $payrexxTransaction->setId($payrexxTransactionId);

        try {
            $response = $payrexx->getOne($payrexxTransaction);
            return $response->getStatus();
        } catch(\Payrexx\PayrexxException $e) {
            return null;
        }
    }
}
