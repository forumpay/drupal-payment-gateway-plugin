<?php

namespace Drupal\commerce_forumpay;

/**
 * Sets correct response code
 */
class Response
{
    public function setHttpResponseCode($statusCode): void
    {
        if (!headers_sent()) {
            if (function_exists("http_response_code")) {
                http_response_code($statusCode);
            } else {
                header(" ", true, $statusCode);
            }
        }
    }
}
