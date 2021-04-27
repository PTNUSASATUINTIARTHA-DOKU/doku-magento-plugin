/**
 * Copyright © 2016 Doku. All rights reserved.
 */
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'mage/url',
        'Magento_Ui/js/modal/alert',
        'Magento_Checkout/js/checkout-data',
        'mage/loader'
    ],
    function (Component, $, url, alert, checkout, loader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Jokul_Magento2/payment/credit-card-jokul',
                setWindow: false,
                dokuObj: {},
                dokuDiv: ''
            },
            redirectAfterPlaceOrder: false,

            afterPlaceOrder: function(){
                var x = document.getElementById("buttonPayCC");
                if (x.style.display === "none") {
                    x.style.display = "block";
                } else {
                    x.style.display = "none";
                }

                var ifrm = document.getElementById("iframeUrl");
                if (ifrm.style.display === "none") {
                    ifrm.style.display = "block";
                } else {
                    ifrm.style.display = "none";
                }

                $.ajax({
                    type: 'GET',
                    url: url.build('jokulbackend/payment/requestcc'),
                    showLoader: true,
                    success: function (response) {
                        var dataResponse = $.parseJSON(response);
                        if (dataResponse.err == false) {
                            $('#iframeUrl').attr('src',dataResponse.result.URL);
                        } else {
                            alert({
                                title: 'Payment error!',
                                content: 'Error code : ' + dataResponse.response_message + '<br>Please retry payment Or contact your administrator',
                                actions: {
                                    always: function () {
                                    }
                                }
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        alert({
                            title: 'Payment Error!',
                            content: 'Please retry payment',
                            actions: {
                                always: function () {
                                }
                            }
                        });
                    }
                });
            },
            getDescription: function(){
                return window.checkoutConfig.payment.doku_cc_merchanthosted.description
            }
        });
    }
);
