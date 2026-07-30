<?php

namespace PayrexxPaymentGateway\Service;

use Payrexx\Payrexx;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;

class ConfigService
{
    const PLUGIN_CONFIG_DOMAIN = 'PayrexxPaymentGatewaySW6.settings.';

    protected EntityRepository $systemConfigRepository;
    protected LoggerInterface $logger;

    public function __construct(EntityRepository $systemConfigRepository, LoggerInterface $logger)
    {
        $this->systemConfigRepository = $systemConfigRepository;
        $this->logger = $logger;
    }

    public function getPluginConfiguration(string $requestSalesChannelId): array
    {
        if (!class_exists(Payrexx::class)) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        }

        $config = [];
        $salesChannelConfig = [];
        try {
            $criteria = (new Criteria())->addFilter(new ContainsFilter('configurationKey', self::PLUGIN_CONFIG_DOMAIN));
            $configurations = $this->systemConfigRepository->search($criteria, Context::createDefaultContext())->getEntities();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        if ($configurations) {
            foreach ($configurations as $configuration) {
                $configurationKey = $configuration->getConfigurationKey();
                $identifier = (string)substr($configurationKey, \strlen(self::PLUGIN_CONFIG_DOMAIN));

                if ($identifier === '') {
                    continue;
                }

                if ($configuration->getSalesChannelId() === $requestSalesChannelId) {
                    $salesChannelConfig[$identifier] = $configuration->getConfigurationValue();
                } else if (!$configuration->getSalesChannelId()) {
                    $config[$identifier] = $configuration->getConfigurationValue();;
                }

            }
        }
        return $salesChannelConfig ?: $config;
    }
}
