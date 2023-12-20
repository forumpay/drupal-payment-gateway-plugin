<?php

namespace Drupal\commerce_forumpay\Model\Payment;

use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Response\RequestKycResponse;
use Drupal\commerce_forumpay\Exception\ForumPayException;
use ForumPay\PaymentGateway\PHPClient\Response\GetTransactions\TransactionInvoice;
use ForumPay\PaymentGateway\PHPClient\PaymentGatewayApi;
use ForumPay\PaymentGateway\PHPClient\PaymentGatewayApiInterface;
use ForumPay\PaymentGateway\PHPClient\Response\CheckPaymentResponse;
use ForumPay\PaymentGateway\PHPClient\Response\GetCurrencyListResponse;
use ForumPay\PaymentGateway\PHPClient\Response\GetRateResponse;
use ForumPay\PaymentGateway\PHPClient\Response\StartPaymentResponse;
use Drupal\commerce_forumpay\Config;
use Psr\Log\LoggerInterface;

/**
 * ForumPay payment method model
 */
class ForumPay
{
    /**
     * @var PaymentGatewayApiInterface
     */
    private PaymentGatewayApiInterface $apiClient;

    /**
     * @var OrderManager
     */
    private OrderManager $orderManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $psrLogger;

    /**
     * @var Config
     */
    private Config  $config;

    public function __construct(
        OrderManager $orderManager,
        LoggerInterface $psrLogger,
        Config $config
    ) {
        $this->apiClient = new PaymentGatewayApi(
            $config->getApiUrl(),
            $config->getMerchantApiUser(),
            $config->getMerchantApiSecret(),
            sprintf(
                "fp-pgw[%s] Drupal [%s] Commerce [%s] on PHP %s",
                $config->getVersion(),
                $config->getDrupalVersion(),
                $config->getDrupalCommerceVersion(),
                phpversion()
            ),
            $config->getStoreLocale(),
            null,
            $psrLogger
        );

        $this->orderManager = $orderManager;
        $this->psrLogger = $psrLogger;
        $this->config = $config;
    }

    /**
     * Return the list of all available currencies as defined on merchant account
     *
     * @param string $orderId
     * @return GetCurrencyListResponse
     * @throws \Exception
     */
    public function getCryptoCurrencyList(string $orderId): GetCurrencyListResponse
    {
        $currency = $this->orderManager->getOrderCurrency($orderId);

        if (empty($currency)) {
            throw new \Exception('Store currency could not be determined');
        }

        return $this->apiClient->getCurrencyList($currency);
    }

    /**
     * Get rate for a requested currency
     *
     * @param string $orderId
     * @param string $currency
     * @return GetRateResponse
     * @throws \Exception
     */
    public function getRate(string $orderId, string $currency): GetRateResponse
    {
        $order = $this->orderManager->getOrder($orderId);
        if (!$order) {
            throw new \Exception("Order is not active. Order is already created.");
        }

        return $this->apiClient->getRate(
            $this->config->getPosId(),
            $this->orderManager->getOrderCurrency($orderId),
            $this->orderManager->getOrderTotal($orderId),
            $currency,
            1 || $this->config->isAcceptZeroConfirmations() ? 'true' : 'false',
            null,
            null,
            null
        );
    }

    /**
     * @param string $orderId
     * @return RequestKycResponse
     * @throws ApiExceptionInterface
     */
    public function requestKyc(string $orderId): RequestKycResponse
    {
        return $this->apiClient->requestKyc($this->orderManager->getOrderCustomerEmail($orderId));
    }

    /**
     * Initiate a start payment and create order on ForumPay
     *
     * @param string $orderId
     * @param string $currency
     * @param string $paymentId
     * @param string|null $kycPin
     * @return StartPaymentResponse
     * @throws ApiExceptionInterface
     */
    public function startPayment(
        string $orderId,
        string $currency,
        string $paymentId,
        ?string $kycPin
    ): StartPaymentResponse
    {
        $response = $this->apiClient->startPayment(
            $this->config->getPosId(),
            $this->orderManager->getOrderCurrency($orderId),
            $paymentId,
            $this->orderManager->getOrderTotal($orderId),
            $currency,
            $orderId,
            $this->config->isAcceptZeroConfirmations() ? 'true' : 'false',
            $this->orderManager->getOrderCustomerIpAddress($orderId),
            $this->orderManager->getOrderCustomerEmail($orderId),
            $this->orderManager->getOrderCustomerId($orderId),
            'false',
            '',
            'false',
            null,
            null,
            null,
            null,
            null,
            $kycPin
        );

        $this->orderManager->saveOrderMetaData($orderId, 'startPayment', $response->toArray());
        $this->orderManager->saveOrderMetaData($orderId, 'payment_formumpay_paymentId_last', $paymentId, true);

        $this->cancelAllPayments($orderId, $response->getPaymentId());

        return $response;
    }

