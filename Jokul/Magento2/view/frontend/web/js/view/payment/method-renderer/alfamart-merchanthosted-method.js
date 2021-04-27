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
                    template: 'Jokul_Magento2/payment/alfamart-merchanthosted',
                    setWindow: false,
                    dokuObj: {},
                    dokuDiv: ''
                },
                redirectAfterPlaceOrder: false,
                afterPlaceOrder: function () {

                    $.ajax({
                        type: 'GET',
                        url: url.build('jokulbackend/payment/requestO2O'),
                        showLoader: true,
                        success: function (response) {
                            var dataResponse = $.parseJSON(response);
                            
                            if (dataResponse.err == false) {
                                jQuery.each(dataResponse.result, function (i, val) {
                                    if (i != 'url') {
                                        $("#alfamart-merchanthosted").append('<input type="hidden" name="' + i + '" value="' + val + '">');
                                    } else {
                                        $("#alfamart-merchanthosted").attr("action", val);
                                    }
                                    $("#alfamart-merchanthosted").submit();
                                });
                            } else {
                                alert({
                                    title: 'Something went wrong!',
                                    content: `Failed reason: ${dataResponse.response_message} <br>Please retry payment`,
                                    actions: {
                                        always: function () {
                                        }
                                    }
                                });
                            }
                        },
                        error: function (xhr, status, error) {
                            alert({
                                title: 'Error occured!',
                                content: 'Please retry payment',
                                actions: {
                                    always: function () {
                                    }
                                }
                            });
                        }
                    }); 

//                window.location = url.build('dokuhosted/payment/request');
                },
                getDescription: function(){
                     return window.checkoutConfig.payment.alfamart_merchanthosted.description
                }
            });
        }
);
