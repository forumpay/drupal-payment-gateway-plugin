const forumPayData = function (field) {
  return document.getElementById(field).getAttribute('data');
}

const initPlugin = function () {
  const config = {
    baseUrl: forumPayData('forumpay-apibase'),

    restGetCryptoCurrenciesUri: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'currencies'
      },
    },
    restGetRateUri: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'getRate'
      },
    },
    restStartPaymentUri: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'startPayment'
      },
    },
    restCheckPaymentUri: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'checkPayment'
      },
    },
    restCancelPaymentUri: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'cancelPayment'
      },
    },
    restRestoreCart: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'restoreCart'
      },
    },
    successResultUrl: forumPayData('forumpay-returnurl'),
    errorResultUrl: forumPayData('forumpay-cancelurl'),
    messageReceiver: function (name, data) {
    },
    showStartPaymentButton: true,
  }
  window.forumPayPaymentGatewayWidget = new ForumPayPaymentGatewayWidget(config);
  window.forumPayPaymentGatewayWidget.init();
}

initPlugin();
