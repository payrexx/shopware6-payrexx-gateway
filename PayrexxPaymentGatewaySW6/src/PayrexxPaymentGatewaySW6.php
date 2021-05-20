<?php

declare(strict_types=1);

namespace PayrexxPaymentGateway;

use PayrexxPaymentGateway\Installer\PayrexxPaymentInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PayrexxPaymentGatewaySW6 extends Plugin
{

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->install($context);
        parent::install($context);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->uninstall($context);
        parent::uninstall($context);
    }

    /**
     * @param ActivateContefxt $context
     */
    public function activate(ActivateContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->activate($context);
        parent::activate($context);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->deactivate($context);
        parent::deactivate($context);
    }

    /**
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context): void
    {
        (new PayrexxPaymentInstaller($this->container))->update($context);
        parent::update($context);
    }
}
