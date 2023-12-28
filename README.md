# Drupal 9 ForumPay payment module
# Installation guide

## Requirements

> Make sure you have at least Drupal Version 9.4 and Drupal Commerce 2.2 or higher.
> Install using the composer

## Installation using composer

```shell
composer require forumpay/drupal-9-payment-gateway-plugin
```

## Upgrade from previous version

```shell
composer update forumpay/drupal-9-payment-gateway-plugin
```

## Configuration

Once the plugin has been activated, go to the configuration page.
Navigate to **Manage > Commerce > Configuration > Payment gateways**.
Find button **+ Add payment gateway**

Enter the Payment gateway name: **ForumPay**.
From the **Plugin section**, select **ForumPay Payment** and fill in the rest of the parameters as follows.

### Configuration details:

1. **Display name**
   The label of the payment method that is displayed when user is prompted to choose one. You can leave default or set it to something like *Pay with crypto*.
2  **Environment**
   Dropdown lets you switch between 'Production' and 'Sandbox' modes.
   Use 'Production' for processing real transactions in a live environment and
   'Sandbox' for safe testing without financial implications.
3  **API User**
   This is our identifier that we need to access the payment system.
   It can be found in your **Profile**.
   [Go to profile >](https://dashboard.forumpay.com/pay/userPaymentGateway.api_settings)
4. **API Secret**
   _Important:_ never share it to anyone!
   Think of it as a password.
   API Secret consists of two parts. When generated in [ForumPay dashboard](https://dashboard.forumpay.com/pay/userPaymentGateway.api_settings),
   the first one will be displayed in your profile, while the second part will be sent to your e-mail.
   You need to enter both parts here (one after the other).
5. **POS ID**
   This is how payments coming to your wallets are going to be identified.
   Special characters are not allowed. Allowed characters are: `[A-Za-z0-9._-]` (e.g. `my-shop`, `my_shop`).
6. **Success Order Status**
   Which status the order gets once user successfully completes the payment.
7. **Custom environment URL**
   Optional: URL to the API server. This value will override the default setting. Only used for debugging.
8  **Accept Instant (Zero) Confirmations**
   Allows immediate transaction approval without waiting for network confirmations, enhancing speed but with increased risk.

Don't forget to click *Save* button after the settings are filled in.

## Webhook setup

**Webhook** allows us to check order status **independently** of the customer's actions.

For example, if the customer **closes tab** after the payment is started,
the webshop cannot determine what the status of the order is.

If you do not set the webhook notifications, orders may stay in the `Pending` status forever.

### Webhook setup:

Webhook configuration is in your [Profile](https://dashboard.forumpay.com/pay/userPaymentGateway.api_settings#webhook_notifications).
You can find the webhook URL by scrolling down.

Insert **URL** in the webhook URL field:
`YOUR_WEBSHOP/forumpay-api?act=webhook`

**YOUR_WEBSHOP** is the URL of your webshop. An example of the complete webhook URL would be:
`https://my.webshop.com/forumpay-api?act=webhook`

## Functionality

When the customer clicks on the **Place order** button they are being redirected to the payment page, where cryptocurrency can be selected.

When the currency is selected, details for the cryptocurrency payment will be displayed: Amount, Rate, Fee, Total, Expected time.

After the customer clicks the **START PAYMENT** button, they have 5 minutes to pay for the order by scanning the **QR Code** or manually using the blockchain address shown under the QR Code.

## Troubleshooting

> **Can not select cryptocurrency, there is no dropdown:**
This issue probably happens because web shop's backend cannot access ForumPay.
Please check if your API keys in the configuration are correct.

> **The plugin has been installed and activated successfully, but it does not appear in the Drupal payments settings**
Please ensure that you have installed the latest release of the ForumPay Payment Gateway.
