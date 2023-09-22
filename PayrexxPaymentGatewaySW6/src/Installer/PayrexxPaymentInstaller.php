<?php

namespace PayrexxPaymentGateway\Installer;

use PayrexxPaymentGateway\Installer\Modules\PaymentMethodInstaller;
use PayrexxPaymentGateway\Util\Compatibility\EntityRepositoryDecorator;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class PayrexxPaymentInstaller implements InstallerInterface
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function install(InstallContext $context): void
    {
        $this->getPaymentMethodInstaller()->install($context);
    }

    public function update(UpdateContext $context): void
    {
        $this->getPaymentMethodInstaller()->update($context);
    }

    public function uninstall(UninstallContext $context): void
    {
        $this->getPaymentMethodInstaller()->uninstall($context);
    }

    public function activate(ActivateContext $context): void
    {
        $this->getPaymentMethodInstaller()->activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->getPaymentMethodInstaller()->deactivate($context);
    }

    private function getPaymentMethodInstaller(): InstallerInterface
    {
        $entityName = \sprintf('%s.repository', PaymentMethodDefinition::ENTITY_NAME);
        $entityRepository = $this->container->get($entityName, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        if (\interface_exists(EntityRepositoryInterface::class) && $entityRepository instanceof EntityRepositoryInterface) {
            $entityRepository = new EntityRepositoryDecorator($entityRepository);
        }
     
        if (!$entityRepository instanceof EntityRepository) {
            throw new ServiceNotFoundException($entityName);
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);


        return new PaymentMethodInstaller($entityRepository, $pluginIdProvider);
    }
}
