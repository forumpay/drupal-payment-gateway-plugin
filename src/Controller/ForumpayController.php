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
        $orderId = $request->getRequired('orderId');
        $order = Order::load($orderId);
        $paymentGateway = $order->payment_gateway->entity;
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
        $config = $paymentGateway->get('configuration');

        $apiUrl = Url::fromRoute('commerce_forumpay.apicall', [], ['absolute' => true])->toString();
        $basePath = Url::fromRoute('<front>', [], ['absolute' => true])->toString();

        $module_handler = \Drupal::service('module_handler');
        $module_path = $module_handler->getModule('commerce_forumpay')->getPath();

        $extraHtml = '<span id="forumpay-apibase" data="' . $apiUrl . '"></span>';
        $extraHtml .= '<span id="forumpay-orderId" data="' . $orderId . '"></span>';
        $extraHtml .= '<span id="forumpay-returnurl" data="' . $return_url . '"></span>';
        $extraHtml .= '<span id="forumpay-cancelurl" data="' . $cancel_url . '"></span>';

        $extraHtml .= '<link rel="stylesheet"  href="' . $basePath . $module_path . '/css/forumpay.css" />';
        $extraHtml .= '<link rel="stylesheet"  href="' . $basePath . $module_path . '/css/forumpay_widget.css" />';
        $extraHtml .= '<script type="text/javascript" src="' . $basePath . $module_path . '/js/forumpay_widget.js"></script>';
        $extraHtml .= '<script type="text/javascript" src="' . $basePath . $module_path . '/js/forumpay.js"></script>';

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
