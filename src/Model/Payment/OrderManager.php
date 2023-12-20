<?php

namespace Drupal\commerce_forumpay\Model\Payment;

use Drupal\commerce_price\Price;
use Drupal\commerce_order\Entity\Order;

/**
 * Manages internal states of the order and provides
 * and interface for dealing with Drupal internal
 */
class OrderManager
{
    /**
     * Get order by order id from db
     *
     * @param string $orderId
     * @return Order
     */
    public function getOrder(string $orderId): Order
    {
        return Order::load($orderId);
    }

    /**
     * Get currency customer used when creating order
     *
     * @param $orderId
     * @return string
     */
    public function getOrderCurrency($orderId)
    {
        $order = $this->getOrder($orderId);
        return $order->getTotalPrice()->getCurrencyCode();
    }

    /**
     * Get order total by order id from db
     *
     * @param $orderId
     * @return string
     */
    public function getOrderTotal($orderId)
    {
        $order = $this->getOrder($orderId);
        return $order->getTotalPrice()->getNumber();
    }

    /**
     * Get customer IP address that was used when order is created
     *
     * @param $orderId
     * @return string
     */
    public function getOrderCustomerIpAddress($orderId)
    {
        $remoteAddressesList = [];
        $request = \Drupal::request();

        $remoteAddressesList += [
            $this->getOrder($orderId)->getIpAddress(),
            $request->headers->get('X-Real-IP')
            ?? $request->headers->get('X-Forwarded-For')
            ?? $request->getClientIp()
        ];

        if ($request->headers->get('HTTP_X_REAL_IP')) {
            $remoteAddressesList += preg_split("/,/", $request->headers->get('HTTP_X_REAL_IP'), -1, PREG_SPLIT_NO_EMPTY);
        }

        if ($request->headers->get('HTTP_X_FORWARDED_FOR')) {
            $remoteAddressesList += preg_split("/,/", $request->headers->get('HTTP_X_FORWARDED_FOR'), -1, PREG_SPLIT_NO_EMPTY);
        }

        if ($request->headers->get('REMOTE_ADDR')) {
            $remoteAddressesList += preg_split("/,/", $request->headers->get('REMOTE_ADDR'), -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!count($remoteAddressesList)) {
            return '';
        }

        foreach ($remoteAddressesList as $remoteAddress) {
            if (filter_var(
                $remoteAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                return $remoteAddress;
            }
        }

        return $remoteAddressesList[0];
    }

    /**
     * Get customer email address that was used when order is created
     *
     * @param $orderId
     * @return string
     */
    public function getOrderCustomerEmail($orderId)
    {
        $order = $this->getOrder($orderId);

        if ($order) {
            $customer_user_id = $order->getCustomerId();
            $customer_user = \Drupal\user\Entity\User::load($customer_user_id);
            if ($customer_user) {
                return $customer_user->getEmail();
            }
        }

        if ($order && $order->hasField('billing_profile')) {
            $billing_profile = $order->get('billing_profile')->entity;
            if ($billing_profile && $billing_profile->hasField('email')) {
                return $billing_profile->get('email')->value;
            }
        }

        return '';
    }

    /**
     * Get customer ID if registered customer or construct one for guests
     *
     * @param $orderId
     * @return int|string
     */
    public function getOrderCustomerId($orderId)
    {
        $order = $this->getOrder($orderId);

        return $order->getCustomerId() != false
            ? $order->getCustomerId()
            : sprintf('guest_%s', $orderId);
    }

    /**
     * Update order with new status
     *
     * @param $orderId
     * @param $newStatus
     * @param $paymentId
     * @param $completedOrderState
     */
    public function updateOrderStatus($orderId, $newStatus, $paymentId, $completedOrderState)
    {
        $order = $this->getOrder($orderId);

        if (strtolower($newStatus) === 'confirmed') {
            $paymentStorage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
            $transactionArray = $paymentStorage->loadByProperties(['order_id' => $orderId]);

            $paymentGateway = $order->payment_gateway->entity;

            if (!empty($transactionArray)) {
                $transaction = array_shift($transactionArray);
            } else {
                $transaction = $paymentStorage->create([
                    'payment_gateway' => $paymentGateway->get('id'),
                    'order_id' => $orderId,
                    'remote_id' => $paymentId,
                ]);
            }

            $transaction->setRemoteId($paymentId);
            $transaction->setRemoteState('completed');
            $transaction->setState('completed');
            $price = new Price($this->getOrderTotal($orderId), $this->getOrderCurrency($orderId));
            $transaction->setAmount($price);
            $paymentStorage->save($transaction);

            $order->set('state', $completedOrderState);
            $order->set('checkout_step', 'complete');
            $order->set('completed', REQUEST_TIME);
            $order->set('placed', REQUEST_TIME);
            $order->set('cart', false);

            $order->save();
            $this->saveOrderMetaData($orderId, 'payment_formumpay_paymentId', $paymentId, true);
        } else if (strtolower($newStatus) === 'cancelled') {
            $order->set('state', 'draft');
            $order->save();
        }
    }

    /**
     * Save metadata to order
     *
     * @param $orderId
     * @param $key
     * @param $data
     * @param false $unique
     */
    public function saveOrderMetaData($orderId, $key, $data, $unique = false)
    {
        $order = $this->getOrder($orderId);
        $metaData = [];
        if ($order && $order->hasField('commerce_forumpay_metadata')) {
            $metaData = json_decode(
                $order->get('commerce_forumpay_metadata')->value ?? '[]',
                true
            );
        }

        if ($unique) {
            $metaData[$key] = [$data];
        } else {
            $metaData[$key] = $metaData[$key] ?? [];
            $metaData[$key][] = $data;
        }

        $order->set(
            'commerce_forumpay_metadata',
            json_encode($metaData)
        );

        $order->save();
    }

    /**
     * Fetch metadata from order
     *
     * @param $orderId
     * @param $key
     * @return mixed
     */
    public function getOrderMetaData($orderId, $key)
    {
        $order = $this->getOrder($orderId);

        if ($order && $order->hasField('commerce_forumpay_metadata')) {
            $metaData = json_decode(
                $order->get('commerce_forumpay_metadata')->value,
                true
            );

            return $metaData[$key] ?? [];
        }

        return [];
    }
}
