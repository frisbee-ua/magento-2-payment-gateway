define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList'
    ],
    function ($,
              quote,
              urlBuilder,
              storage,
              customerData,
              Component,
              placeOrderAction,
              selectPaymentMethodAction,
              customer,
              checkoutData,
              additionalValidators,
              url,
              fullScreenLoader,
              messageList
    ) {
        'use strict';

        return Component.extend({
            initialize: function () {
                this._super();
                this.init();
            },
            defaults: {
                template: 'Fondy_Fondy/payment/fondy'
            },
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }
                var self = this,
                    placeOrder;
                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    fullScreenLoader.startLoader();
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                        try {
                            self.isPlaceOrderActionAllowed(true);
                        } catch (e) {
                            this.showPaymentButton();
                        }
                    }).done(function (id, status) {
                        self.afterPlaceOrder(id);
                    }).always(function(){
                        fullScreenLoader.stopLoader();
                    });
                    return true;
                }
                return false;
            },

            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            checkout: function (id) {
                var payload = {
                    cartId: quote.getQuoteId(),
                    method: this.item.method,
                    orderId: id
                };

                this.request(payload);
            },

            apiPaymentHandler: function (data) {
                var response = JSON.parse(data);

                if (response.url) {
                    window.location = response.url;
                } else if (response.options) {
                    self.token = response.token;

                    fondy("#fondy-checkout-container", JSON.parse(response.options));
                    fullScreenLoader.stopLoader();
                } else {
                    fullScreenLoader.stopLoader();
                    try {
                        self.isPlaceOrderActionAllowed(true);
                    } catch (e) {
                        this.showPaymentButton();
                    }

                    if (response.message) {
                        messageList.addErrorMessage({ message: response.message });
                    } else {
                        messageList.addErrorMessage({ message: 'API response error' });
                    }

                    return false;
                }
            },

            afterPlaceOrder: function (id) {
                var payload = {
                    cartId: quote.getQuoteId(),
                    method: 'redirect',
                    orderId: id
                };

                this.request(payload);
            },

            request: function (payload) {
                var serviceUrl = urlBuilder.createUrl('/fondy/payment', {});

                storage.post(
                    serviceUrl, JSON.stringify(payload)
                ).done(this.apiPaymentHandler).fail(function (data) {
                    fullScreenLoader.stopLoader();

                    try {
                        self.isPlaceOrderActionAllowed(true);
                    } catch (e) {
                        this.showPaymentButton();
                    }
                });
            },

            init: function (data, event) {
                if (event) {
                    event.preventDefault();
                }
                var self = this,
                    placeOrder;
                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    fullScreenLoader.startLoader();
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                        try {
                            self.isPlaceOrderActionAllowed(true);
                        } catch (e) {
                            this.showPaymentButton();
                        }
                    }).done(function (id, status) {
                        self.checkout(id);
                    }).always(function(){
                        fullScreenLoader.stopLoader();
                    });
                    return true;
                }
                return false;
            },

            showPaymentButton: function () {
                document.getElementById('fondy-actions-container').style = 'display:block';
            }
        });
    }
);
