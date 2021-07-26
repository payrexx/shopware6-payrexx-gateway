<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Handler;

use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Service\ConfigService;
use PayrexxPaymentGateway\Service\PayrexxApiService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
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
        $totalAmount = $transaction->getOrderTransaction()->getAmount()->getTotalPrice();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $transactionId = $transaction->getOrderTransaction()->getId();

        $paymentMethodRepository = $this->container->get('payment_method.repository');
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter('id', $transaction->getOrderTransaction()->getPaymentMethodId())
        );
        $paymentMethod = $paymentMethodRepository->search($paymentCriteria, $salesChannelContext->getContext())->first();
        $customFields = $paymentMethod->getCustomFields();

        $paymentMethodName = '';

        if ($customFields) {
            $paymentMethodName = $customFields['payrexx_payment_method_name'] ?: $paymentMethodName;
        }

        $paymentMean = str_replace(self::PAYMENT_METHOD_PREFIX, '', $paymentMethodName);

        // Workaround if amount is 0
        if ($totalAmount <= 0) {
            $redirectUrl = $transaction->getReturnUrl();
            return new RedirectResponse($redirectUrl);
        }

        try {
            $customer = $this->customerService->getCustomerDetails($transaction->getOrder()->getOrderCustomer()->getCustomerId(), Context::createDefaultContext());
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transactionId,
                'An error occurred during processing the customer details' . PHP_EOL . $e->getMessage()
            );
        }

        // Create Payrexx Gateway link for checkout and redirect user
        try {

            $payrexxGateway = $this->payrexxApiService->createPayrexxGateway(
                $transactionId,
                $totalAmount,
                $salesChannelContext->getCurrency()->getIsoCode(),
                $paymentMean,
                $customer,
                $transaction->getReturnUrl(),
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
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();

        $customFields = $transaction->getOrderTransaction()->getCustomFields();
        $gatewayId = $customFields['gateway_id'];
        $transactionId = $transaction->getOrderTransaction()->getId();
        $totalAmount = $transaction->getOrderTransaction()->getAmount()->getTotalPrice();

        if ($totalAmount <= 0) {
            if (OrderTransactionStates::STATE_PAID === $transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName()) return;
            $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context);
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

        if (!$transaction) {
            return;
        }
        $this->transactionHandler->handleTransactionStatus($transaction->getOrderTransaction(), $payrexxTransactionStatus, $context);

        $this->transactionHandler->saveTransactionCustomFields($salesChannelContext, $transactionId, ['gateway_id' => $gatewayId]);

        if (!in_array($payrexxTransactionStatus, [Transaction::CANCELLED, Transaction::EXPIRED, Transaction::ERROR])){
            return;
        }
        throw new CustomerCanceledAsyncPaymentException(
            $transactionId,
            'Customer canceled the payment on the Payrexx page'
        );
    }
}
