<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\ForumPayException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Response\CheckPaymentResponse;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;

/**
 * @inheritdoc
 */
class Webhook
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

    /**
     * @inheritdoc
     *
     */
    public function execute(Request $request): void
    {
        try {
            try {
                $paymentId = $request->getRequired('payment_id');
                $orderId = $request->getRequired('reference_no');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
                throw new ForumPayException($e->getMessage(),6005, $e);
            }

            $this->logger->info('Webhook entrypoint called.', ['paymentId' => $paymentId, 'orderId' => $orderId]);

            /** @var CheckPaymentResponse $response */
            $this->forumPay->checkPayment($orderId, $paymentId);

            $this->logger->info('Webhook entrypoint finished.');
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ApiHttpException($e, 6050);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 6100, $e);
        }
    }
}
