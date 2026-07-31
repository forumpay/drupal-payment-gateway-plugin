<?php

namespace Drupal\commerce_forumpay\Model;

use Drupal\commerce_forumpay\Exception\ForumPayException;
use Drupal\commerce_forumpay\Exception\ForumPayHttpException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\Data\WebhookPingResponse;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Request;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\ApiExceptionInterface;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\InvalidApiResponseException;
use ForumPay\PaymentGateway\PHPClient\Http\Exception\InvalidResponseStatusCodeException;
use Drupal\Component\Utility\Crypt;

class Ping
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

    public function execute(Request $request): ?\Drupal\commerce_forumpay\Model\Data\Ping
    {
        try {
            $this->logger->info('Ping entrypoint called.');

            try {
                $apiEnv = $request->getRequired('apiEnv');
                $apiUrlOverride = $request->getRequired('apiUrlOverride');
                $webhookUrl = $request->getRequired('webhookUrl');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error($e->getMessage(), $e->getTrace());
                throw new ForumPayException('There has been an error, check Drupal logs for more.');
            }

            $apiKey = (string) $request->get('apiKey', '');
            $apiSecret = (string) $request->get('apiSecret', '');

            $response = $this->forumPay->ping($apiEnv, $apiKey, $apiSecret, $apiUrlOverride, $webhookUrl);
            $this->logger->debug('Ping response.', ['response' => $response->toArray()]);
            $this->logger->info('Ping entrypoint finished.');

            $webhookPingResult = $response->getWebhookResult();

            if ($webhookPingResult) {
                $decoded = json_decode($webhookPingResult['response_body'] ?? '');
                $responseBody = (is_object($decoded) && isset($decoded->message))
                    ? $decoded->message
                    : ($webhookPingResult['response_body'] ?? null);

                $webhookPing = new WebhookPingResponse(
                    $webhookPingResult['status'],
                    $webhookPingResult['duration'],
                    $webhookPingResult['webhook_url'],
                    $webhookPingResult['response_code'],
                    $responseBody,
                );

                $webhookSuccess = $webhookPingResult['status'] === 'ok'
                    && Crypt::hashBase64($this->forumPay->getInstanceIdentifier()) === $webhookPing->getResponseBody();

                return new \Drupal\commerce_forumpay\Model\Data\Ping(
                    'OK',
                    $webhookSuccess ? 'OK' : 'FAILED',
                    $webhookPing
                );
            }

            return new \Drupal\commerce_forumpay\Model\Data\Ping('OK');

        } catch (ForumPayException $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
            throw $e;
        } catch (InvalidApiResponseException $e) {
            $this->logger->logApiException($e);
            throw new ForumPayHttpException($e->getMessage(), intval(0));
        } catch (InvalidResponseStatusCodeException $e) {
            $this->logger->logApiException($e);
            throw new ForumPayHttpException($e->getMessage(),$e->getResponseStatusCode() ?? 500);
        } catch (ApiExceptionInterface $e) {
            $this->logger->logApiException($e);
            throw new ForumPayHttpException($e->getMessage(),$e->getCode() ?? 500);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), $e->getTrace());
            throw new \Exception($e->getMessage(), 500, $e);
        }
    }
}
