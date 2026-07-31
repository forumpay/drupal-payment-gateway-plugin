<?php

namespace Drupal\commerce_forumpay\Model\Data;

/**
 * Dto for ping response
 */

class Ping
{
    /**
     * Message
     *
     * @var string
     */
    private string $message;

    /**
     * Webhook Success
     *
     * @var string|null
     */
    private ?string $webhookSuccess;

    /**
     * Webhook Ping Response
     *
     * @var WebhookPingResponse|null
     */
    private ?WebhookPingResponse $webhookPingResponse;

    /**
     * Ping DTO constructor
     *
     */
    public function __construct(
        string $message,
        ?string $webhookSuccess = null,
        ?WebhookPingResponse $webhookPingResponse = null
    ) {
        $this->message = $message;
        $this->webhookSuccess = $webhookSuccess;
        $this->webhookPingResponse = $webhookPingResponse;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return string|null
     */
    public function getWebhookSuccess(): ?string
    {
        return $this->webhookSuccess;
    }

    /**
     * @return WebhookPingResponse|null
     */
    public function getWebhookPingResponse(): ?WebhookPingResponse
    {
        return $this->webhookPingResponse;
    }

    /**
     * Return empty array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'webhookSuccess' => $this->webhookSuccess,
            'webhookPingResponse' => $this->webhookPingResponse?->toArray(),
        ];
    }
}
