<?php

namespace Drupal\commerce_forumpay\Model;

use ForumPay\PaymentGateway\PHPClient\Response\RequestKycResponse;
use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\OrderNotFoundException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\Data\Payment;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Response\StartPaymentResponse;

class StartPayment
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
     * @throws OrderNotFoundException
     * @throws ApiExceptionInterface
     * @throws ApiHttpException
     * @return Payment|RequestKycResponse
     */
    public function execute(Request $request)
    {
        try {
            $orderId = $request->getRequired('orderId');
        } catch (\InvalidArgumentException $e) {
            $this->logger->error($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new OrderNotFoundException(3005);
        }

        try {
            $currency = $request->getRequired('currency');
            $kyc = $request->get('kycPin');

            $this->logger->info('StartPayment entrypoint called.', ['currency' => $currency]);

            /** @var StartPaymentResponse $response */
            $response = $this->forumPay->startPayment($orderId, $currency, '', $kyc);

            $notices = [];
            foreach ($response->getNotices() as $notice) {
                $notices[] = new Payment\Notice($notice['code'], $notice['message']);
            }

            $payment = new Payment(
                $response->getPaymentId(),
                $response->getAddress(),
                '',
                $response->getMinConfirmations(),
                $response->getFastTransactionFee(),
                $response->getFastTransactionFeeCurrency(),
                $response->getQr(),
                $response->getQrAlt(),
                $response->getQrImg(),
                $response->getQrAltImg(),
                $notices
            );

            $this->logger->info('StartPayment entrypoint finished.');

            return $payment;
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            $errorCode = $e->getErrorCode();

            if ($errorCode === null) {
                throw new ApiHttpException($e, 3050);
            }

            if (
                $errorCode === 'payerAuthNeeded' ||
                $errorCode === 'payerKYCNotVerified' ||
                $errorCode === 'payerKYCNeeded' ||
                $errorCode === 'payerEmailVerificationCodeNeeded'
            ) {
                $this->forumPay->requestKyc($orderId);
                throw new ApiHttpException($e, 3051);
            } elseif (substr($errorCode, 0, 5) === 'payer') {
                throw new ApiHttpException($e, 3052);
            } else {
                throw new ApiHttpException($e, 3050);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ForumPayLogger::exceptionToContext($e));
            throw new \Exception($e->getMessage(), 3100, $e);
        }
    }
}
