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
use PayrexxPaymentGateway\Installer\Modules\PayrexxMediaInstaller;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaService;

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

        // $payrexxMediaInstaller = $this->container->get('PayrexxPaymentGateway\Installer\Modules\PayrexxMediaInstaller');

        return new PaymentMethodInstaller(
            $entityRepository,
            $pluginIdProvider,
            // new PayrexxMediaInstaller(
            //     $this->getRepository($this->container, MediaDefinition::ENTITY_NAME),
            //     $this->getRepository($this->container, MediaFolderDefinition::ENTITY_NAME),
            //     $this->getRepository($this->container, PaymentMethodDefinition::ENTITY_NAME),
                $this->container->get(FileSaver::class)
            // )
        );
    }

    private function getRepository(ContainerInterface $container, string $entityName): EntityRepository
    {
        $repository = $container->get(\sprintf('%s.repository', $entityName), ContainerInterface::NULL_ON_INVALID_REFERENCE);

        if (\interface_exists(EntityRepositoryInterface::class) && $repository instanceof EntityRepositoryInterface) {
            return new EntityRepositoryDecorator($repository);
        }

        if (!$repository instanceof EntityRepository) {
            throw new ServiceNotFoundException(\sprintf('%s.repository', $entityName));
        }

        return $repository;
    }
}
