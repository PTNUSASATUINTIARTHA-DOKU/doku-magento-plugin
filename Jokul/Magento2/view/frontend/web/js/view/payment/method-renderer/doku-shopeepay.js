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
                template: 'Jokul_Magento2/payment/doku-checkout-jokul',
                setWindow: false,
                dokuObj: {},
                dokuDiv: ''
            },
            redirectAfterPlaceOrder: false,
            afterPlaceOrder: function () {

                $.ajax({
                    type: 'GET',
                    url: url.build('jokulbackend/payment/requestcheckout'),
                    showLoader: true,
                    success: function (response) {
                        var dataResponse = $.parseJSON(response);
                        console.log("RESPONSE DI JS " + dataResponse +": response "+response)

                        if (dataResponse.err == false) {
                            if (dataResponse.response_msg) {
                                if(dataResponse.response_msg.includes("failed")) {
                                    alert({
                                        title: 'Payment Failed!',
                                        content: dataResponse.response_msg + '<br>Please retry payment',
                                        actions: {
                                            always: function () {
                                            }
                                        }
                                    });
                                } else {
                                    jQuery.each(dataResponse.result, function (i, val) {
                                        if (i != 'url') {
                                            $("#doku-checkout-jokul").append('<input type="hidden" name="' + i + '" value="' + val + '">');
                                        } else {
                                            $("#doku-checkout-jokul").attr("action", val);
                                        }
                                    });
                                    jQuery(function(){ jQuery('#submitDataCheckout').trigger('click'); });
                                }
                            } else if (dataResponse.response_message) {
                                if(dataResponse.response_message.includes("failed")) {
                                    alert({
                                        title: 'Payment Failed!',
                                        content: dataResponse.response_message + '<br>Please retry payment',
                                        actions: {
                                            always: function () {
                                            }
                                        }
                                    });
                                } else {
                                    window.location.replace(dataResponse.url_checkout);
                                }
                            } else {
                                jQuery.each(dataResponse.result, function (i, val) {
                                    if (i != 'url') {
                                        $("#doku-checkout-jokul").append('<input type="hidden" name="' + i + '" value="' + val + '">');
                                    } else {
                                        $("#doku-checkout-jokul").attr("action", val);
                                    }
                                });
                                jQuery(function(){ jQuery('#submitDataCheckout').trigger('click'); });
                            }
                        } else {
                            alert({
                                title: 'Payment error!',
                                content: '<br>Please retry payment',
                                actions: {
                                    always: function () {
                                    }
                                }
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        var dataResponse = $.parseJSON(response);
                        alert({
                            title: 'Payment Error!',
                            content: 'Please retry payment '+ dataResponse,
                            actions: {
                                always: function () {
                                }
                            }
                        });
                    }
                }); 

            },
            getDescription: function(){
                return window.checkoutConfig.payment.doku_shopeepay.description
            }
        });
    }
);
