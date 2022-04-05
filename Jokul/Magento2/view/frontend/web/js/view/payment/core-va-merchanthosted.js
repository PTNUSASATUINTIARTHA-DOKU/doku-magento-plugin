define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
        'jquery'
    ],
    function (
        Component,
        rendererList,
        $
    ) {
        'use strict';

        rendererList.push(
            {
                type: 'mandiri_va',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/mandiri-va-merchanthosted-method'
            },
            {
                type: 'mandiri_syariah_va',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/mandiri-syariah-va-merchanthosted-method'
            },
            {
                type: 'bri_va',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/bri-va-merchanthosted-method'
            },
            {
                type: 'doku_va',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-va-merchanthosted-method'
            },
            {
                type: 'bca_va',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/bca-va-merchanthosted-method'
            },
            {
                type: 'permata_va',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/permata-va-merchanthosted-method'
            },
            {
                type: 'doku_cc',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/credit-card-method'
            },
            {
                type: 'alfamart',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/alfamart-merchanthosted-method'
            },
            {
                type: 'doku_checkout_merchanthosted',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/jokul-checkout-method'
            }
        );

        return Component.extend({});
    }
);
