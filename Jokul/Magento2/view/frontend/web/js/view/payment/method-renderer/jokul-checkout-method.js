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
                                jQuery.each(dataResponse.result, function (i, val) {
                                    if (i != 'url') {
                                        $("#doku-checkout-jokul").append('<input type="hidden" name="' + i + '" value="' + val + '">');
                                    } else {
                                        $("#doku-checkout-jokul").attr("action", val);
                                    }
                                    $("#doku-checkout-jokul").submit();
                                });
                            } else {
                                alert({
                                    title: 'Payment error!',
                                    content: 'Error code : ' + dataResponse.response_msg + '<br>Please retry payment',
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
                     return window.checkoutConfig.payment.doku_checkout_merchanthosted.description
                }
            });
        }
);
