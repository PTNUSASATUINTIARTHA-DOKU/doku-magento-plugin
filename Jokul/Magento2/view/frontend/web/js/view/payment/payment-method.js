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
                type: 'doku_bcava',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-bcava'
            },
            {
                type: 'doku_mandiriva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-mandiriva'
            },
            {
                type: 'doku_briva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-briva'
            },
            {
                type: 'doku_bniva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-bniva'
            },
            {
                type: 'doku_bsiva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-bsiva'
            },
            {
                type: 'doku_cimbva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-cimbva'
            },
            {
                type: 'doku_danamonva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-danamonva'
            },
            {
                type: 'doku_dokuva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-dokuva'
            },
            {
                type: 'doku_maybankva',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-maybankva'
            },
            {
                type: 'doku_permatava',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-permatava'
            },
            {
                type: 'doku_akulaku',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-akulaku'
            },
            {
                type: 'doku_briceria',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-briceria'
            },
            {
                type: 'doku_indodana',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-indodana'
            },
            {
                type: 'doku_kredivo',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-kredivo'
            },
            {
                type: 'doku_alfa',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-alfa'
            },
            {
                type: 'doku_indomaret',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-indomaret'
            },
            {
                type: 'doku_danamonOB',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-danamonOB'
            },
            {
                type: 'doku_epaybri',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-epaybri'
            },
            {
                type: 'doku_octoclicks',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-octoclicks'
            },
            {
                type: 'doku_permatanet',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-permatanet'
            },
            {
                type: 'doku_dana',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-dana'
            },
            {
                type: 'doku_dokuwallet',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-dokuwallet'
            },
            {
                type: 'doku_linkaja',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-linkaja'
            },
            {
                type: 'doku_ovo',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-ovo'
            },
            {
                type: 'doku_shopeepay',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-shopeepay'
            },
            {
                type: 'doku_directdebitbri',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-directdebitbri'
            },
            {
                type: 'doku_directdebitcimb',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-directdebitcimb'
            },
            {
                type: 'doku_jeniuspay',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-jeniuspay'
            },
            {
                type: 'doku_credit_card',
                component: 'Jokul_Magento2/js/view/payment/method-renderer/doku-cc'
            },
        );

        return Component.extend({});
    }
);
