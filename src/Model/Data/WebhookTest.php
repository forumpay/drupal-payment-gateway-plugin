<?php

namespace Drupal\commerce_forumpay\Model\Data;

/**
 * Dto for ping response
 */
class WebhookTest
{
    /**
     * Message
     *
     * @var string
     */
    private string $message;

    /**
     * Ping DTO constructor
     *
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * Return message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
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
        ];
    }
}
