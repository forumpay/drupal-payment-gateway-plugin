<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\OrderNotFoundException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Model\Data\CurrencyList;
use Drupal\commerce_forumpay\Model\Data\CurrencyList\Currency;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;

/**
 * @inheritdoc
 */
class GetCurrencyList
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

    public function execute(Request $request): ?CurrencyList
    {
        try {
            $this->logger->info('GetCurrencyList entrypoint called.');

            try {
                $orderId = $request->getRequired('orderId');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
                throw new OrderNotFoundException(1005);
            }

            $response = $this->forumPay->getCryptoCurrencyList($orderId);

            /** @var CurrencyList[] $currencyDtos */
            $currencyDtos = [];

            /** @var \ForumPay\PaymentGateway\PHPClient\Response\GetCurrencyList\Currency $currency */
            foreach ($response->getCurrencies() as $currency) {
                if ($currency->getStatus() !== 'OK') {
                    continue;
                }

                $currencyDto = new Currency(
                    $currency->getCurrency(),
                    $currency->getDescription(),
                    $currency->getSellStatus(),
                    (bool)$currency->getZeroConfirmationsEnabled(),
                    $currency->getCurrencyFiat(),
                    $currency->getIconUrl(),
                    $currency->getRate()
                );
                $currencyDtos[] = $currencyDto;
            }

            $this->logger->debug('GetCurrencyList response.', ['response' => $currencyDtos]);
            $this->logger->info('GetCurrencyList entrypoint finished.');

            return new CurrencyList($currencyDtos);
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ApiHttpException($e, 1050);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 1100, $e);
        }
    }
}
