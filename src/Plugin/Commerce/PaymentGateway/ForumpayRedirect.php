<?php

namespace Drupal\commerce_forumpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_forumpay\Config;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

/**
 * Provides the forumpay payment gateway
 *
 * @CommercePaymentGateway(
 *   id = "forumpay",
 *   label = @Translation("ForumPay"),
 *   display_label = @Translation("Pay with Crypto (by ForumPay)"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_forumpay\PluginForm\OffsiteRedirect\ForumpayForm"
 *   }
 * )
 */
class ForumPayRedirect extends OffsitePaymentGatewayBase {
    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
        return [
                'api_url' => Config::PRODUCTION_URL,
                'api_user' => '',
                'api_key' => '',
                'pos_id' => 'drupal',
                'success_order_state' => 'completed',
                'api_url_override' => '',
                'accept_zero_confirmations' => TRUE,
                'network_processing_fee_paid_by' => 'payer',
                'mode' => 'live',
            ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['#attached']['library'][] = 'commerce_forumpay/admin_gateway_settings';

        $form['api_url'] = [
            '#type' => 'select',
            '#title' => $this->t('Environment'),
            '#default_value' => $this->configuration['api_url'] ?? Config::PRODUCTION_URL,
            '#description' => $this->t('ForumPay environment.'),
            '#required' => TRUE,
            '#options' => [
                Config::PRODUCTION_URL => $this->t('Production'),
                Config::SANDBOX_URL => $this->t('Sandbox'),
            ],
        ];

        $hasStoredApiKey = !empty($this->configuration['api_key']);

        $form['api_user'] = [
            '#type' => 'textfield',
            '#title' => $this->t('API User'),
            '#default_value' => $this->configuration['api_user'] ?? '',
            '#description' => $this->t('You can generate API key in your ForumPay Account.'),
            '#required' => TRUE,
        ];

        $form['api_key'] = [
            '#type' => 'password',
            '#title' => $this->t('API Secret'),
            '#default_value' => $hasStoredApiKey ? Config::API_SECRET_MASK : '',
            '#description' => $this->t('You can generate API secret in your ForumPay Account.'),
            '#required' => TRUE,
            '#attributes' => $hasStoredApiKey
                ? ['value' => Config::API_SECRET_MASK]
                : [],
        ];

        $form['pos_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('POS ID'),
            '#default_value' => $this->configuration['pos_id'] ?? 'drupal',
            '#description' => $this->t('Enter your webshop identifier (POS ID). Special characters not allowed. Allowed are: [A-Za-z0-9._-] Eg drupal-3'),
            '#required' => TRUE,
        ];

        $form['success_order_state'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Success Order Status'),
            '#default_value' => $this->configuration['success_order_state'] ?? 'completed',
            '#description' => $this->t('Order status assigned to successful orders.'),
            '#required' => TRUE,
        ];

        $form['webhook_url'] = [
            '#type' => 'url',
            '#title' => $this->t('Webhook URL'),
            '#default_value' => $this->configuration['webhook_url'],
            '#description' => Markup::create($this->t(
                'Optional: This URL should point to the endpoint that will handle the webhook events.<br>' .
                'Typically, it should be: <b><i>@url</i></b><br>' .
                'This URL will override the default setting for your API keys on your ForumPay Account.<br>' .
                'Ensure that the URL is publicly accessible and can handle the incoming webhook events securely.',
                [
                    '@url' => Url::fromUri(
                        Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString() . 'forumpay-api?act=webhook'
                    )->toString(),
                ]
            )),
            '#required' => FALSE,
        ];

        $form['api_url_override'] = [
            '#type' => 'url',
            '#title' => $this->t('Custom environment URL'),
            '#default_value' => $this->configuration['api_url_override'],
            '#description' => $this->t('Optional: URL to the API server. This value will override the default setting. Only used for debugging.'),
            '#required' => FALSE,
        ];

        $form['ping_button'] = [
            '#type' => 'markup',
            '#markup' => Markup::create('<button id="drupal_forumpay_api_test" class="button button--secondary" type="button">' . t('Test API credentials') . '</button>' .
                '<p class="description form-item__description">' . t('Click the button to check credentials and connection to ForumPay server. No order will be created.') . '</p>'),
        ];

        $form['accept_zero_confirmations'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Accept Zero Confirmations'),
            '#default_value' => $this->configuration['accept_zero_confirmations'],
            '#required' => FALSE,
        ];

        $form['accept_underpayment_main'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Auto-Accept Underpayments'),
            '#default_value' => $this->configuration['accept_underpayment_main'],
            '#required' => FALSE,
            '#description' => $this->t('Enable this option to automatically accept payments that are slightly less than the total order amount.'),
        ];

        $form['accept_underpayment_threshold'] = [
            '#type' => 'textfield',
            '#title' => $this->t(''),
            '#default_value' => $this->configuration['accept_underpayment_threshold'],
            '#description' => $this->t('Enter the maximum percentage (0-100) of the order total that can be underpaid for the order to be accepted automatically or leave blank to accept any underpayment amount.'),
            '#wrapper_attributes' => [
                'class' => ['padding-left-1-6-em'],
            ],
        ];

        $form['accept_underpayment_modify_order_total'] = [
            '#type' => 'checkbox',
            '#title' => $this->t(''),
            '#default_value' => $this->configuration['accept_underpayment_modify_order_total'],
            '#description' => $this->t('Enable to modify the order total to reflect underpayments as a separate fee. This will be negative to indicate less payment received.'),
            '#wrapper_attributes' => [
                'class' => ['padding-left-1-6-em'],
            ],
        ];

        $form['accept_underpayment_modify_order_total_description'] = [
            '#type' => 'textfield',
            '#title' => $this->t(''),
            '#default_value' => $this->configuration['accept_underpayment_modify_order_total_description'] ?? $this->t('ForumPay underpayment'),
            '#description' => $this->t('Enter a description for the underpayment fee that will appear on the customer\'s invoice.'),
            '#wrapper_attributes' => [
                'class' => ['padding-left-1-6-em'],
            ],
        ];

        $form['accept_overpayment_main'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Auto-Accept Overpayments'),
            '#default_value' => $this->configuration['accept_overpayment_main'],
            '#required' => FALSE,
            '#description' => $this->t('Enable this option to automatically accept payments that exceed the total order amount.'),
        ];

        $form['accept_overpayment_threshold'] = [
            '#type' => 'textfield',
            '#title' => $this->t(''),
            '#default_value' => $this->configuration['accept_overpayment_threshold'],
            '#description' => $this->t('Enter the maximum percentage of the order total that can be overpaid for the order to be accepted automatically or leave blank to accept any overpayment amount.'),
            '#wrapper_attributes' => [
                'class' => ['padding-left-1-6-em'],
            ],
        ];

        $form['accept_overpayment_modify_order_total'] = [
            '#type' => 'checkbox',
            '#title' => $this->t(''),
            '#default_value' => $this->configuration['accept_overpayment_modify_order_total'],
            '#description' => $this->t('Enable to modify the order total to reflect overpayments as a separate fee. This will be positive to indicate extra payment received.'),
            '#wrapper_attributes' => [
                'class' => ['padding-left-1-6-em'],
            ],
        ];

        $form['accept_overpayment_modify_order_total_description'] = [
            '#type' => 'textfield',
            '#title' => $this->t(''),
            '#default_value' => $this->configuration['accept_overpayment_modify_order_total_description'] ?? $this->t('ForumPay overpayment'),
            '#description' => $this->t('Enter a description for the overpayment fee that will appear on the customer\'s invoice.'),
            '#wrapper_attributes' => [
                'class' => ['padding-left-1-6-em'],
            ],
        ];

        $form['accept_latepayment'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Auto-Accept Late Payments'),
            '#default_value' => $this->configuration['accept_latepayment'],
            '#description' => $this->t('Automatically accept the payment if transaction was received late and either the paid amount is similar to requested or accepting it is allowed by the other Auto-Accept conditions.'),
        ];

        $networkProcessingFeePaidBy = $this->configuration['network_processing_fee_paid_by'] ?? 'payer';
        $merchantNoticeStyle = $networkProcessingFeePaidBy === 'merchant' ? '' : 'display:none;';
        $merchantNotice = sprintf(
            '<p id="edit-configuration-forumpay-network-processing-fee-paid-by-merchant-notice" style="%s">%s <a href="mailto:support@forumpay.com">support@forumpay.com</a></p>',
            $merchantNoticeStyle,
            $this->t("This option must be enabled for your account before you can select 'Merchant'. Contact your account representative or")
        );

        $form['network_processing_fee_paid_by'] = [
            '#type' => 'select',
            '#title' => $this->t('Network Processing Fee Paid By'),
            '#default_value' => $networkProcessingFeePaidBy,
            '#description' => $this->t('Set who will pay for the network processing fee.'),
            '#options' => [
                'payer'    => $this->t('Payer'),
                'merchant' => $this->t('Merchant'),
            ],
            '#suffix' => $merchantNotice,
        ];

        $config = \Drupal::configFactory()->getEditable('forumpay.settings');
        $form['installation_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Installation Id'),
            '#default_value' => $config->get('installation_id') ?? '',
            '#attributes' => [
                'readonly' => 'readonly',
            ],
        ];

