define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'jquery',
    'jquery/jquery-storageapi'
], function (Component, quote, totals) {
    "use strict";
    return Component.extend({
        defaults: {
            template: 'Jokul_Magento2/cart/totals/fee'
        },
        totals: quote.getTotals(),

        isDisplayed: function() {
            let total =  this.getPaymentDiscount() + this.getPaymentAdminFee()
            return total > 0;
        },

        getPaymentDiscount: function() {
            var price = 0;
            price = totals.getSegment('discount_fee').value;
            return price;
        },

        getPaymentAdminFee: function() {
            var price = 0;
            price = totals.getSegment('admin_fee').value;
            return price;
        },

        getJokulAdminFeeValue: function() {
            return this.getFormattedPrice(this.getPaymentAdminFee());
        },

        getJokulDiscountValue: function() {
            return this.getFormattedPrice(this.getPaymentDiscount());
        },

        getJokulPaymentDiscount: function () {
            return this.getJokulDiscountValue();
        },

        getJokulPaymentFee: function () {
            return this.getJokulAdminFeeValue();
        },
    });
});
