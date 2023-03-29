// Import all necessary Storefront plugins
import GooglePay from './google-pay/google-pay-check';
import ApplePay from './apple-pay/apple-pay-check';

// Register the plugin via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('GooglePay', GooglePay, '[data-payrexx-googlepay-check]');
PluginManager.register('ApplePay', ApplePay, '[data-payrexx-applepay-check]');