<?php

namespace PayrexxPaymentGateway\Service;

use Payrexx\Communicator;
use Payrexx\Models\Response\Transaction;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PayrexxApiService
{
    /**
     * @var EntityRepository
     */
    protected $customerRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * Constructor
     *
     * @param EntityRepository $customerRepository
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepository $customerRepository, LoggerInterface $logger, ConfigService $configService)
    {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->configService = $configService;
    }

    /**
     * @return \Payrexx\Payrexx
     */
    private function getInterface($salesChannelId): \Payrexx\Payrexx
    {
        $config = $this->configService->getPluginConfiguration($salesChannelId);
        $platform = !empty($config['platform']) ? $config['platform'] : Communicator::API_URL_BASE_DOMAIN;
        return new \Payrexx\Payrexx($config['instanceName'], $config['apiKey'], '', $platform);
    }

    /**
     * Create a checkout page in Payrexx (Payrexx Gateway)
     *
     * @param string $orderNumber
     * @param float  $amount
     * @param float  $averageVatRate,
     * @param string $currency
     * @param string $paymentMean
     * @param array  $billingAndShippingDetails
     * @param string $url
     * @param array  $basket
     * @param string $salesChannelId
     * @param string $purpose
     * @return Gateway
     *
     */
    public function createPayrexxGateway(
        string $orderNumber,
        float $amount,
        float $averageVatRate,
        string $currency,
        string $paymentMean,
        array $billingAndShippingDetails,
        string $url,
        array $basket,
        string $salesChannelId,
        string $purpose
    ) {
        $config = $this->configService->getPluginConfiguration($salesChannelId);
        $lookAndFeelId = !empty($config['lookFeelID']) ? $config['lookFeelID'] : null;

        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setAmount($amount * 100);
        $gateway->setVatRate($averageVatRate);
        $gateway->setCurrency($currency);
        $gateway->setSuccessRedirectUrl($url);
        $gateway->setFailedRedirectUrl($url);
        $gateway->setCancelRedirectUrl($url);
        $gateway->setSkipResultPage(true);
        $gateway->setLookAndFeelProfile($lookAndFeelId);

        $gateway->setPsp([]);
        $gateway->setPm([$paymentMean]);
        $gateway->setReferenceId($orderNumber);
        $gateway->setValidity(15);

        foreach ($billingAndShippingDetails as $fieldKey => $fieldValue) {
            $gateway->addField($fieldKey, $fieldValue);
        }

        if (!empty($basket)) {
            $gateway->setBasket($basket);
        } else {
            $gateway->setPurpose($purpose);
        }

        try {
            return $payrexx->create($gateway);
        } catch (\Payrexx\PayrexxException $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }
        return null;
    }

    /**
     * @param $gatewayId
     * @param string $salesChannelId
     * @return \Payrexx\Models\Request\Gateway|bool
     */
    public function getPayrexxGateway($gatewayId, string $salesChannelId)
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
     * capture a Transaction
     *
     * @param integer $gatewayId The Payrexx Gateway ID
     * @param string $salesChannelId
     * @return string
     */
    public function captureTransaction($transactionId, $salesChannelId)
    {
        if (!$transactionId) {
            return false;
        }
        $payrexx = $this->getInterface($salesChannelId);

        $transaction = new \Payrexx\Models\Request\Transaction();
        $transaction->setId($transactionId);

        try {
            $response = $payrexx->capture($transaction);
            return $response;
        } catch (\Payrexx\PayrexxException $e) {
            return $e->getMessage();
        }
    }

    public function getTransactionByGateway($payrexxGateway, $salesChannelId): ?\Payrexx\Models\Response\Transaction
    {
        if (!in_array($payrexxGateway->getStatus(), [Transaction::CONFIRMED, Transaction::WAITING])) {
            return null;
        }
        $invoices = $payrexxGateway->getInvoices();

        if (!$invoices || !$invoice = end($invoices)) {
            return null;
        }

        if (!$transactions = $invoice['transactions']) {
            return null;
        }

        return $this->getPayrexxTransaction(end($transactions)['id'], $salesChannelId);
    }

    public function getPayrexxTransaction(int $payrexxTransactionId, $salesChannelId): ?\Payrexx\Models\Response\Transaction
    {
        $payrexx = $this->getInterface($salesChannelId);

        $payrexxTransaction = new \Payrexx\Models\Request\Transaction();
        $payrexxTransaction->setId($payrexxTransactionId);

        try {
            $response = $payrexx->getOne($payrexxTransaction);
            return $response;
        } catch(\Payrexx\PayrexxException $e) {
            return null;
        }
    }
}
