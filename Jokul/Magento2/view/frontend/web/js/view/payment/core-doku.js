/**
 * Copyright © 2016 Doku. All rights reserved.
 */
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
                type: 'bca_klikpay_core',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/bca-klikpay-Magento2-method'
            },
            {
                type: 'klik_bca_core',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/klik-bca-Magento2-method'
            }
        );

        /** Add view logic here if needed */

        return Component.extend({});
    }
);