        $form['mode']['#access'] = FALSE;

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::validateConfigurationForm($form, $form_state);

        $values = $form_state->getValue($form['#parents']);
        if (preg_match('/^[A-Za-z0-9._-]+$/', $values['pos_id']) !== 1) {
            $form_state->setErrorByName('pos_id',
                $this->t('POS ID field includes invalid characters. Allowed are: A-Za-z0-9._-')
            );
        }

        $apiUser = trim((string) ($values['api_user'] ?? ''));
        $rawSecret = trim((string) ($values['api_key'] ?? ''));
        $apiEnv = (string) ($values['api_url'] ?? '');
        $apiUrlOverride = trim((string) ($values['api_url_override'] ?? ''));
        $hasStoredApiKey = !empty($this->configuration['api_key']);

        if ($apiUser === '') {
            $form_state->setErrorByName('api_user',
                $this->t('API User field is required.')
            );
        }

        $config = new Config($this->configuration);
        $overrideRequiresSecret = $config->requiresExplicitSecret($apiEnv, $apiUrlOverride);

        if ($overrideRequiresSecret && ($rawSecret === '' || $rawSecret === Config::API_SECRET_MASK)) {
            $form_state->setErrorByName('api_key',
                $this->t('Enter your API Secret to change the API environment.')
            );
        }
        elseif ($rawSecret === '') {
            $form_state->setErrorByName('api_key',
                $this->t('API Secret field is required.')
            );
        }
        elseif ($rawSecret === Config::API_SECRET_MASK && !$hasStoredApiKey) {
            $form_state->setErrorByName('api_key',
                $this->t('API Secret field is required.')
            );
        }

