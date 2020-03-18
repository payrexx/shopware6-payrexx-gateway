<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use PayrexxPaymentGateway\Handler\PaymentHandler;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PayrexxPaymentGatewaySW6 extends Plugin
{
    const PAYMENT_MEAN_PREFIX = 'payrexx_payment_';
    const PAYMENT_METHODS = ['mastercard', 'visa', 'american_express', 'postfinance_card', 'postfinance_efinance', 'apple_pay', 'wirpay', 'coinbase',
        'antepay', 'bancontact', 'eps', 'giropay', 'ideal', 'barzahlen', 'twint'];

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context): void
    {
        $this->addPaymentMethod($context->getContext());
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, $context->getContext());
        parent::activate($context);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        parent::deactivate($context);
    }

    /**
     * Adds the Payment Methods
     *
     * @param Context $context
     * @return void
     */
    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId();
        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            return;
        }

        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $paymentRepository = $this->container->get('payment_method.repository');

        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $isApplePay = $paymentMethod == 'apple_pay';
            $options = [
                'handlerIdentifier' => PaymentHandler::class,
                'name' => ucwords(str_replace('_', ' ', $paymentMethod)),
                'active' => false,
                'pluginId' => $pluginId,
                'customFields' => [
                    'payrexx_payment_method_name' => self::PAYMENT_MEAN_PREFIX . $paymentMethod,
                    'is_payrexx_applepay' => $isApplePay,
                    'template' => $isApplePay ? '@PayrexxPaymentGatewaySW6/storefront/payrexx/apple_pay_check.html.twig' : null,
                ]
            ];
            $paymentRepository->create([$options], $context);
        }
    }

    /**
     * Sets the Payment Method Active/Inactive
     *
     * @param bool $active
     * @param Context $context
     * @return void
     */
    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodExists = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodExists) {
            return;
        }

        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentMethods = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());
        if (!empty($paymentMethods->getIds())) {
            foreach ($paymentMethods->getIds() as $paymentMethodId) {   
                $paymentMethod = [
                    'id' => $paymentMethodId,
                    'active' => $active,
                ];
                $paymentRepository->update([$paymentMethod], $context);
            }
        }
    }

    /**
     * Get the Payment Method Id
     *
     * @return string
     */
    private function getPaymentMethodId(): ?string
    {
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentIds = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }
}