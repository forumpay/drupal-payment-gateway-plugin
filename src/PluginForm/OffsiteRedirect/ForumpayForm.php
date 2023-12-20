<?php

namespace Drupal\commerce_forumpay\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class ForumpayForm extends PaymentOffsiteForm
{
    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;

        $order = $payment->getOrder();
        $paymentMachineName = $order->get('payment_gateway')->first()->entity->getOriginalId();
        $param = array('commerce_payment_gateway' => $paymentMachineName);

        $payurl = Url::fromRoute('commerce_forumpay.pay', $param, ['absolute' => true])->toString();

        $rData = array();
        $rData['orderid'] = $payment->getOrderID();
        $rData['return_url'] = $form['#return_url'];
        $rData['cancel_url'] = $form['#cancel_url'];

        return $this->buildRedirectForm(
            $form,
            $form_state,
            $payurl,
            $rData,
            PaymentOffsiteForm::REDIRECT_POST
        );
    }

    /**
     * Return notify url
     * {@inheritdoc}
     */
    public function getNotifyUrl($paymentName)
    {
        $url = \Drupal::request()->getSchemeAndHttpHost() . '/payment/notify/' . $paymentName;
        return $url;
    }
}
