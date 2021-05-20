<?php


namespace PayrexxPaymentGateway\Installer\Modules;

use PayrexxPaymentGateway\Installer\InstallerInterface;
use Shopware\Core\Framework\Context;
use PayrexxPaymentGateway\Handler\PaymentHandler;
use PayrexxPaymentGateway\PayrexxPaymentGatewaySW6;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PaymentMethodInstaller implements InstallerInterface
{

    public const PAYREXX_MASTERPASS         = 'masterpass';
    public const PAYREXX_MASTERCARD         = 'mastercard';
    public const PAYREXX_VISA               = 'visa';
    public const PAYREXX_APPLE_PAY          = 'apple_pay';
    public const PAYREXX_MAESTRO            = 'maestro';
    public const PAYREXX_JCB                = 'jcb';
    public const PAYREXX_AMEX               = 'american_express';
    public const PAYREXX_WIR                = 'wirpay';
    public const PAYREXX_PAYPAL             = 'paypal';
    public const PAYREXX_BITCOIN            = 'bitcoin';
    public const PAYREXX_SOFORT             = 'sofortueberweisung_de';
    public const PAYREXX_AIRPLUS            = 'airplus';
    public const PAYREXX_BILLPAY            = 'billpay';
    public const PAYREXX_BONUSCARD          = 'bonuscard';
    public const PAYREXX_CASHU              = 'cashu';
    public const PAYREXX_CB                 = 'cb';
    public const PAYREXX_DINERS             = 'diners_club';
    public const PAYREXX_DIRECT_DEBIT       = 'direct_debit';
    public const PAYREXX_DISCOVER           = 'discover';
    public const PAYREXX_ELV                = 'elv';
    public const PAYREXX_IDEAL              = 'ideal';
    public const PAYREXX_INVOICE            = 'invoice';
    public const PAYREXX_MYONE              = 'myone';
    public const PAYREXX_PAYSAFECARD        = 'paysafecard';
    public const PAYREXX_PF_CARD            = 'postfinance_card';
    public const PAYREXX_PF_EFINANCE        = 'postfinance_efinance';
    public const PAYREXX_SWISSBILLING       = 'swissbilling';
    public const PAYREXX_TWINT              = 'twint';
    public const PAYREXX_VIACASH            = 'barzahlen';
    public const PAYREXX_BANCONTANCT        = 'bancontact';
    public const PAYREXX_GIROPAY            = 'giropay';
    public const PAYREXX_EPS                = 'eps';
    public const PAYREXX_GOOGLE_PAY         = 'google_pay';
    public const PAYREXX_KLARNA_PAYNOW      = 'klarna_paynow';
    public const PAYREXX_KLARNA_PAYLATER    = 'klarna_paylater';
    public const PAYREXX_ONEY               = 'oney';

    const PAYMENT_METHODS = [
        self::PAYREXX_MASTERPASS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Masterpass',
                ],
                'en-GB' => [
                    'name' => 'Masterpass',
                ],
            ],
        ],
        self::PAYREXX_MASTERCARD => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Mastercard',
                ],
                'en-GB' => [
                    'name' => 'Mastercard',
                ],
            ],
        ],
        self::PAYREXX_VISA => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Visa',
                ],
                'en-GB' => [
                    'name' => 'Visa',
                ],
            ],
        ],
        self::PAYREXX_APPLE_PAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Apple Pay',
                ],
                'en-GB' => [
                    'name' => 'Apple Pay',
                ],
            ],
        ],
        self::PAYREXX_MAESTRO => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Maestro',
                ],
                'en-GB' => [
                    'name' => 'Maestro',
                ],
            ],
        ],
        self::PAYREXX_JCB => [
            'translations' => [
                'de-DE' => [
                    'name' => 'JCB',
                ],
                'en-GB' => [
                    'name' => 'JCB',
                ],
            ],
        ],
        self::PAYREXX_AMEX => [
            'translations' => [
                'de-DE' => [
                    'name' => 'American Express',
                ],
                'en-GB' => [
                    'name' => 'American Express',
                ],
            ],
        ],
        self::PAYREXX_WIR => [
            'translations' => [
                'de-DE' => [
                    'name' => 'WIRpay',
                ],
                'en-GB' => [
                    'name' => 'WIRpay',
                ],
            ],
        ],
        self::PAYREXX_PAYPAL => [
            'translations' => [
                'de-DE' => [
                    'name' => 'PayPal',
                ],
                'en-GB' => [
                    'name' => 'PayPal',
                ],
            ],
        ],
        self::PAYREXX_BITCOIN => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Bitcoin',
                ],
                'en-GB' => [
                    'name' => 'Bitcoin',
                ],
            ],
        ],
        self::PAYREXX_SOFORT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Sofortüberweisung',
                ],
                'en-GB' => [
                    'name' => 'Sofortüberweisung',
                ],
            ],
        ],
        self::PAYREXX_AIRPLUS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Airplus',
                ],
                'en-GB' => [
                    'name' => 'Airplus',
                ],
            ],
        ],
        self::PAYREXX_BILLPAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Billpay',
                ],
                'en-GB' => [
                    'name' => 'Billpay',
                ],
            ],
        ],
        self::PAYREXX_BONUSCARD => [
            'translations' => [
                'de-DE' => [
                    'name' => 'onus card',
                ],
                'en-GB' => [
                    'name' => 'onus card',
                ],
            ],
        ],
        self::PAYREXX_CASHU => [
            'translations' => [
                'de-DE' => [
                    'name' => 'CashU',
                ],
                'en-GB' => [
                    'name' => 'CashU',
                ],
            ],
        ],
        self::PAYREXX_CB => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Carte Bleue',
                ],
                'en-GB' => [
                    'name' => 'Carte Bleue',
                ],
            ],
        ],
        self::PAYREXX_DINERS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Diners Club',
                ],
                'en-GB' => [
                    'name' => 'Diners Club',
                ],
            ],
        ],
        self::PAYREXX_DIRECT_DEBIT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Direct Debit',
                ],
                'en-GB' => [
                    'name' => 'Direct Debit',
                ],
            ],
        ],
        self::PAYREXX_DISCOVER => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Discover',
                ],
                'en-GB' => [
                    'name' => 'Discover',
                ],
            ],
        ],
        self::PAYREXX_ELV => [
            'translations' => [
                'de-DE' => [
                    'name' => 'ELV',
                ],
                'en-GB' => [
                    'name' => 'ELV',
                ],
            ],
        ],
        self::PAYREXX_IDEAL => [
            'translations' => [
                'de-DE' => [
                    'name' => 'iDEAL',
                ],
                'en-GB' => [
                    'name' => 'iDEAL',
                ],
            ],
        ],
        self::PAYREXX_INVOICE => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Invoice',
                ],
                'en-GB' => [
                    'name' => 'Invoice',
                ],
            ],
        ],
        self::PAYREXX_MYONE => [
            'translations' => [
                'de-DE' => [
                    'name' => 'My One',
                ],
                'en-GB' => [
                    'name' => 'My One',
                ],
            ],
        ],
        self::PAYREXX_PAYSAFECARD => [
            'translations' => [
                'de-DE' => [
                    'name' => 'aysafe Card',
                ],
                'en-GB' => [
                    'name' => 'aysafe Card',
                ],
            ],
        ],
        self::PAYREXX_PF_CARD => [
            'translations' => [
                'de-DE' => [
                    'name' => 'PostFinance Card',
                ],
                'en-GB' => [
                    'name' => 'PostFinance Card',
                ],
            ],
        ],
        self::PAYREXX_PF_EFINANCE => [
            'translations' => [
                'de-DE' => [
                    'name' => 'PostFinance E-Finance',
                ],
                'en-GB' => [
                    'name' => 'PostFinance E-Finance',
                ],
            ],
        ],
        self::PAYREXX_SWISSBILLING => [
            'translations' => [
                'de-DE' => [
                    'name' => 'SwissBilling',
                ],
                'en-GB' => [
                    'name' => 'SwissBilling',
                ],
            ],
        ],
        self::PAYREXX_TWINT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Twint',
                ],
                'en-GB' => [
                    'name' => 'Twint',
                ],
            ],
        ],
        self::PAYREXX_VIACASH => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Barzahlen',
                ],
                'en-GB' => [
                    'name' => 'Viacash',
                ],
            ],
        ],
        self::PAYREXX_BANCONTANCT => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Bancontact',
                ],
                'en-GB' => [
                    'name' => 'Bancontact',
                ],
            ],
        ],
        self::PAYREXX_GIROPAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'GiroPay',
                ],
                'en-GB' => [
                    'name' => 'GiroPay',
                ],
            ],
        ],
        self::PAYREXX_EPS => [
            'translations' => [
                'de-DE' => [
                    'name' => 'EPS',
                ],
                'en-GB' => [
                    'name' => 'EPS',
                ],
            ],
        ],
        self::PAYREXX_GOOGLE_PAY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Google Pay',
                ],
                'en-GB' => [
                    'name' => 'Google Pay',
                ],
            ],
        ],
        self::PAYREXX_KLARNA_PAYNOW => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Klarna Sofortüberweisung',
                ],
                'en-GB' => [
                    'name' => 'Klarna Pay Now',
                ],
            ],
        ],
        self::PAYREXX_KLARNA_PAYLATER => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Klarna Rechnung',
                ],
                'en-GB' => [
                    'name' => 'Klarna Pay Later',
                ],
            ],
        ],
        self::PAYREXX_ONEY => [
            'translations' => [
                'de-DE' => [
                    'name' => 'Oney',
                ],
                'en-GB' => [
                    'name' => 'Oney',
                ],
            ],
        ],
    ];

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    /** @var PluginIdProvider */
    private $pluginIdProvider;

    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository,
        PluginIdProvider $pluginIdProvider
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->pluginIdProvider        = $pluginIdProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstallContext $context): void
    {
        foreach (self::PAYMENT_METHODS as $payrexxPaymentMethodIdentifier => $payrexxPaymentMethod) {
            $this->upsertPaymentMethod($context->getContext(), $payrexxPaymentMethod, $payrexxPaymentMethodIdentifier);
        }
    }

    public function uninstall(UninstallContext $context): void
    {
        // Nothing to do, payment methods already deactivated
    }

    public function activate(ActivateContext $context): void
    {
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentMethods = $this->paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        foreach ($paymentMethods->getIds() as $paymentMethodId) {
            $this->setPaymentMethodIsActive($context->getContext(), $paymentMethodId, true);
        }
    }

    public function deactivate(DeactivateContext $context): void
    {
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentMethods = $this->paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        foreach ($paymentMethods->getIds() as $paymentMethodId) {
            $this->setPaymentMethodIsActive($context->getContext(), $paymentMethodId, false);
        }
    }

    public function update(UpdateContext $context): void {
        foreach (self::PAYMENT_METHODS as $payrexxPaymentMethodIdentifier => $payrexxPaymentMethod) {
            $this->upsertPaymentMethod($context->getContext(), $payrexxPaymentMethod, $payrexxPaymentMethodIdentifier);
        }
    }

    /**
     * @param Context $context
     * @param array $paymentMethod
     * @return void
     */
    private function upsertPaymentMethod(Context $context, array $payrexxPaymentMethod, string $payrexxPaymentMethodIdentifier): void
    {
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(PayrexxPaymentGatewaySW6::class, $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class))
            ->addFilter(new EqualsFilter('customFields.payrexx_payment_method_name', PaymentHandler::PAYMENT_METHOD_PREFIX . $payrexxPaymentMethodIdentifier))
            ->setLimit(1);
        $paymentMethod = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext());
        $paymentMethodId = null;

        if ($paymentMethod->count()){
            $paymentMethodId = $paymentMethod->getEntities()->first()->getId();
        }

        $isApplePay = $payrexxPaymentMethodIdentifier == self::PAYREXX_APPLE_PAY;
        $options = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => PaymentHandler::class,
            'translations' => $payrexxPaymentMethod['translations'],
            'active' => false,
            'pluginId' => $pluginId,
            'customFields' => [
                'payrexx_payment_method_name' => PaymentHandler::PAYMENT_METHOD_PREFIX . $payrexxPaymentMethodIdentifier,
                'is_payrexx_applepay' => $isApplePay,
                'template' => $isApplePay ? '@PayrexxPaymentGatewaySW6/storefront/payrexx/apple_pay_check.html.twig' : null,
            ]
        ];

        if (!$paymentMethodId) {
            $options['afterOrderEnabled'] = true;
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($options): void {
            $this->paymentMethodRepository->upsert([$options], $context);
        });
    }

    /**
     * @param Context $context
     * @param int $paymentMethodId
     * @param boolean $active
     * @return void
     */
    private function setPaymentMethodIsActive($context, $paymentMethodId, $active) {
        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];
        $this->paymentMethodRepository->update([$paymentMethod], $context);
    }
}
