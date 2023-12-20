<?php

namespace Drupal\commerce_forumpay;

use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\ForumPayException;
use Drupal\commerce_forumpay\Exception\ForumPayHttpException;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Model\CancelPayment;
use Drupal\commerce_forumpay\Model\CheckPayment;
use Drupal\commerce_forumpay\Model\GetCurrencyList;
use Drupal\commerce_forumpay\Model\GetCurrencyRate;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Model\RestoreCart;
use Drupal\commerce_forumpay\Model\StartPayment;
use Drupal\commerce_forumpay\Model\Webhook;

/**
 * Maps action parameter to the responsible action.
 */
class Router
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
     * Available routes
     *
     * @var array
     */
    private array $routes = [];

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

        $this->initRoutes();
    }

    protected function initRoutes()
    {
        $this->routes = [
            'currencies' => new GetCurrencyList($this->forumPay, $this->logger),
            'getRate' => new GetCurrencyRate($this->forumPay, $this->logger),
            'startPayment' => new StartPayment($this->forumPay, $this->logger),
            'checkPayment' => new CheckPayment($this->forumPay, $this->logger),
            'cancelPayment' => new CancelPayment($this->forumPay, $this->logger),
            'webhook' => new Webhook($this->forumPay, $this->logger),
            'restoreCart' => new RestoreCart($this->forumPay, $this->logger),
        ];
    }

    /**
     * Execute HTTP request and return serialized response
     *
     * @param Request $request
     * @return array|null
     */
    public function execute(Request $request): ?array
    {
        $route = $request->getRequired('act');

        if (array_key_exists($route, $this->routes)) {
            $service = $this->routes[$route];
            $response = $service->execute($request);
            return $response->toArray();
        }

        throw \Exception(sprintf("Action %s, not found"), $route);
    }

    /**
     * @param $response
     * @return false|string
     */
    private function serializeResponse($response)
    {
        return json_encode($response->toArray());
    }

    /**
     * @param ForumPayHttpException $e
     * @return false|string
     */
    private function serializeError(ForumPayHttpException $e)
    {
        $repose = new Response();
        $repose->setHttpResponseCode($e->getHttpCode());
        return json_encode([
            'code' => $e->getCode(),
            'message' => $e->getMessage()
        ]);
    }
}
