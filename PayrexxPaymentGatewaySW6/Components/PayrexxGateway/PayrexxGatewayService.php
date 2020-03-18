<?php
/**
 * (c) Payrexx AG <info@payrexx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Ueli Kramer <dev@payrexx.com>
 */

namespace PayrexxPaymentGateway\Components\PayrexxGateway;

use Payrexx\Models\Response\Gateway;

class PayrexxGatewayService
{
    /**
     * Check the Payrexx Gateway status whether it is paid or not
     *
     * @param integer $gatewayId The Payrexx Gateway ID
     * @return bool TRUE if the payment has been confirmed, FALSE if it is not confirmed
     */
    public function checkPayrexxGatewayStatus($gatewayId)
    {
        if (!$gatewayId) {
            return false;
        }
        $payrexx = $this->getInterface();
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setId($gatewayId);
        try {
            $gateway = $payrexx->getOne($gateway);
            return $gateway->getStatus() == 'confirmed';
        } catch (\Payrexx\PayrexxException $e) {
        }
        return false;
    }

    /**
     * @return \Payrexx\Payrexx
     */
    private function getInterface()
    {
        $shop = false;
        if (Shopware()->Container()->initialized('shop')) {
            $shop = Shopware()->Container()->get('shop');
        }

        if (!$shop) {
            $shop = Shopware()->Container()->get('models')->getRepository(\Shopware\Models\Shop\Shop::class)->getActiveDefault();
        }

        $config = Shopware()->Container()->get('shopware.plugin.cached_config_reader')->getByPluginName('PayrexxPaymentGateway', $shop);
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
     * @return Gateway
     *
     */
    public function createPayrexxGateway($orderNumber, $amount, $currency, $paymentMean, $user, $urls)
    {
        $billingInformation = $user['billingaddress'];

        $payrexx = $this->getInterface();
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setAmount($amount * 100);
        $gateway->setCurrency($currency);
        $gateway->setSuccessRedirectUrl($urls['successUrl']);
        $gateway->setFailedRedirectUrl($urls['errorUrl']);
        $gateway->setCancelRedirectUrl($urls['errorUrl']);

        $gateway->setPsp(array());
        $gateway->setPm(array($paymentMean));
        $gateway->setReferenceId($orderNumber);

        $gateway->addField('forename', $billingInformation['firstname']);
        $gateway->addField('surname', $billingInformation['lastname']);
        $gateway->addField('company', $billingInformation['company']);
        $gateway->addField('street', $billingInformation['street']);
        $gateway->addField('postcode', $billingInformation['zipCode']);
        $gateway->addField('place', $billingInformation['city']);
        $gateway->addField('email', $user['additional']['user']['email']);
        $gateway->addField('custom_field_1', $orderNumber, array(
            1 => 'Shopware Order ID',
            2 => 'Shopware B-Nr.',
            3 => 'Shopware Order ID',
            4 => 'Shopware Order ID',
        ));

        try {
            return $payrexx->create($gateway);
        } catch (\Payrexx\PayrexxException $e) {
        }
        return null;
    }
}
