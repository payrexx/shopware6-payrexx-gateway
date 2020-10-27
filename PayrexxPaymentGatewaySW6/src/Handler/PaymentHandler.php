<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway\Handler;

use PayrexxPaymentGateway\Service\ConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use PayrexxPaymentGateway\Service\CustomerService;
use Symfony\Component\HttpFoundation\Session\Session;
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

    const PAYMENT_MEAN_PREFIX = 'payrexx_payment_';
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ContainerInterface $container
     * @param CustomerService $customerService
     * @param ConfigService $configService
     * @param type $logger
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        ContainerInterface $container,
        CustomerService $customerService,
        ConfigService $configService,
        $logger
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->container = $container;
        $this->customerService = $customerService;
        $this->configService = $configService;
        $this->logger = $logger;

        $this->session = new Session();
        if (!isset($_SESSION)) {
            $this->session->start();
        }
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

        // Workaround if amount is 0
        if ($totalAmount <= 0) {
            $this->session->set('payrexxPayment/gatewayId', time());
            $redirectUrl = $transaction->getReturnUrl();
        } else {
            try {
                $customer = $this->customerService->getCustomerDetails($transaction->getOrder()->getOrderCustomer()->getCustomerId(), Context::createDefaultContext());
            } catch (\Exception $e) {
                throw new AsyncPaymentProcessException(
                    $transaction->getOrderTransaction()->getId(),
                    'An error occurred during the processing the customer details' . PHP_EOL . $e->getMessage()
                );
            }

            // Create Payrexx Gateway link for checkout and redirect user
            try {
                $customFields = $transaction->getOrderTransaction()->getPaymentMethod()->getCustomFields();
                $paymentMean = str_replace(self::PAYMENT_MEAN_PREFIX, '', $customFields['payrexx_payment_method_name']);

                $payrexxGateway = $this->createPayrexxGateway(
                    $transaction->getOrderTransaction()->getId(),
                    $totalAmount,
                    $salesChannelContext->getCurrency()->getIsoCode(),
                    $paymentMean,
                    $customer,
                    [
                        'successUrl' => $transaction->getReturnUrl(),
                        'errorUrl' => $transaction->getReturnUrl(),
                    ],
                    $salesChannelId
                );

                $this->session->set('payrexxPayment/gatewayId', $payrexxGateway->getId());
                $redirectUrl = $this->getProviderUrl($salesChannelId) . '?payment=' . $payrexxGateway->getHash();
            } catch (\Exception $e) {
                throw new AsyncPaymentProcessException(
                    $transaction->getOrderTransaction()->getId(),
                    'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
                );
            }
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
        $gatewayId = $this->session->get('payrexxPayment/gatewayId');
        $transactionId = $transaction->getOrderTransaction()->getId();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        if (empty($gatewayId)) {
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the Payrexx page'
            );
        }
        $context = $salesChannelContext->getContext();
        $totalAmount = $transaction->getOrderTransaction()->getAmount()->getTotalPrice();
        if ($totalAmount > 0 ? $this->checkPayrexxGatewayStatus($gatewayId, $salesChannelId) : true) {
            $this->saveTransactionDetails($gatewayId, $salesChannelContext, $transactionId);

            if (OrderTransactionStates::STATE_PAID === $transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName()) return;
            $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context);
            $this->session->remove('payrexxPayment/gatewayId');
            return;
        }
        throw new CustomerCanceledAsyncPaymentException(
            $transactionId,
            'Customer canceled the payment on the Payrexx page'
        );
    }

    /**
     * Get the base URL for the Payrexx Gateway checkout page by instanceName setting
     *
     * @return string
     */
    private function getProviderUrl(string $salesChannelId): string
    {
        $config = $this->configService->getPluginConfiguration($salesChannelId);
        return 'https://' . $config['instanceName'] . '.' . self::BASE_URL . '/';
    }

    /**
     * @return \Payrexx\Payrexx
     */
    private function getInterface($salesChannelId): \Payrexx\Payrexx
    {
        $config = $this->configService->getPluginConfiguration($salesChannelId);
        return new \Payrexx\Payrexx($config['instanceName'], $config['apiKey']);
    }

    /**
     * Create a checkout page in Payrexx (Payrexx Gateway)
     *
     * @param $orderNumber
     * @param $amount
     * @param $currency
     * @param $paymentMean
     * @param $user
     * @param $urls
     * @param string $salesChannelId
     * @return Gateway
     *
     */
    public function createPayrexxGateway(string $orderNumber, float $amount, string $currency, string $paymentMean, array $customer, array $urls, string $salesChannelId)
    {
        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setAmount($amount * 100);
        $gateway->setCurrency($currency);
        $gateway->setSuccessRedirectUrl($urls['successUrl']);
        $gateway->setFailedRedirectUrl($urls['errorUrl']);
        $gateway->setCancelRedirectUrl($urls['errorUrl']);

        $gateway->setPsp([]);
        $gateway->setPm([$paymentMean]);
        $gateway->setReferenceId($orderNumber);
        $gateway->setValidity(15);

        $gateway->addField('forename', $customer['forename']);
        $gateway->addField('surname', $customer['surname']);
        $gateway->addField('company', $customer['company']);
        $gateway->addField('street', $customer['street']);
        $gateway->addField('postcode', $customer['postcode']);
        $gateway->addField('place', $customer['place']);
        $gateway->addField('email', $customer['email']);
        $gateway->addField('custom_field_1', $orderNumber, [
            1 => 'Shopware Bestellnummer',
            2 => 'Shopware Order ID',
        ]);

        try {
            return $payrexx->create($gateway);
        } catch (\Payrexx\PayrexxException $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }
        return null;
    }

    /**
     * Check the Payrexx Gateway status whether it is paid or not
     *
     * @param $gatewayId
     * @return bool
     */
    public function checkPayrexxGatewayStatus($gatewayId, $salesChannelId): bool
    {
        if (!$gatewayId) {
            return false;
        }
        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setId($gatewayId);
        try {
            $payrexxGateway = $payrexx->getOne($gateway);
            return ($payrexxGateway->getStatus() == 'confirmed');
        } catch (\Payrexx\PayrexxException $e) {
        }
        return false;
    }

    /**
     * @param $gatewayId
     * @param string $salesChannelId
     * @return \Payrexx\Models\Request\Gateway|bool
     */
    public function getPayrexxGatewayDetails($gatewayId, string $salesChannelId)
    {
        if (!$gatewayId) {
            return false;
        }
        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setId($gatewayId);
        try {
            $payrexxGateway = $payrexx->getOne($gateway);
            return $payrexxGateway;
        } catch (\Payrexx\PayrexxException $e) {
        }
        return false;
    }

    /**
     * @param $gatewayId
     * @param $salesChannelContext
     * @param $transactionId
     */
    public function saveTransactionDetails($gatewayId, $salesChannelContext, $transactionId)
    {
        $payrexxGateway = $this->getPayrexxGatewayDetails($gatewayId, $salesChannelContext->getSalesChannel()->getId());
        if($payrexxGateway->getStatus() == 'confirmed' && $payrexxGateway){
            $invoices = $payrexxGateway->getInvoices();

            if($invoices && $invoices[0]){
                $invoice = $invoices[0];
                $transactions = $invoice['transactions'];

                if($transactions && $transactions[0]){
                    $transaction = $transactions[0];
                    $transactionRepo = $this->container->get('order_transaction.repository');
                    $transactionRepo->upsert([[
                        'id' => $transactionId,
                        'customFields' => ['transaction_ids' => $transaction['uuid'].'_'.$transaction['id']]
                    ]], $salesChannelContext->getContext());
                }
            }
        }
    }

    /**
     * @param $transactionId
     * @param $context
     * @return bool|null|\Payrexx\Models\Request\Transaction|OrderTransactionEntity|string
     */
    public function getPayrexxTransactionDetails($transactionId, $context, $salesChannelId)
    {
        if (!$transactionId) {
            return false;
        }

        $transactionRepo = $this->container->get('order_transaction.repository');
        try {
            $transactionDetails = $transactionRepo->search(
                (new Criteria([$transactionId]))->addAssociation('customFields'),
                $context
            );
        } catch (InconsistentCriteriaIdsException $e) {
            return $e->getMessage();
        }


        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $transactionDetails->first();

        if (!($transaction instanceof OrderTransactionEntity)) {
            return "No Transaction Found";
        }

        $customFields = $transaction->getCustomFields();
        if($customFields && $customFields['transaction_ids']){
            $transactionIDs = explode("_",$customFields['transaction_ids']);
            if(is_array($transactionIDs) && $transactionIDs[1]){
                $transactionId = $transactionIDs[1];
            }
        }

         $payrexx = $this->getInterface($salesChannelId);
         $transaction = new \Payrexx\Models\Request\Transaction();
         $transaction->setId($transactionId);

         try {
             $transaction = $payrexx->getOne($transaction);
             return $transaction;
         } catch (\Payrexx\PayrexxException $e) {
             return $e->getMessage();
         }
         return "";
 }

 /**
  * capture a Transaction
  *
  * @param integer $gatewayId The Payrexx Gateway ID
  * @param string $salesChannelId
  * @return string
  */
    public function captureTransaction($gatewayId, $salesChannelId)
    {
        if (!$gatewayId) {
            return false;
        }
        $payrexx = $this->getInterface($salesChannelId);

        $transaction = new \Payrexx\Models\Request\Transaction();
        $transaction->setId($gatewayId);

        try {
            $response = $payrexx->capture($transaction);
            //var_dump($response);
            return $response;
        } catch (\Payrexx\PayrexxException $e) {
            return $e->getMessage();
        }
    }

}
