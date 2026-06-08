<?php

namespace Drupal\commerce_forumpay\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_forumpay\Config;
use Drupal\commerce_forumpay\Logger\ForumPayLogger;
use Drupal\commerce_forumpay\Logger\PrivateTokenMasker;
use Drupal\commerce_forumpay\Model\Payment\ForumPay;
use Drupal\commerce_forumpay\Model\Payment\OrderManager;
use Drupal\commerce_forumpay\Router;
use Drupal\commerce_forumpay\Exception\ApiHttpException;
use Drupal\commerce_forumpay\Exception\ForumPayException;
use Drupal\commerce_forumpay\Exception\ForumPayHttpException;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Component\Utility\Html;

/**
 * Maps action parameter to the responsible action.
 */
class ForumpayController extends ControllerBase
{
    /**
     * The logger service.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Drupal container
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * ForumPay order manager
     *
     * @var OrderManager
     */
    private OrderManager $orderManager;

    /**
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface    $logger
    )
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->orderManager = new OrderManager();
    }

    public function ApiCall()
    {
        $forumPayLogger = new ForumPayLogger($this->logger);
        $forumPayLogger->addParser(new PrivateTokenMasker());

        $request = new \Drupal\commerce_forumpay\Request();

        $paymentGateway = PaymentGateway::load('forumpay');

        if (!$paymentGateway) {
            return $this->returnError(
                new ForumPayHttpException(
                    'Payment gateway "forumpay" not found.',
                    0,
                    ForumPayHttpException::HTTP_BAD_REQUEST
                )
            );
        }

        $config = new Config($paymentGateway->get('configuration'));

        $forumPay = new ForumPay(
            $this->orderManager,
            $forumPayLogger,
            $config
        );

        try {
            $router = new Router($forumPay, $forumPayLogger);
            $response = $router->execute($request);
        } catch (ApiHttpException $e) {
            return $this->returnError($e);
        } catch (ForumPayException $e) {
            return $this->returnError(
                new ForumPayHttpException(
                    $e->getMessage(),
                    $e->getCode(),
                    ForumPayHttpException::HTTP_BAD_REQUEST
                )
            );
        } catch (\Exception $e) {
            return $this->returnError(
                new ForumPayHttpException(
                    $e->getMessage(),
                    $e->getCode(),
                    ForumPayHttpException::HTTP_INTERNAL_ERROR,
                )
            );
        }

        return new JsonResponse($response);
    }

    public function PayForm(Request $request)
    {
        $attached['library'][] = 'commerce_forumpay/payform';

        $orderId = $request->request->get('orderid');
        $return_url = $request->request->get('return_url');
        $cancel_url = $request->request->get('cancel_url');

        $order = $this->orderManager->getOrder($orderId);

        if ($order->getState()->getId() === 'completed') {
            return new RedirectResponse($return_url);
        }

        if ($order->getState()->getId() !== 'draft') {
            return new RedirectResponse($cancel_url);
        }

        $paymentGateway = $order->payment_gateway->entity;
        $config = new Config($paymentGateway->get('configuration'));

        $parsed_url = parse_url($config->getApiUrl());
        $protocol = $parsed_url['scheme'] ?? null;
        $host = $parsed_url['host'] ?? null;
        $forumPayApiUrl = '';

        if ($protocol && $host) {
            $forumPayApiUrl = $protocol . "://" . $host;
        }

        $apiUrl = Url::fromRoute('commerce_forumpay.apicall', [], ['absolute' => true])->toString();

        $extraHtml = '<span id="forumpay-apibase" data="' . $apiUrl . '"></span>';
        $extraHtml .= '<span id="forumpay-orderId" data="' . $orderId . '"></span>';
        $extraHtml .= '<span id="forumpay-returnurl" data="' . $return_url . '"></span>';
        $extraHtml .= '<span id="forumpay-cancelurl" data="' . $cancel_url . '"></span>';
        $extraHtml .= '<span id="forumpay-forumpayapiurl" data="' . $forumPayApiUrl . '"></span>';
        $extraHtml .= '<span id="forumpay-invoiceamount" data="' . $this->orderManager->getOrderTotal($orderId) . '"></span>';
        $extraHtml .= '<span id="forumpay-invoicecurrency" data="' . $this->orderManager->getOrderCurrency($orderId) . '"></span>';

        $billing = $order->getBillingProfile()->get('address')->first();
        $firstName = $billing ? $billing->getGivenName() : '';
        $lastName = $billing ? $billing->getFamilyName() : '';
        $company = $billing ? $billing->getOrganization() : '';
        $country = $billing ? $billing->getCountryCode() : '';

        $extraHtml .= '<span id="forumpay-payerfirstname" data="' . Html::escape($firstName) . '"></span>';
        $extraHtml .= '<span id="forumpay-payerlastname" data="' . Html::escape($lastName) . '"></span>';
        $extraHtml .= '<span id="forumpay-payeremail" data="' . Html::escape($order->getEmail()) . '"></span>';
        $extraHtml .= '<span id="forumpay-payercompany" data="' . Html::escape($company) . '"></span>';
        $extraHtml .= '<span id="forumpay-payercountry" data="' . Html::escape($country) . '"></span>';

        $templateHtml = '<div id="ForumPayPaymentGatewayWidgetContainer">{{message}}</div>' . $extraHtml;

        return array(
            '#attached' => $attached,
            '#type' => 'inline_template',
            '#template' => $templateHtml,
        );
    }

    /**
     * @param ForumPayHttpException $e
     * @return false|string
     */
    private function returnError(ForumPayHttpException $e)
    {
        return new JsonResponse(
            [
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ],
            $e->getHttpCode()
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container,
            $container->get('logger.factory')->get('commerce_forumpay')
        );
    }
}
