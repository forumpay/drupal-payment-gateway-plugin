<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\ForumPayException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\Data\WebhookTest;
use Drupal\commerce_forumpay\Request;
use Drupal\Component\Utility\Crypt;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Response\CheckPaymentResponse;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;

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
     * return WebhookTest|null
     */
    public function execute(Request $request): ?WebhookTest
    {
        try {
            $request->getRequired('webhook_test');
            return new WebhookTest(Crypt::hashBase64($this->forumPay->getInstanceIdentifier()));
        } catch (\InvalidArgumentException $e) { }

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
            $this->forumPay->checkPayment($orderId, $paymentId, true);

            $this->logger->info('Webhook entrypoint finished.');

            return null;
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ApiHttpException($e, 6050);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 6100, $e);
        }
    }
}
