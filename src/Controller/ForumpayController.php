<?php

namespace Drupal\commerce_forumpay\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
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
use Drupal\Component\Utility\UrlHelper;

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
            $router = new Router($forumPay, $this->orderManager, $forumPayLogger);
            $response = $router->execute($request);
        } catch (ApiHttpException $e) {
            return $this->returnError($e);
        } catch (ForumPayHttpException $e) {
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

        if ($orderId === null || $orderId === ''
            || $return_url === null || $return_url === ''
            || $cancel_url === null || $cancel_url === '') {
            throw new BadRequestHttpException('Missing required parameters.');
        }

        $return_url = $this->validateRedirectUrl($return_url);
        $cancel_url = $this->validateRedirectUrl($cancel_url);

        try {
            $this->orderManager->validateOrderAccess((string) $orderId);
        }
        catch (ForumPayHttpException $e) {
            throw new AccessDeniedHttpException($e->getMessage(), $e);
        }

        $order = $this->orderManager->getOrder((string) $orderId);

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

        $billing = $order->getBillingProfile()->get('address')->first();
        $firstName = $billing ? $billing->getGivenName() : '';
        $lastName = $billing ? $billing->getFamilyName() : '';
        $company = $billing ? $billing->getOrganization() : '';
        $country = $billing ? $billing->getCountryCode() : '';

        return [
            '#attached' => $attached,
            '#type' => 'inline_template',
            '#template' => '<div id="ForumPayPaymentGatewayWidgetContainer">{{ message }}</div>'
                . '<span id="forumpay-apibase" data="{{ api_url|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-orderId" data="{{ order_id|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-returnurl" data="{{ return_url|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-cancelurl" data="{{ cancel_url|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-forumpayapiurl" data="{{ forumpay_api_url|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-invoiceamount" data="{{ invoice_amount|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-invoicecurrency" data="{{ invoice_currency|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-payerfirstname" data="{{ payer_first_name|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-payerlastname" data="{{ payer_last_name|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-payeremail" data="{{ payer_email|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-payercompany" data="{{ payer_company|e(\'html_attr\') }}"></span>'
                . '<span id="forumpay-payercountry" data="{{ payer_country|e(\'html_attr\') }}"></span>',
            '#context' => [
                'message' => '',
                'api_url' => $apiUrl,
                'order_id' => $orderId,
                'return_url' => $return_url,
                'cancel_url' => $cancel_url,
                'forumpay_api_url' => $forumPayApiUrl,
                'invoice_amount' => $this->orderManager->getOrderTotal($orderId),
                'invoice_currency' => $this->orderManager->getOrderCurrency($orderId),
                'payer_first_name' => $firstName,
                'payer_last_name' => $lastName,
                'payer_email' => $order->getEmail(),
                'payer_company' => $company,
                'payer_country' => $country,
            ],
        ];
    }

    /**
     * Validate that a redirect URL is safe to use.
     *
     * @param string $url
     * @return string
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    private function validateRedirectUrl(string $url): string
    {
        if ($url === '') {
            throw new BadRequestHttpException('Invalid redirect URL.');
        }

        if (UrlHelper::isExternal($url)) {
            if (!UrlHelper::isValid($url, TRUE)) {
                throw new BadRequestHttpException('Invalid redirect URL.');
            }

            $base_url = \Drupal::request()->getSchemeAndHttpHost();
            if (!UrlHelper::externalIsLocal($url, $base_url)) {
                throw new BadRequestHttpException('Invalid redirect URL.');
            }
        }
        elseif (!UrlHelper::isValid($url)) {
            throw new BadRequestHttpException('Invalid redirect URL.');
        }

        return $url;
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
