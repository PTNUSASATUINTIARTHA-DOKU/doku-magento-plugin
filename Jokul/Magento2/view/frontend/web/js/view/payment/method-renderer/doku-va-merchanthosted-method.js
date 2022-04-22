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
                    template: 'Jokul_Magento2/payment/doku-va-merchanthosted',
                    setWindow: false,
                    dokuObj: {},
                    dokuDiv: ''
                },
                redirectAfterPlaceOrder: false,
                afterPlaceOrder: function () {

                    $.ajax({
                        type: 'GET',
                        url: url.build('jokulbackend/payment/requestva'),
                        showLoader: true,
                        success: function (response) {
                            var dataResponse = $.parseJSON(response);
                            console.log(JSON.stringify(dataResponse.result))

                            if (dataResponse.err == false) {
                                jQuery.each(dataResponse.result, function (i, val) {
                                    if (i != 'url') {
                                        $("#doku-va-merchanthosted").append('<input type="hidden" name="' + i + '" value="' + val + '">');
                                    } else {
                                        $("#doku-va-merchanthosted").attr("action", val);
                                    }
                                });
                                jQuery(function(){ jQuery('#submitDataDokuVa').trigger('click'); });
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
                },
                getDescription: function(){
                     return window.checkoutConfig.payment.doku_va.description
                }
            });
        }
);
