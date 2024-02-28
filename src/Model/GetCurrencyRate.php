<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\OrderNotFoundException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Response\GetRateResponse;
use Drupal\commerce_forumpay\Model\Data\Rate;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;

/**
 * @inheritdoc
 */
class GetCurrencyRate
{
    /**
     * ForumPay payment model
     *
     * @var ForumPay
     */
    private ForumPay $forumPay;

    /**
     * @var ForumPayLogger
     */
    private ForumPayLogger $logger;

    /**
     * Constructor
     *
     * @param ForumPay $forumPay
     * @param ForumPayLogger $logger
     */
    public function __construct(
        ForumPay $forumPay,
        ForumPayLogger $logger
    ) {
        $this->forumPay = $forumPay;
        $this->logger = $logger;
    }

    public function execute(Request $request): Rate
    {
        try {
            try {
                $orderId = $request->getRequired('orderId');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
                throw new OrderNotFoundException(2005);
            }

            $currency = $request->getRequired('currency');
            $this->logger->info('GetCurrencyRate entrypoint called.', ['currency' => $currency]);

            /** @var GetRateResponse $response */
            $response = $this->forumPay->getRate($orderId, $currency);

            $rate = new Rate(
                $response->getInvoiceCurrency(),
                $response->getInvoiceAmount(),
                $response->getCurrency(),
                $response->getRate(),
                $response->getAmountExchange(),
                $response->getNetworkProcessingFee(),
                $response->getAmount(),
                $response->getWaitTime(),
                $response->getSid(),
                $response->getFastTransactionFee(),
                $response->getFastTransactionFeeCurrency(),
                $response->getPaymentId()
            );

            $this->logger->info('GetCurrencyRate entrypoint finished.');

            return $rate;
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ApiHttpException($e, 2050);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 2100, $e);
        }
    }
}
