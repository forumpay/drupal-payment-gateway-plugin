<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\OrderNotFoundException;
use Drupal\commerce_forumpay\Exception\TransactionDetailsMissingException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\Data\PaymentDetails;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Response\CheckPaymentResponse;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Model\Data\PaymentDetails\Underpayment;

/**
 * @inheritdoc
 */
class CheckPayment
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

    public function execute(Request $request): PaymentDetails
    {
        try {
            try {
                $orderId = $request->getRequired('orderId');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
                throw new OrderNotFoundException(4005);
            }
            $paymentId = $request->getRequired('payment_id');
            $this->logger->info('CheckPayment entrypoint called.', ['paymentId' => $paymentId]);

            /** @var CheckPaymentResponse $response */
            $response = $this->forumPay->checkPayment($orderId, $paymentId);

            if ($response->getUnderpayment()) {
                $underPayment = new Underpayment(
                    $response->getUnderpayment()->getAddress(),
                    $response->getUnderpayment()->getMissingAmount(),
                    $response->getUnderpayment()->getQr(),
                    $response->getUnderpayment()->getQrAlt(),
                    $response->getUnderpayment()->getQrImg(),
                    $response->getUnderpayment()->getQrAltImg()
                );
                $this->logger->debug('CheckPayment - Underpayment.', ['paymentId' => $paymentId]);
            }

            $paymentDetails = new PaymentDetails(
                $response->getReferenceNo(),
                $response->getInserted(),
                $response->getInvoiceAmount(),
                $response->getType(),
                $response->getInvoiceCurrency(),
                $response->getAmount(),
                $response->getMinConfirmations(),
                $response->isAcceptZeroConfirmations(),
                $response->isRequireKytForConfirmation(),
                $response->getCurrency(),
                $response->isConfirmed(),
                $response->getConfirmedTime(),
                $response->getReason(),
                $response->getPayment(),
                $response->getSid(),
                $response->getConfirmations(),
                $response->getAccessToken(),
                $response->getAccessUrl(),
                $response->getWaitTime(),
                $response->getStatus(),
                $response->isCancelled(),
                $response->getCancelledTime(),
                $response->getPrintString(),
                $response->getState(),
                $underPayment ?? null,
            );

            $this->logger->info('CheckPayment entrypoint finished.');
            return $paymentDetails;
        } catch (TransactionDetailsMissingException $e) {
            $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new TransactionDetailsMissingException($e->getMessage(), 4006, $e);
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ApiHttpException($e, 4050);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 4100, $e);
        }
    }
}
