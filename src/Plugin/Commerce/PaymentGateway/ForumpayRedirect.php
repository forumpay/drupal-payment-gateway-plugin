<?php

namespace Drupal\commerce_forumpay\Plugin\Commerce\PaymentGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_forumpay\Config;

/**
 * Provides the forumpay payment gateway
 * @CommercePaymentGateway(
 *   id = "forumpay",
 *   label = "ForumPay Payment",
 *   display_label = "Pay with Crypto (by ForumPay)",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_forumpay\PluginForm\OffsiteRedirect\ForumpayForm"
 *   }
 * )
 */
class ForumpayRedirect extends OffsitePaymentGatewayBase
{

    /**
     * @var string null
     */
    private $oldApiKey = null;

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
                'api_url' => Config::PRODUCTION_URL,
                'api_user' => '',
                'api_key' => '',
                'pos_id' => 'drupal',
                'success_order_state' => 'completed',
                'accept_zero_confirmations' => true,
                'api_url_override' => '',

            ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $api_url = $this->configuration['api_url'] ?? Config::PRODUCTION_URL;
        $api_user = $this->configuration['api_user'] ?? '';
        $api_key = $this->configuration['api_key'] ?? '';
        $pos_id = $this->configuration['pos_id'] ?? 'drupal';
        $success_order_state = $this->configuration['success_order_state'] ?? 'completed';
        $api_url_override = $this->configuration['api_url_override'] ?? '';
        $accept_zero_confirmations = boolval($this->configuration['accept_zero_confirmations']);

        $form['pos_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('POS ID'),
            '#default_value' => $pos_id,
            '#description' => $this->t('Enter your webshop identifier (POS ID). Special characters not allowed. Allowed are: [A-Za-z0-9._-] Eg drupal-3'),
            '#required' => true,
        ];

        $form['api_url'] = [
            '#type' => 'select',
            '#title' => $this->t('Environment'),
            '#default_value' => $api_url,
            '#description' => $this->t('ForumPay environment.'),
            '#required' => true,
            "#options" => array(
                Config::PRODUCTION_URL => t("Production"),
                Config::SANDBOX_URL => t("Sandbox"),
            ),
        ];

        $form['api_user'] = [
            '#type' => 'textfield',
            '#title' => $this->t('API User'),
            '#default_value' => $api_user,
            '#description' => $this->t('You can generate API key in your ForumPay Account.'),
            '#required' => true,
        ];

        $form['api_key'] = [
            '#type' => 'password',
            '#title' => $this->t('API Secret'),
            '#default_value' => $api_key,
            '#placeholder' => '*****',
            '#description' => $this->t('You can generate API secret in your ForumPay Account.'),
            '#required' => false,
            '#always_empty' => false,
        ];

        $form['success_order_state'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Success Order Status'),
            '#default_value' => $success_order_state,
            '#description' => $this->t('Order status assigned to successful orders.'),
            '#required' => true,
        ];

        $form['api_url_override'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Custom environment URL'),
            '#default_value' => $api_url_override,
            '#description' => $this->t('URL to the api server. This value will override default environment.'),
            '#required' => false,
        ];

        $form['accept_zero_confirmations'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Accept Zero Confirmations'),
            '#default_value' => $accept_zero_confirmations,
            '#required' => false,
        ];

        $form['mode']['#access'] = false;
        $form_state->set('oldApiKey', $api_key);

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);

        $values = $form_state->getValue($form['#parents']);

        $url = $values['api_url_override'];
        if ($url && !UrlHelper::isValid($url, true)) {
            $form_state->setErrorByName(
                'api_url_override',
                $this->t('The URL is not valid.')
            );
        }

        $posId = $values['pos_id'];
        if (1 !== preg_match('/^[A-Za-z0-9._-]+$/', $posId)) {
            $form_state->setErrorByName(
                'pos_id',
                $this->t('POS ID field includes invalid characters. Allowed are: A-Za-z0-9._-')
            );
        }

        if (!$form_state->getErrors() && $form_state->isSubmitted()) {
            $values = $form_state->getValue($form['#parents']);

            $this->configuration['api_url'] = $values['api_url'];
            $this->configuration['api_user'] = $values['api_user'];

            $newApiKey = $values['api_key'];
            $existingApiKey = $form_state->get('oldApiKey');

            if (!empty($newApiKey) && (empty($existingApiKey) || $newApiKey !== $existingApiKey)) {
                $this->configuration['api_key'] = $newApiKey;
            } else {
                $this->configuration['api_key'] = $existingApiKey;
            }

            $this->configuration['api_url_override'] = $values['api_url_override'];
            $this->configuration['pos_id'] = $values['pos_id'];
            $this->configuration['success_order_state'] = $values['success_order_state'];
            $this->configuration['accept_zero_confirmations'] = (bool)$values['accept_zero_confirmations'];
            $this->configuration['mode'] = 'live';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);

            $this->configuration['api_url'] = $values['api_url'];
            $this->configuration['api_user'] = $values['api_user'];

            $newApiKey = $values['api_key'];
            $existingApiKey = $form_state->get('oldApiKey');

            if (!empty($newApiKey) && (empty($existingApiKey) || $newApiKey !== $existingApiKey)) {
                $this->configuration['api_key'] = $newApiKey;
            } else {
                $this->configuration['api_key'] = $existingApiKey;
            }

            $this->configuration['api_url_override'] = $values['api_url_override'];
            $this->configuration['pos_id'] = $values['pos_id'];
            $this->configuration['success_order_state'] = $values['success_order_state'];
            $this->configuration['accept_zero_confirmations'] = (bool)$values['accept_zero_confirmations'];
            $this->configuration['mode'] = 'live';
        }
    }
}