        $acceptOverpaymentThreshold = trim($values['accept_overpayment_threshold']);
        $isEnabledAcceptOverpayment = (bool)$values['accept_overpayment_main'];
        if ($isEnabledAcceptOverpayment && $acceptOverpaymentThreshold !== '') {
            if (filter_var($acceptOverpaymentThreshold, FILTER_VALIDATE_FLOAT) === false) {
                $form_state->setErrorByName('accept_overpayment_threshold',
                    $this->t('Invalid overpayment threshold. Please enter a valid percentage between 0 and 100 or leave blank to accept any overpayment amount.')
                );
            }

            if (($acceptOverpaymentThreshold < 0.01) || ($acceptOverpaymentThreshold > 99.99)) {
                $form_state->setErrorByName('accept_overpayment_threshold',
                    $this->t('Invalid overpayment threshold. Please enter a valid percentage between 0 and 100 or leave blank to accept any overpayment amount.')
                );
            }
        }

        $acceptUnderpaymentThreshold = trim($values['accept_underpayment_threshold']);
        $isEnabledUnderpaymentThreshold = (bool)$values['accept_underpayment_main'];
        if ($isEnabledUnderpaymentThreshold && $acceptUnderpaymentThreshold !== '') {
            if (filter_var($acceptUnderpaymentThreshold, FILTER_VALIDATE_FLOAT) === false) {
                $form_state->setErrorByName('accept_underpayment_threshold',
                    $this->t('Invalid underpayment threshold. Please enter a valid percentage between 0 and 100 or leave blank to accept any underpayment amount.')
                );
            }

            if (($acceptUnderpaymentThreshold < 0.01) || ($acceptUnderpaymentThreshold > 99.99)) {
                $form_state->setErrorByName('accept_underpayment_threshold',
                    $this->t('Invalid underpayment threshold. Please enter a valid percentage between 0 and 100 or leave blank to accept any underpayment amount.')
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);

        if ($form_state->getErrors()) {
            return;
        }

        $values = $form_state->getValue($form['#parents']);
        $keys = [
            'api_url',
            'api_url_override',
            'pos_id',
            'success_order_state',
            'accept_zero_confirmations',
            'webhook_url',
            'accept_underpayment_main',
            'accept_underpayment_threshold',
            'accept_underpayment_modify_order_total',
            'accept_underpayment_modify_order_total_description',
            'accept_overpayment_main',
            'accept_overpayment_threshold',
            'accept_overpayment_modify_order_total',
            'accept_overpayment_modify_order_total_description',
            'accept_latepayment',
            'network_processing_fee_paid_by',
        ];

        $newApiUser = trim((string) ($values['api_user'] ?? ''));
        $newApiKey = Config::normalizePostedSecret((string) ($values['api_key'] ?? ''));

        $this->configuration['api_user'] = $newApiUser;

        if ($newApiKey !== '') {
            $this->configuration['api_key'] = $newApiKey;
        }
        // Empty / mask: keep the already-stored secret on $this->configuration['api_key'].

        if (!$values['accept_underpayment_main']) {
            $values['accept_underpayment_threshold'] = '';
        }

        if (!$values['accept_overpayment_main']) {
            $values['accept_overpayment_threshold'] = '';
        }

        foreach ($keys as $key) {
            $this->configuration[$key] = $values[$key];
        }
    }
}
