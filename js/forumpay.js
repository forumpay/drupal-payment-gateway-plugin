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
    restGetRatesUri: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'getRates'
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
    restGetWalletAppsUri: {
      'path': '',
      'params': {
        'orderId': forumPayData('forumpay-orderId'),
        'act': 'getWalletApps'
      },
    },
    successResultUrl: forumPayData('forumpay-returnurl'),
    errorResultUrl: forumPayData('forumpay-cancelurl'),
    forumPayApiUrl: forumPayData('forumpay-forumpayapiurl'),
    invoiceAmount: forumPayData('forumpay-invoiceamount'),
    invoiceCurrency: forumPayData('forumpay-invoicecurrency'),
    payer: {
      'payer_type': '',
      'payer_first_name': forumPayData('forumpay-payerfirstname'),
      'payer_last_name': forumPayData('forumpay-payerlastname'),
      'payer_country_of_residence': forumPayData('forumpay-payercountry'),
      'payer_email': forumPayData('forumpay-payeremail'),
      'payer_date_of_birth': '',
      'payer_country_of_birth': '',
      'payer_company': forumPayData('forumpay-payercompany'),
      'payer_date_of_incorporation': '',
      'payer_country_of_incorporation': forumPayData('forumpay-payercountry'),
    },
    messageReceiver: function (name, data) {
    },
  }
  window.forumPayPaymentGatewayWidget = new ForumPayPaymentGatewayWidget(config);
  window.forumPayPaymentGatewayWidget.init();
}

initPlugin();
