import Plugin from 'src/plugin-system/plugin.class';

export default class GooglePay extends Plugin {
    init() {
        var deviceSupported = checkDeviceSupport();
        displayGooglePay();

        /**
         * Check Device support the payment method
         *
         * @returns bool
         */
        function checkDeviceSupport() {
            try {
                const baseRequest = {
                    apiVersion: 2,
                    apiVersionMinor: 0
                };
                const allowedCardNetworks = ['MASTERCARD', 'VISA'];
                const allowedCardAuthMethods = ['CRYPTOGRAM_3DS'];
                const baseCardPaymentMethod = {
                    type: 'CARD',
                    parameters: {
                        allowedAuthMethods: allowedCardAuthMethods,
                        allowedCardNetworks: allowedCardNetworks
                    }
                };

                const isReadyToPayRequest = Object.assign({}, baseRequest);
                isReadyToPayRequest.allowedPaymentMethods = [
                    baseCardPaymentMethod
                ];
                const paymentsClient = new google.payments.api.PaymentsClient(
                    {
                        environment: 'TEST'
                    }
                );
                paymentsClient.isReadyToPay(isReadyToPayRequest).then(function(response) {
                    if (response.result) {
                        return true;
                    }
                }).catch(function(err) {
                    console.log(err);
                });
                return false;
            } catch (err) {
                console.log(err);
            }
            return false;
        }

        /**
         * Display the payment method
         *
         * @returns bool
         */
        function displayGooglePay() {
            if (deviceSupported) {
                console.warn("Payrexx GooglePay is not supported on this device/browser");
                return;
            }
            jQuery("#payrexx-googlepay-check").parent('.payment-method').remove();
        }
    }
}