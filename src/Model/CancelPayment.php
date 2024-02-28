<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\OrderNotFoundException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;

/**
 * @inheritdoc
 */
class CancelPayment
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

    public function execute(Request $request): void
    {
        try {
            try {
                $orderId = $request->getRequired('orderId');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
                throw new OrderNotFoundException(5005);
            }
            $paymentId = $request->getRequired('payment_id');
            $reason = $request->get('reason', '');
            $description = $request->get('description', '');

            $this->logger->info('CancelPayment entrypoint called.', ['paymentId' => $paymentId]);

            $this->forumPay->cancelPaymentByPaymentId($orderId, $paymentId, $reason, $description);

            $this->logger->info('CancelPayment entrypoint finished.');
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ApiHttpException($e, 5050);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 5100, $e);
        }
    }
}
