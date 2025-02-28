<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Handler;

use Exception;
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
use Shopware\Core\Checkout\Payment\PaymentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

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
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ContainerInterface $container
     * @param CustomerService $customerService
     * @param PayrexxApiService $payrexxApiService
     * @param TransactionHandler $transactionHandler
     * @param ConfigService $configService
     * @param LoggerInterface $logger
     * @param RouterInterface $router
     * @param RequestStack $requestStack
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        CustomerService $customerService,
        PayrexxApiService $payrexxApiService,
        TransactionHandler $transactionHandler,
        ConfigService $configService,
        LoggerInterface $logger,
        RouterInterface $router,
        RequestStack $requestStack
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->customerService = $customerService;
        $this->payrexxApiService = $payrexxApiService;
        $this->transactionHandler = $transactionHandler;
        $this->configService = $configService;
        $this->logger = $logger;
        $this->router = $router;
        $this->requestStack = $requestStack;
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

        // Workaround if amount is 0
        if ($totalAmount <= 0) {
            $redirectUrl = $transaction->getReturnUrl();
            return new RedirectResponse($redirectUrl);
        }

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

        $basket = $this->collectBasketData($order, $salesChannelContext);
        $purpose = $this->createPurposeByBasket($basket);
        $basketAmount = $this->getBasketAmount($basket);
        if ($totalAmount !== $basketAmount) {
            $basket = [];
        }

        $averageVatRate = $this->getAverageTaxRate($order);
        try {
            $billingAndShippingDetails = $this->customerService->getBillingAndShippingDetails($order, Context::createDefaultContext());
        } catch (\Exception $e) {
            $message = 'An error occurred while processing the customer details' . PHP_EOL . $e->getMessage();
            $this->customAsyncException($transactionId, $message);
        }

        // Delete gateway from all transactions.
        foreach($order->getTransactions() as $transactionGateway) {
            $oldGatewayId = $transactionGateway->getCustomFields()['gateway_id'] ?? '';
            if ($oldGatewayId) {
                $gatewayStatus = $this->payrexxApiService->deletePayrexxGateway(
                    $salesChannelId,
                    (int) $oldGatewayId
                );
                if ($gatewayStatus) {
                    $this->transactionHandler->saveTransactionCustomFields(
                        $salesChannelContext,
                        $transactionGateway->getId(),
                        [
                            'gateway_id' => '',
                        ]
                    );
                }
            }
        }

        if (in_array($paymentMean, ['sofortueberweisung_de', 'postfinance_card', 'postfinance_efinance'])) {
            throw new Exception('Unavailable payment method error');
        }

        $returnUrl = $this->createReturnUrl(
            $transaction,
            $order->getOrderNumber(),
            $transactionId
        );
        // Create Payrexx Gateway link for checkout and redirect user
        try {
            $payrexxGateway = $this->payrexxApiService->createPayrexxGateway(
                $order->getOrderNumber(),
                $totalAmount,
                $averageVatRate,
                $salesChannelContext->getCurrency()->getIsoCode(),
                $paymentMean,
                $billingAndShippingDetails,
                $returnUrl,
                $basket,
                $salesChannelId,
                $purpose
            );

            if (!$payrexxGateway) {
                throw new Exception('Gateway creation error');
            }
            $this->transactionHandler->saveTransactionCustomFields($salesChannelContext, $transactionId, ['gateway_id' => $payrexxGateway->getId()]);
            $this->transactionHandler->handleTransactionStatus(
                $orderTransaction,
                OrderTransactionStates::STATE_UNCONFIRMED,
                $salesChannelContext->getContext()
            );
            $redirectUrl = $payrexxGateway->getLink();
        } catch (\Exception $e) {
            $message = 'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage();
            $this->customAsyncException($transactionId, $message);
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     * @throws PaymentException|CustomerCanceledAsyncPaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $shopwareTransaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        $orderTransaction = $shopwareTransaction->getOrderTransaction();
        $transactionId = $orderTransaction->getId();
        $totalAmount = $orderTransaction->getAmount()->getTotalPrice();

        if ($totalAmount <= 0) {
            if ($orderTransaction->getStateMachineState() &&
                OrderTransactionStates::STATE_PAID === $orderTransaction->getStateMachineState()->getTechnicalName()
            ) {
                return;
            }
            $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
            return;
        }

        $customFields = $orderTransaction->getCustomFields();
        $gatewayId = $customFields['gateway_id'] ?? '';
        if (empty($gatewayId)) {
            $this->customCustomerException($transactionId, 'Customer canceled the payment on the Payrexx page');
        }

        $gatewayIds = explode(',', (string) $gatewayId); // TODO: later remove explode.
        $gatewayId = current($gatewayIds);
        $payrexxGateway = $this->payrexxApiService->getPayrexxGateway($gatewayId, $salesChannelId);
        $payrexxTransaction = $this->payrexxApiService->getTransactionByGateway($payrexxGateway, $salesChannelId);

        if (!$payrexxTransaction && $totalAmount > 0) {
            if ($gatewayId) {
                $this->payrexxApiService->deletePayrexxGateway($salesChannelId, (int) $gatewayId);
            }
            $this->customCustomerException($transactionId, 'Customer canceled the payment on the Payrexx page');
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
        $this->customCustomerException($transactionId, 'Customer canceled the payment on the Payrexx page');
    }

    private function collectBasketData(OrderEntity $order, $salesChannelContext):array
    {
        // Collect basket data
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
            }
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

    /**
     * Get total amount of basket items
     *
     * @param $basket
     * @return float
     */
    private function getBasketAmount($basket): float
    {
        $basketAmount = 0;

        foreach ($basket as $product) {
            $amount = $product['amount'] / 100;
            $basketAmount += $product['quantity'] * $amount;
        }
        return floatval($basketAmount);
    }

    /**
     * Create purpose from basket items
     *
     * @param array $basket
     * @return string
     */
    public static function createPurposeByBasket($basket): string
    {
        $desc = [];
        foreach ($basket as $product) {
            $desc[] = implode(' ', [
                $product['name'],
                $product['quantity'],
                'x',
                number_format($product['amount'] / 100, 2, '.'),
            ]);
        }
        return implode('; ', $desc);
    }

    /**
     * @param int $transactionId
     * @param string $message
     * @throws AsyncPaymentProcessException|PaymentException
     */
    public function customAsyncException($transactionId, $message)
    {
        if (class_exists(AsyncPaymentProcessException::class)) {
            throw new AsyncPaymentProcessException($transactionId, $message);
        } else {
            // support from shopware 6.6
            throw PaymentException::asyncProcessInterrupted($transactionId, $message);
        }
    }

    /**
     * @param int $transactionId
     * @param string $message
     * @throws AsyncPaymentProcessException|PaymentException
     */
    public function customCustomerException($transactionId, $message)
    {
        if (class_exists(CustomerCanceledAsyncPaymentException::class)) {
            throw new CustomerCanceledAsyncPaymentException($transactionId, $message);
        } else {
            // support from shopware 6.6
            throw PaymentException::customerCanceled($transactionId, $message);
        }
    }

    /**
     * Build return url
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param string $orderNumber
     * @param string $transactionId
     * @return array
     */
    private function createReturnUrl(
        AsyncPaymentTransactionStruct $transaction,
        string $orderNumber,
        string $transactionId
    ): array {
        $returnUrl = $transaction->getReturnUrl();
        $request = $this->requestStack->getCurrentRequest();
        $urlPath = $request ? $request->getPathInfo() : '';

        // Check if the request comes from Store API (headless frontend)
        $isStoreApiRequest = str_starts_with($urlPath, '/store-api');

        return [
            'success' => $returnUrl,
            'cancel' => $isStoreApiRequest
                ? $returnUrl
                : $this->router->generate(
                    'frontend.payrexx-payment.cancel',
                    [
                        'orderId' => $orderNumber,
                        'transactionId' => $transactionId,
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
        ];
    }
}