    /**
     * Get detailed payment information for ForumPay
     *
     * @param string $paymentId
     * @return CheckPaymentResponse
     * @throws ForumPayException
     */
    public function checkPayment(string $orderId, string $paymentId): CheckPaymentResponse
    {
        $meta = $this->getStartPaymentMetaData($orderId, $paymentId);

        $address = $meta['address'];
        $cryptoCurrency = $meta['currency'];

        $response = $this->apiClient->checkPayment(
            $this->config->getPosId(),
            $cryptoCurrency,
            $paymentId,
            $address
        );

        if (strtolower($response->getStatus()) === 'cancelled') {
            if (!$this->checkAllPaymentsAreCanceled($orderId)) {
                return $response;
            }
        }

        $this->orderManager->updateOrderStatus($orderId, $response->getStatus(), $paymentId, $this->config->getOrderStatusAfterPayment());
        $this->orderManager->saveOrderMetaData($orderId, 'payment_formumpay_paymentId_last', $paymentId, true);
        $this->orderManager->saveOrderMetaData($orderId, 'payment_formumpay_checkpayment_last', $response->toArray(), true);

        return $response;
    }

    /**
     * Cancel give payment on ForumPay
     *
     * @param string $orderId
     * @param string $paymentId
     * @param string $reason
     * @param string $description
     */
    public function cancelPaymentByPaymentId(string $orderId, string $paymentId, string $reason = '', string $description = '')
    {
        $meta = $this->getStartPaymentMetaData($orderId, $paymentId);
        $currency = $meta['currency'];
        $address = $meta['address'];
        $this->cancelPayment($paymentId, $currency, $address, $reason, $description);
    }

    /**
     * Cancel give payment on ForumPay
     *
     * @param string $paymentId
     * @param string $currency
     * @param string $address
     */
    public function cancelPayment(string $paymentId, string $currency, string $address, string $reason = '', string $description = '')
    {
        $this->apiClient->cancelPayment(
            $this->config->getPosId(),
            $currency,
            $paymentId,
            $address,
            $reason,
            substr($description, 0, 255),
        );
    }

    /**
     * Cancel all except existingPayment on ForumPay
     *
     * @param string $orderId
     * @param $existingPaymentId
     */
    private function cancelAllPayments(string $orderId, $existingPaymentId)
    {
        $existingPayments = $this->apiClient->getTransactions(null, null, $orderId);

        /** @var TransactionInvoice $existingPayment */
        foreach ($existingPayments->getInvoices() as $existingPayment) {

            if (
                $existingPayment->getPaymentId() === $existingPaymentId
                || strtolower($existingPayment->getStatus()) !== 'waiting'
            ) {
                //newly created
                continue;
            }

            $this->cancelPayment(
                $existingPayment->getPaymentId(),
                $existingPayment->getCurrency(),
                $existingPayment->getAddress()
            );
        }
    }

    /**
     * Check if all payments for a given order are canceled on ForumPay
     *
     * @param string $orderId
     * @return bool
     */
    private function checkAllPaymentsAreCanceled(string $orderId): bool
    {
        $existingPayments = $this->apiClient->getTransactions(null, null, $orderId);

        /** @var TransactionInvoice $existingPayment */
        foreach ($existingPayments->getInvoices() as $existingPayment) {
            if (
                strtolower($existingPayment->getStatus()) !== 'cancelled'
                && $existingPayment->getPosId() === $this->config->getPosId()
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get return startPayment response from metadata for given paymentId
     *
     * @param string $orderId
     * @param string $paymentId
     * @return array
     */
    private function getStartPaymentMetaData(string $orderId, string $paymentId): ?array
    {
        $startPaymentResponses = $this->orderManager->getOrderMetaData($orderId, 'startPayment');

        /** @var WC_Meta_Data $response */
        foreach ($startPaymentResponses as $response) {
            if ($response['payment_id'] === $paymentId) {
                return $response;
            }
        }

        return null;
    }
}
