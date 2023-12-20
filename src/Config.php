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
            return $module['version'];
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

}
