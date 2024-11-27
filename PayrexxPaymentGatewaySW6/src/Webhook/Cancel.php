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

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class Cancel
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * @Route(
     *     "/payrexx-payment/cancel",
     *     name="frontend.payrexx-payment.cancel",
     *     methods={"GET"},
     *     defaults={"csrf_protected"=false}
     * )
     *
     * @param Request $request
     * @param Context $context
     * @return Response
     */
    #[Route(path: '/payrexx-payment/cancel', name: 'frontend.payrexx-payment.cancel', methods: ['GET'], defaults: ['csrf_protected' => false])]
    public function executeCancel(Request $request, Context $context): Response
    {
        $orderRepository = $this->container->get('order.repository');
        $transactionRepo = $this->container->get('order_transaction.repository');
        $router = $this->container->get('router');

        // Get parameters from the request
        $orderNumber = $request->query->get('orderId');
        $transactionId = $request->query->get('transactionId');
        $redirectUrl = base64_decode($request->query->get('redirect'));

        // Validate input
        if (!$orderNumber || !$transactionId) {
            return new Response('Invalid request parameters.', Response::HTTP_BAD_REQUEST);
        }

        // Search for the order
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('orderNumber', $orderNumber)
        );

        $order = $orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return new Response('Order not found.', Response::HTTP_NOT_FOUND);
        }

        // Search for the transaction
        $criteria = new Criteria([$transactionId]);
        $criteria->addFilter(
            new EqualsFilter('orderId', $order->getId())
        );

        $transaction = $transactionRepo->search($criteria, $context)->first();

        if (!$transaction) {
            return new Response('Transaction not found.', Response::HTTP_NOT_FOUND);
        }

        $transactionCreatedAt = $transaction->getCreatedAt();
        $thresholdTime = new \DateTime('-10 minutes');

        if ($transactionCreatedAt && $transactionCreatedAt < $thresholdTime) {
            $redirectUrl = $router->generate(
                'frontend.account.edit-order.page',
                ['orderId' => $order->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }
        return new RedirectResponse($redirectUrl);
    }
}
