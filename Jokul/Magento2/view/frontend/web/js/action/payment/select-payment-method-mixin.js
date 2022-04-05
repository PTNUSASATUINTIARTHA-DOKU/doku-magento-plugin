define(
    [
        'mage/utils/wrapper',
        'ko',
        'Jokul_Magento2/js/action/checkout/cart/totals'
    ],
    function (wrapper, ko, totals) {
        'use strict';

        let isLoading = ko.observable(false);

        return function (selectPaymentMethodAction) {
            return wrapper.wrap(selectPaymentMethodAction, function (originalSelectPaymentMethodAction, paymentMethod) {
                originalSelectPaymentMethodAction(paymentMethod);
                totals(isLoading, paymentMethod['method']);
            });
        };
    }
);
