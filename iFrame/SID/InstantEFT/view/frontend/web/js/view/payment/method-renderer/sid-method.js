/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
define(['jquery', 'Magento_Checkout/js/view/payment/default', 'Magento_Checkout/js/action/place-order', 'Magento_Checkout/js/action/select-payment-method', 'Magento_Customer/js/model/customer', 'Magento_Checkout/js/checkout-data', 'Magento_Checkout/js/model/payment/additional-validators', 'mage/url'], function($, Component, placeOrderAction, selectPaymentMethodAction, customer, checkoutData, additionalValidators, url) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'SID_InstantEFT/payment/sid'
        },
        placeOrder: function(data, event) {
            if (event) {
                event.preventDefault();
            }
            var self = this,
                placeOrder,
                emailValidationResult = customer.isLoggedIn(),
                loginFormSelector = 'form[data-role=email-with-possible-login]';
            if (!customer.isLoggedIn()) {
                $(loginFormSelector).validation();
                emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
            }
            if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                this.isPlaceOrderActionAllowed(false);
                placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);
                $.when(placeOrder).fail(function() {
                    self.isPlaceOrderActionAllowed(true);
                }).done(function(order_id) {
                    jQuery.ajax({
                        url: url.build('sid/redirect/order'),
                        type: "POST",
                        data: {
                            order_id: order_id
                        },
                        complete: function(data) {
                            var params = JSON.parse(data.responseText);
                            //<!-- Build Popup -->
                            jQuery("#sidButton").after("<div id='sidPopup'></div>");
                            jQuery("#sidPopup").append("<div id='sidPopupContent'></div>");
                            var queryString = jQuery.param(params);
                            jQuery("#sidPopupContent").append("<iframe id='sidPopupFrame' src='https://www.sidpayment.com/paysidv20/#/bank?" + queryString + "&POPUP=true&Continue=%20%20'></iframe>");
                        }
                    });
                });
                return false;
                return false;
            }
        },
        getCode: function() {
            return 'sid';
        },
        selectPaymentMethod: function() {
            selectPaymentMethodAction(this.getData());
            checkoutData.setSelectedPaymentMethod(this.item.method);
            return true;
        },
        getInstructions: function() {
            return window.checkoutConfig.payment.instructions[this.item.method];
        },
        isAvailable: function() {
            return quote.totals().grand_total <= 0;
        },
        afterPlaceOrder: function() {
            return false;
            window.location.replace(url.build(window.checkoutConfig.payment.sid.redirectUrl.sid));
        },
        getPaymentAcceptanceMarkHref: function() {
            return window.checkoutConfig.payment.sid.paymentAcceptanceMarkHref;
        },
        getPaymentAcceptanceMarkSrc: function() {
            return window.checkoutConfig.payment.sid.paymentAcceptanceMarkSrc;
        }
    });
});
