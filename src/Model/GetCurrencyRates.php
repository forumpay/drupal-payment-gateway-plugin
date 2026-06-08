<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\OrderNotFoundException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use Drupal\commerce_forumpay\Model\Data\Rates;

/**
 * Get rates for multiple cryptocurrencies
 */
class GetCurrencyRates
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

    public function execute(Request $request): Rates
    {
        try {
            try {
                $orderId = $request->getRequired('orderId');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
                throw new OrderNotFoundException(2005);
            }

            $currencies = $request->getRequired('currencies');
            $this->logger->info('GetCurrencyRates entrypoint called.', ['currencies' => $currencies]);

            $response = $this->forumPay->getRates($orderId, $currencies);

            // Response is now a typed GetRatesResponse object
            $rates = new Rates(
                $response->getPaymentId(),
                $response->getInvoiceAmount(),
                $response->getInvoiceCurrency(),
                $response->getSid(),
                $response->getCurrencies()
            );

            $this->logger->info('GetCurrencyRates entrypoint finished.');

            return $rates;
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ApiHttpException($e, 2050);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 2100, $e);
        }
    }
}
