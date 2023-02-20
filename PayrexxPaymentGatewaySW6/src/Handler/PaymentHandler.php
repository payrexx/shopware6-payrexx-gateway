<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Handler;

use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Service\ConfigService;
use PayrexxPaymentGateway\Service\PayrexxApiService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use PayrexxPaymentGateway\Service\CustomerService;
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

class PaymentHandler implements AsynchronousPaymentHandlerInterface
{

    const PAYMENT_METHOD_PREFIX = 'payrexx_payment_';
    const BASE_URL = 'payrexx.com';

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
     * @var CustomerService
     */
    protected $customerService;

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
     * @param CustomerService $customerService
     * @param PayrexxApiService $payrexxApiService
     * @param TransactionHandler $transactionHandler
     * @param ConfigService $configService
     * @param type $logger
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        CustomerService $customerService,
        PayrexxApiService $payrexxApiService,
        TransactionHandler $transactionHandler,
        ConfigService $configService,
        $logger
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->customerService = $customerService;
        $this->payrexxApiService = $payrexxApiService;
        $this->transactionHandler = $transactionHandler;
        $this->configService = $configService;
        $this->logger = $logger;
    }

    /**
     * Redirects to the payment page
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     * @throws AsyncPaymentProcessException
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $orderTransaction = $transaction->getOrderTransaction();
        $order = $transaction->getOrder();
        $totalAmount = $orderTransaction->getAmount()->getTotalPrice();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $transactionId = $orderTransaction->getId();

        $paymentMethodRepo = $this->container->get('payment_method.repository');
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter('id', $orderTransaction->getPaymentMethodId())
        );
        $paymentMethod = $paymentMethodRepo->search($paymentCriteria, $salesChannelContext->getContext())->first();
        $customFields = isset($paymentMethod->getTranslated()['customFields']) ? $paymentMethod->getTranslated()['customFields'] : [];

        $paymentMethodName = '';

        if (isset($customFields['payrexx_payment_method_name'])) {
            $paymentMethodName = $customFields['payrexx_payment_method_name'] ?: $paymentMethodName;
        }

        $paymentMean = str_replace(self::PAYMENT_METHOD_PREFIX, '', $paymentMethodName);

        $basket = $this->collectBasketData($order, $totalAmount, $salesChannelContext);
        $averageVatRate = $this->getAverageTaxRate($order);

        // Workaround if amount is 0
        if ($totalAmount <= 0) {
            $redirectUrl = $transaction->getReturnUrl();
            return new RedirectResponse($redirectUrl);
        }

        try {
            $customer = $this->customerService->getCustomerDetails($order, Context::createDefaultContext());
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transactionId,
                'An error occurred while processing the customer details' . PHP_EOL . $e->getMessage()
            );
        }

        // Create Payrexx Gateway link for checkout and redirect user
        try {
            $payrexxGateway = $this->payrexxApiService->createPayrexxGateway(
                $order->getOrderNumber(),
                $totalAmount,
                $averageVatRate,
                $salesChannelContext->getCurrency()->getIsoCode(),
                $paymentMean,
                $customer,
                $transaction->getReturnUrl(),
                $basket,
                $salesChannelId
            );

            $this->transactionHandler->saveTransactionCustomFields($salesChannelContext, $transactionId, ['gateway_id' => $payrexxGateway->getId()]);
            $redirectUrl = $payrexxGateway->getLink();
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transactionId,
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     * @throws CustomerCanceledAsyncPaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $shopwareTransaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();

        $orderTransaction = $shopwareTransaction->getOrderTransaction();
        $customFields = $orderTransaction->getCustomFields();
        $gatewayId = $customFields['gateway_id'];
        $transactionId = $orderTransaction->getId();
        $totalAmount = $orderTransaction->getAmount()->getTotalPrice();

        if ($totalAmount <= 0) {
            if (OrderTransactionStates::STATE_PAID === $orderTransaction->getStateMachineState()->getTechnicalName()) return;
            $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
            return;
        }

        if (empty($gatewayId)) {
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the Payrexx page'
            );
        }

        $payrexxGateway = $this->payrexxApiService->getPayrexxGateway($gatewayId, $salesChannelContext->getSalesChannel()->getId());
        $payrexxTransaction = $this->payrexxApiService->getTransactionByGateway($payrexxGateway, $salesChannelContext->getSalesChannel()->getId());

        if (!$payrexxTransaction && $totalAmount > 0) {
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the Payrexx page'
            );
        }

        $payrexxTransactionStatus = $payrexxTransaction->getStatus();
        if ($totalAmount <= 0) {
            $payrexxTransactionStatus = Transaction::CONFIRMED;
        }

        if (!$shopwareTransaction) {
            return;
        }
        $this->transactionHandler->handleTransactionStatus($orderTransaction, $payrexxTransactionStatus, $context);

        if (!in_array($payrexxTransactionStatus, [Transaction::CANCELLED, Transaction::DECLINED, Transaction::EXPIRED, Transaction::ERROR])){
            return;
        }
        throw new CustomerCanceledAsyncPaymentException(
            $transactionId,
            'Customer canceled the payment on the Payrexx page'
        );
    }

    private function collectBasketData(OrderEntity $order, $totalAmount, $salesChannelContext):array
    {
        // Collect basket data
        $basketTotal = 0;
        $basket = [];

        $lineItemElements = [];
        if ($order->getLineItems()) {
            $lineItemElements = $order->getLineItems()->getElements();
        }
        foreach ($lineItemElements as $item) {
            $unitPrice = $item->getUnitPrice();
            $quantity = $item->getQuantity();

            $basket[] = [
                'name' => $item->getLabel(),
                'description' => $item->getDescription(),
                'quantity' => $item->getQuantity(),
                'amount' => $unitPrice * 100,
                'sku' => isset($item->getPayload()['productNumber']) ? $item->getPayload()['productNumber'] : '',
            ];
            $basketTotal += $unitPrice * $quantity;
        }

        $shippingMethodRepo = $this->container->get('shipping_method.repository');

        $deliveryElements = [];
        if ($order->getDeliveries()) {
            $deliveryElements = $order->getDeliveries()->getElements();
        }
        foreach ($deliveryElements as $delivery) {
            $shippingCriteria = (new Criteria())->addFilter(
                new EqualsFilter('id', $delivery->getShippingMethodId())
            );
            $shippingMethod = $shippingMethodRepo->search($shippingCriteria, $salesChannelContext->getContext())->first();

            $unitPrice = $delivery->getShippingCosts()->getUnitPrice() ;
            $quantity = $delivery->getShippingCosts()->getQuantity();

            $basket[] = [
                'name' => $shippingMethod->getTranslated()['name'] ?: $shippingMethod->getName(),
                'description' => $shippingMethod->getTranslated()['description'] ?: $shippingMethod->getDescription(),
                'quantity' => $quantity,
                'amount' => $unitPrice * 100,
                'sku' => $shippingMethod->getId(),
            ];
            $basketTotal += $unitPrice * $quantity;
        }

        $taxElements = [];
        if ($order->getPrice() && $order->getPrice()->getCalculatedTaxes()) {
            $taxElements = $order->getPrice()->getCalculatedTaxes();
        }
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_NET) {
            foreach ($taxElements as $tax) {
                $unitPrice = $tax->getTax();
                $quantity = 1;
                $basket[] = [
                    'name' => 'Tax ' . $tax->getTaxRate() . '%',
                    'quantity' => $quantity,
                    'amount' => $unitPrice * 100,
                ];
                $basketTotal += $unitPrice;
            }
        }

        if ($totalAmount !== $basketTotal) {
            return [];
        }

        return $basket;
    }

    private function getAverageTaxRate(OrderEntity $order): float {
        if (!$order->getPrice() || !$order->getPrice()->getCalculatedTaxes()) {
            return 0;
        }

        $taxRate = 0;
        $finalTaxRate = 0;
        $taxElements = $order->getPrice()->getCalculatedTaxes();

        if (!count($taxElements)) return $finalTaxRate;
        foreach ($taxElements as $tax) {
            $taxRate += $tax->getTaxRate();
        }

        $finalTaxRate = ($taxRate / count($taxElements));

        return $finalTaxRate;
    }
}
