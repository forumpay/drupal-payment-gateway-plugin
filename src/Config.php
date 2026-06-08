<?php

namespace Drupal\commerce_forumpay;

/**
 * Forumpay Payment Gateway configuration.
 *
 */
class Config
{
    public const PRODUCTION_URL = 'https://api.forumpay.com/pay/v2/';
    public const SANDBOX_URL = 'https://sandbox.api.forumpay.com/pay/v2/';
    public const MODULE_NAME = 'commerce_forumpay';

    /**
     * @var array
     */
    private $configData;

    public function __construct($configData)
    {
        $this->configData = $configData;
    }

    /**
     * Return get version of this module
     *
     * @return string
     */
    public function getVersion(): string
    {
        $moduleHandler = \Drupal::service('module_handler');
        if ($moduleHandler->moduleExists(self::MODULE_NAME)) {
            $extensionList = \Drupal::service('extension.list.module');
            $module = $extensionList->getExtensionInfo(self::MODULE_NAME);
            return $module['version'] ?? '0';
        }
        return '';
    }

    /**
     * Return Drupal version of this module
     *
     * @return string
     */
    public function getDrupalVersion()
    {
        return  \Drupal::VERSION;
    }

    /**
     * Return Drupal Commerce plugin version
     *
     * @return string
     */
    public function getDrupalCommerceVersion()
    {
        $moduleHandler = \Drupal::service('module_handler');
        if ($moduleHandler->moduleExists('commerce')) {
            $extensionList = \Drupal::service('extension.list.module');
            $module = $extensionList->getExtensionInfo('commerce');
            return $module['version'];
        }
        return '';
    }

    /**
     * Returns url to FormPay api depending on the environment selected.
     *
     * @return string
     */
    public function getApiUrl()
    {
        $envOverride = $this->configData['api_url_override'];
        return $envOverride ?: $this->getPaymentMode();
    }

    /**
     * Returns url to ForumPay api if configured in settings or LIVE by default
     *
     * @return mixed|string
     */
    public function getPaymentMode()
    {
        return $this->configData['api_url'] ?? self::PRODUCTION_URL;
    }

    /**
     * Returns merchant api user
     *
     * @return mixed|string
     */
    public function getMerchantApiUser()
    {
        return $this->configData['api_user'];
    }

    /**
     * Returns merchant api secret
     *
     * @return string
     */
    public function getMerchantApiSecret()
    {
        return $this->configData['api_key'];
    }

    /**
     * Get status that order should be in after the payment
     *
     * @return mixed
     */
    public function getOrderStatusAfterPayment()
    {
        return $this->configData['success_order_state'];
    }

    /**
     * Webshop identifier (POS ID). Special characters not allowed. Allowed are: [A-Za-z0-9._-]
     *
     * @return string
     */
    public function getPosId()
    {
        $posId = $this->configData['pos_id'];

        if ($posId) {
            return preg_replace(
                '/[^A-Za-z0-9\-]/',
                '',
                str_replace(' ', '-', $posId)
            );
        }

        return '';
    }

    /**
     * If set to true, confirms small payment with zero confirmations
     *
     * @return bool
     */
    public function isAcceptZeroConfirmations(): bool
    {
        return $this->configData['accept_zero_confirmations'];
    }

    /**
     * Returns url to Webhook api depending.
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return $this->configData['webhook_url'];
    }

    /**
     * If set to true, confirms to automatically accept payments that are less than the total order amount
     *
     * @return bool
     */
    public function getAcceptUnderpayment(): bool
    {
        return $this->configData['accept_underpayment_main'];
    }

    /**
     * Returns maximum percentage of the order total that can be underpaid
     *
     * @return int|string
     */
    public function getAcceptUnderpaymentThreshold(): int|string
    {
        return $this->configData['accept_underpayment_threshold'] ?: '';
    }

    /**
     * If set to true, returns modified order total with underpayments as a separate and negative fee
     *
     * @return bool
     */
    public function getAcceptUnderpaymentModifyOrderTotal(): bool
    {
        return $this->configData['accept_underpayment_modify_order_total'];
    }

    /**
     * Returns a description for the underpayment fee
     *
     * @return string
     */
    public function getAcceptUnderpaymentModifyOrderTotalDescription(): string
    {
        return $this->configData['accept_underpayment_modify_order_total_description'];
    }

    /**
     * If set to true, confirms to automatically accept payments that exceed the total order amount
     *
     * @return bool
     */
    public function getAcceptOverpayment(): bool
    {
        return $this->configData['accept_overpayment_main'];
    }

    /**
     * Returns maximum percentage of the order total that can be overpaid
     *
     * @return int|string
     */
    public function getAcceptOverpaymentThreshold(): int|string
    {
        return $this->configData['accept_overpayment_threshold'] ?: '';
    }

    /**
     * If set to true, returns modified order total with overpayments as a separate and positive fee
     *
     * @return bool
     */
    public function getAcceptOverpaymentModifyOrderTotal(): bool
    {
        return $this->configData['accept_overpayment_modify_order_total'];
    }

    /**
     * Returns a description for the overpayment fee
     *
     * @return string
     */
    public function getAcceptOverpaymentModifyOrderTotalDescription(): string
    {
        return $this->configData['accept_overpayment_modify_order_total_description'];
    }

    /**
     * If set to true, confirms to automatically accept the payment if transaction was received late and either the paid
     * amount is similar to requested or accepting it is allowed by the other Auto-Accept conditions
     *
     * @return bool
     */
    public function getAcceptLatePayment(): bool
    {
        return $this->configData['accept_latepayment'];
    }

    /**
     * Returns who pays the network processing fee ('payer' or 'merchant')
     *
     * @return string
     */
    public function getNetworkProcessingFeePaidBy(): string
    {
        return $this->configData['network_processing_fee_paid_by'] ?? 'payer';
    }

    /**
     * Returns custom instructions that should be visible to customer.
     *
     * @return mixed
     */
    public function getInstructions()
    {
        return $this->configData['display_label'];
    }

    /**
     * Get current store locale string
     *
     * @return string
     */
    public function getStoreLocale()
    {
        $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();
        return $currentLanguage->getId();
    }

    /**
     * @return string|null
     */
    public function getInstallationId(): ?string
    {
        $config = \Drupal::configFactory()->getEditable('forumpay.settings');
        return $config->get('installation_id') ?? '';
    }
}
