<?php

namespace Drupal\commerce_forumpay\Model\Payment;

use Drupal\commerce_forumpay\Model\Data\Payer\Payer;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Response\PingResponse;
use ForumPay\PaymentGateway\PHPClient\Response\RequestKycResponse;
use Drupal\commerce_forumpay\Exception\ForumPayException;
use ForumPay\PaymentGateway\PHPClient\Response\GetTransactions\TransactionInvoice;
use ForumPay\PaymentGateway\PHPClient\PaymentGatewayApi;
use ForumPay\PaymentGateway\PHPClient\PaymentGatewayApiInterface;
use ForumPay\PaymentGateway\PHPClient\Response\CheckPaymentResponse;
use ForumPay\PaymentGateway\PHPClient\Response\GetCurrencyListResponse;
use ForumPay\PaymentGateway\PHPClient\Response\GetRateResponse;
use ForumPay\PaymentGateway\PHPClient\Response\GetWalletAppsResponse;
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
        $this->orderManager = $orderManager;
        $this->psrLogger = $psrLogger;
        $this->config = $config;

        $this->apiClient = $this->initApiClient(
            $config->getApiUrl(),
            $config->getMerchantApiUser(),
            $config->getMerchantApiSecret(),
        );
    }

    /**
     * Get instance identifier
     *
     * @return string
     */
    public function getInstanceIdentifier(): string
    {
        return sprintf(
            '%s-%s-%s-%s',
            'forumpay-drupal',
            $this->config->getVersion(),
            $this->config->getDrupalVersion(),
            $this->config->getDrupalCommerceVersion(),
        );
    }

    /**
     * Ping api to check configuration
     *
     * @param string $apiEnv
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $apiUrlOverride
     * @param string $webhookUrl
     * @return PingResponse
     */
    public function ping(
        string $apiEnv,
        string $apiKey,
        string $apiSecret,
        string $apiUrlOverride,
        string $webhookUrl
    ): PingResponse
    {
        if (!$apiSecret) {
            $apiSecret = $this->config->getMerchantApiSecret();
        }

        return $this->initApiClient(
            $apiUrlOverride ?: $apiEnv,
            $apiKey,
            $apiSecret,
        )->ping($webhookUrl);
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
     * Get rates for multiple currencies
     *
     * @param string $orderId
     * @param string $currencies Comma-separated list of currency codes (e.g., "BTC,ETH")
     * @return mixed
     * @throws \Exception
     */
    public function getRates(string $orderId, string $currencies)
    {
        $order = $this->orderManager->getOrder($orderId);
        if (!$order) {
            throw new \Exception("Order is not active. Order is already created.");
        }

        return $this->apiClient->getRates(
            $this->config->getPosId(),
            $this->orderManager->getOrderCurrency($orderId),
            $this->orderManager->getOrderTotal($orderId),
            $currencies,
            $this->config->isAcceptZeroConfirmations() ? 'true' : 'false',
            null,
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
        ?string $kycPin,
        ?Payer $payer
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
            $this->orderManager->getOrderCustomerId($orderId)  . '_' . $this->config->getInstallationId(),
            $this->config->getAcceptUnderpayment() ? 'true' : 'false',
            $this->calculateMinimumOrderValue(
                $this->config->getAcceptUnderpayment(),
                $this->config->getAcceptUnderpaymentThreshold(),
                $orderId
            ),
            $this->config->getAcceptOverpayment() ? 'true' : 'false',
            null,
            null,
            null,
            null,
            null,
            $kycPin,
            $this->config->getAcceptLatePayment() ? 'true' : 'false',
            $this->config->getWebhookUrl(),
            null,
            null,
            $this->calculateMaximumOrderValue(
                $this->config->getAcceptOverpayment(),
                $this->config->getAcceptOverpaymentThreshold(),
                $orderId
            ),
            $payer?->toArray(),
            $this->config->getNetworkProcessingFeePaidBy() ?: null,
        );

        $this->orderManager->saveOrderMetaData($orderId, 'startPayment', $response->toArray());
        $this->orderManager->saveOrderMetaData($orderId, 'payment_formumpay_paymentId_last', $paymentId, true);

        $this->cancelAllPayments($orderId, $response->getPaymentId());

        return $response;
    }

    /**
     * Get detailed payment information for ForumPay
     *
     * @param string $orderId
     * @param string $paymentId
     * @param bool $webhookUsed
     * @return CheckPaymentResponse
     *
     * @throws ApiExceptionInterface
     */
    public function checkPayment(string $orderId, string $paymentId, bool $webhookUsed = false): CheckPaymentResponse
    {
        if ($webhookUsed) {
            $this->orderManager->saveOrderMetaData($orderId, 'payment_formumpay_webhook_used', date('Y-m-d h:i:sa'));
        }

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

        $this->orderManager->updateOrderStatus(
            $orderId,
            $response->getStatus(),
            $paymentId,
            $this->config->getOrderStatusAfterPayment(),
            $response->getInvoiceAmount(),
            ($this->config->getAcceptUnderpayment() && $this->config->getAcceptUnderpaymentModifyOrderTotal())
                ? $this->config->getAcceptUnderpaymentModifyOrderTotalDescription()
                : '',
            ($this->config->getAcceptOverpayment() && $this->config->getAcceptOverpaymentModifyOrderTotal())
                ? $this->config->getAcceptOverpaymentModifyOrderTotalDescription()
                : '',
        );
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

        foreach ($startPaymentResponses as $response) {
            if ($response['payment_id'] === $paymentId) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Calculate Minimum Order Value
     *
     * @param bool $acceptUnderpayment
     * @param int|string $acceptUnderpaymentThreshold
     * @param string $orderId
     *
     * @return float|string
     */
    private function calculateMinimumOrderValue(
        bool $acceptUnderpayment,
        int|string $acceptUnderpaymentThreshold,
        string $orderId
    ): float|string
    {
        if (!$acceptUnderpayment || $acceptUnderpaymentThreshold === '') {
            return '';
        }

        $maximumMissingValuePercentage = $acceptUnderpaymentThreshold;
        $total = $this->orderManager->getOrderTotal($orderId);
        $percentage = floatval($maximumMissingValuePercentage);
        $minimumOrderValue = (1 - $percentage / 100) * $total;

        return (string) round($minimumOrderValue, 2);
    }

    /**
     * Calculate Maximum Order Value
     *
     * @param bool $acceptOverpayment
     * @param int|string $acceptOverpaymentThreshold
     * @param string $orderId
     *
     * @return float|string
     */
    private function calculateMaximumOrderValue(
        bool $acceptOverpayment,
        int|string $acceptOverpaymentThreshold,
        string $orderId
    ): float|string
    {
        if (!$acceptOverpayment || $acceptOverpaymentThreshold === '') {
            return '';
        }

        $total = $this->orderManager->getOrderTotal($orderId);
        $percentage = floatval($acceptOverpaymentThreshold);
        $maximumOrderValue = (1 + $percentage / 100) * $total;

        return round($maximumOrderValue, 2);
    }

    /**
     * Get list of available wallet apps
     *
     * @return GetWalletAppsResponse
     * @throws ApiExceptionInterface
     */
    public function getWalletApps(): GetWalletAppsResponse
    {
        return $this->apiClient->getWalletApps();
    }

    private function initApiClient($apiUrl, $apiUser, $apiSecret): PaymentGatewayApiInterface
    {
        return new PaymentGatewayApi(
            $apiUrl,
            $apiUser,
            $apiSecret,
            sprintf(
                "fp-pgw[%s] %s %s %s on PHP",
                $this->config->getVersion(),
                $this->config->getDrupalVersion(),
                $this->config->getDrupalCommerceVersion(),
                phpversion()
            ),
            $this->config->getStoreLocale(),
            null,
            $this->psrLogger
        );
    }
}
