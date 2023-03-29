import Plugin from 'src/plugin-system/plugin.class';

export default class ApplePay extends Plugin {
    init() {
        var deviceSupported = checkDeviceSupport();
        displayApplePay();

        /**
         * Check Device support the payment method
         *
         * @returns bool
         */
        function checkDeviceSupport() {
            if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
                console.warn("Payrexx Apple Pay is not supported on this device/browser");
                return false;
            }
            return true;
        }

        /**
         * Display the payment method
         *
         * @returns bool
         */
        function displayApplePay() {
            if (deviceSupported) {
                return;
            }
            jQuery("#payrexx-applepay-check").parent('.payment-method').remove();
        }
    }
}