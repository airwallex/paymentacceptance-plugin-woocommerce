<?php

use Airwallex\CardClient;
use Airwallex\Gateways\Card;
use Airwallex\Gateways\Main;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use Airwallex\Services\WebhookService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\WeChatClient;

class AirwallexController
{

    /**
     * @var LogService
     */
    protected $logService;

    public function __construct()
    {
        $this->logService = new LogService();
    }

    public function cardPayment()
    {
        try {
            $apiClient = CardClient::getInstance();
            $orderId = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $gateway = new Card();
            $order = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }
            $paymentIntent = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details());
            $paymentIntentId = $paymentIntent->getId();
            $paymentIntentClientSecret = $paymentIntent->getClientSecret();
            $confirmationUrl = $gateway->get_payment_confirmation_url();
            $isSandbox = $gateway->is_sandbox();
            WC()->session->set('airwallex_payment_intent_id', $paymentIntentId);
            $this->logService->debug('cardPayment()', [
                'orderId' => $orderId,
                'paymentIntent' => $paymentIntentId,
            ], LogService::CARD_ELEMENT_TYPE);

            include AIRWALLEX_PLUGIN_PATH . '/html/card-payment.php';
            die;
        } catch (Exception $e) {
            (new LogService())->error('card payment controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE);
            wc_add_notice(__('Airwallex payment error', 'airwallex-online-payments-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }

    }

    public function dropInPayment()
    {
        try {
            $apiClient = CardClient::getInstance();
            $orderId = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $gateway = new Main();
            $order = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }
            $airwallexCustomerId = null;
            $orderService = new OrderService();
            $isSubscription = $orderService->containsSubscription($order->get_id());
            if ($order->get_customer_id('') || $isSubscription) {
                $airwallexCustomerId = $orderService->getAirwallexCustomerId($order->get_customer_id(''), $apiClient);
            }

            $paymentIntent = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details(), $airwallexCustomerId);
            $paymentIntentId = $paymentIntent->getId();
            $paymentIntentClientSecret = $paymentIntent->getClientSecret();
            $confirmationUrl = $gateway->get_payment_confirmation_url();
            $isSandbox = $gateway->is_sandbox();
            WC()->session->set('airwallex_payment_intent_id', $paymentIntentId);
            $this->logService->debug('dropInPayment()', [
                'orderId' => $orderId,
                'paymentIntent' => $paymentIntentId,
            ], LogService::CARD_ELEMENT_TYPE);

            include AIRWALLEX_PLUGIN_PATH . '/html/drop-in-payment.php';
            die;
        } catch (Exception $e) {
            (new LogService())->error('drop in payment controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE);
            wc_add_notice(__('Airwallex payment error', 'airwallex-online-payments-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }

    }

    public function weChatPayment()
    {
        try {
            $apiClient = WeChatClient::getInstance();
            $orderId = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $gateway = new WeChat();
            $order = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }
            $paymentIntent = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details());
            $paymentIntentId = $paymentIntent->getId();
            $paymentIntentClientSecret = $paymentIntent->getClientSecret();
            $confirmationUrl = $gateway->get_payment_confirmation_url();
            $isSandbox = $gateway->is_sandbox();
            WC()->session->set('airwallex_payment_intent_id', $paymentIntentId);
            $this->logService->debug('weChatPayment()', [
                'orderId' => $orderId,
                'paymentIntent' => $paymentIntentId,
            ], LogService::WECHAT_ELEMENT_TYPE);

            include AIRWALLEX_PLUGIN_PATH . '/html/wechat.php';
            die;
        } catch (Exception $e) {
            (new LogService())->error('wechat payment controller action failed', $e->getMessage(), LogService::WECHAT_ELEMENT_TYPE);
            wc_add_notice(__('Airwallex payment error', 'airwallex-online-payments-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
    }

    /**
     * Card payment only
     * @throws Exception
     */
    public function asyncIntent()
    {
        try {
            $apiClient = CardClient::getInstance();
            if (!empty($_GET['airwallexOrderId'])) {
                $orderId = $_GET['airwallexOrderId'];
                WC()->session->set('airwallex_order', $orderId);
            }
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('airwallex_order');
            }
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $gateway = new Card();
            $order = wc_get_order($orderId);
            if (empty($order)) {
                $this->logService->debug('asyncIntent() can not find order', ['orderId' => $orderId]);
                throw new Exception('Order not found: ' . $orderId);
            }
            $orderService = new OrderService();
            $airwallexCustomerId = null;
            if ($orderService->containsSubscription($order->get_id())) {
                $airwallexCustomerId = $orderService->getAirwallexCustomerId($order->get_customer_id(''), $apiClient);
            }
            $this->logService->debug('asyncIntent() before create', ['orderId' => $orderId]);
            $paymentIntent = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details(), $airwallexCustomerId);
            WC()->session->set('airwallex_payment_intent_id', $paymentIntent->getId());

            update_post_meta($orderId, '_tmp_airwallex_payment_intent', $paymentIntent->getId());

            header('Content-Type: application/json');
            http_response_code(200);
            $response = [
                'paymentIntent' => $paymentIntent->getId(),
                'orderId' => $orderId,
                'createConsent' => !empty($airwallexCustomerId),
                'customerId' => !empty($airwallexCustomerId) ? $airwallexCustomerId : '',
                'currency' => $order->get_currency(''),
                'clientSecret' => $paymentIntent->getClientSecret(),
            ];
            $this->logService->debug('asyncIntent() receive payment intent response', [
                'response' => $response,
                'session' => [
                    'cookie' => WC()->session->get_session_cookie(),
                    'data' => WC()->session->get_session_data(),
                ],
            ], LogService::CARD_ELEMENT_TYPE);
            echo json_encode($response);
            die;
        } catch (Exception $e) {
            $this->logService->error('async intent controller action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE);
            http_response_code(200);
            echo json_encode([
                'error' => 1,
            ]);
            die;
        }
    }

    private function getOrderAndPaymentIntentForConfirmation()
    {
        $orderId = (int)WC()->session->get('airwallex_order');
        if (empty($orderId)) {
            $orderId = (int)WC()->session->get('order_awaiting_payment');
        }
        if (empty($orderId) && !empty($_GET['order_id'])) {
            $this->logService->debug('detected order id from URL', ['get' => $_GET]);
            $orderId = (int)$_GET['order_id'];
        }

        if (empty($orderId)) {
            $this->logService->debug('getOrderAndPaymentIntentForConfirmation() do not have order id', ['orderId' => $orderId]);
            throw new Exception('I tried hard, but no order was found for confirmation');
        }

        $paymentIntentId = WC()->session->get('airwallex_payment_intent_id');
        if (empty($paymentIntentId)) {
            $paymentIntentId = get_post_meta($orderId, '_tmp_airwallex_payment_intent', true);
        }

        if (!empty($_GET['intent_id'])) {
            $intentIdFromUrl = $_GET['intent_id'];
            if (!empty($paymentIntentId) && $paymentIntentId !== $intentIdFromUrl) {
                $this->logService->warning('different intent ids from url and session', ['from_session' => $paymentIntentId, 'from_url' => $intentIdFromUrl]);
                if (!empty($_GET['order_id'])) {
                    throw new Exception('different intent ids from url and session - fraud suspected');
                }
            } else {
                $paymentIntentId = $intentIdFromUrl;
            }
        }
        return [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntentId,
        ];
    }

    public function paymentConfirmation()
    {
        try {
            $orderInformation = $this->getOrderAndPaymentIntentForConfirmation();
            $orderId = $orderInformation['order_id'];
            $paymentIntentId = $orderInformation['payment_intent_id'];
            $orderService = new OrderService();

            $this->logService->debug('paymentConfirmation() init', [
                'paymentIntent' => $paymentIntentId,
                'orderId' => $orderId,
                'session' => [
                    'cookie' => WC()->session->get_session_cookie(),
                    'data' => WC()->session->get_session_data(),
                ],
            ]);

            $apiClient = CardClient::getInstance();
            $paymentIntent = $apiClient->getPaymentIntent($paymentIntentId);
            $this->logService->debug('paymentConfirmation() payment intent', [
                'paymentIntent' => $paymentIntent->toArray(),
            ]);
            $order = wc_get_order($orderId);

            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }

            if ($paymentIntent->getPaymentConsentId()) {
                $this->logService->debug('paymentConfirmation() save consent id', [$paymentIntent->toArray()]);
                $order->add_meta_data('airwallex_consent_id', $paymentIntent->getPaymentConsentId());
                $order->add_meta_data('airwallex_customer_id', $paymentIntent->getCustomerId());
                $order->save();
            }

            $this->handleStatusForConfirmation($paymentIntent, $order);

            if (number_format($paymentIntent->getAmount(), 2) !== number_format($order->get_total(), 2)) {
                //amount mismatch
                $this->logService->error('paymentConfirmation() payment amounts did not match', [number_format($paymentIntent->getAmount(), 2), number_format($order->get_total(), 2), $paymentIntent->toArray()]);
                $this->setTemporaryOrderStateAfterDecline($order);
                wc_add_notice('Airwallex payment error', 'error');
                wp_redirect(wc_get_checkout_url());
                die;
            }

            if ($paymentIntent->getStatus() === PaymentIntent::STATUS_SUCCEEDED) {
                $order->payment_complete($paymentIntentId);
                (new LogService())->debug('paymentConfirmation() payment success during checkout', $paymentIntent->toArray());
                $order->add_order_note('Airwallex payment complete');
            } elseif ($paymentIntent->getStatus() === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
                $orderService->setAuthorizedStatus($order);
                $paymentGateway = wc_get_payment_gateway_by_order($order);
                if ($paymentGateway instanceof Card) {
                    if ($paymentGateway->is_capture_immediately()) {
                        $this->logService->debug('paymentConfirmation() start capture', [$paymentIntent->toArray()]);
                        $paymentIntentAfterCapture = $apiClient->capture($paymentIntentId, $paymentIntent->getAmount());
                        if ($paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED) {
                            $order->payment_complete($paymentIntentId);
                            $order->add_order_note('Airwallex payment captured');
                            (new LogService())->debug('paymentConfirmation() payment success during checkout', $paymentIntent->toArray());
                        } else {
                            (new LogService())->error('paymentConfirmation() payment capture failed during checkout', $paymentIntentAfterCapture->toArray());
                            $this->setTemporaryOrderStateAfterDecline($order);
                            wc_add_notice(__('Airwallex payment error', 'airwallex-online-payments-gateway'), 'error');
                            wp_redirect(wc_get_checkout_url());
                            die;
                        }
                    } else {
                        $this->logService->debug('paymentConfirmation() payment complete', []);
                        $order->payment_complete($paymentIntentId);
                        $order->add_order_note('Airwallex payment authorized');
                    }
                }
            } elseif (in_array($paymentIntent->getStatus(), PaymentIntent::PENDING_STATUSES)) {
                $orderService->setPendingStatus($order);
            }
            WC()->cart->empty_cart();
            wp_redirect($order->get_checkout_order_received_url());
            die;
        } catch (Exception $e) {
            $this->logService->error('paymentConfirmation() payment confirmation controller action failed', $e->getMessage());
            if (!empty($order)) {
                $this->setTemporaryOrderStateAfterDecline($order);
            }
            wc_add_notice(__('Airwallex payment error', 'airwallex-online-payments-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
    }

    /**
     * @param WC_Order $order
     * @return void
     */
    private function setTemporaryOrderStateAfterDecline($order)
    {
        (new OrderService())->setTemporaryOrderStateAfterDecline($order);
    }

    public function webhook()
    {
        $body = file_get_contents('php://input');
        $this->logService->debug('🖧 webhook body', ['body' => $body]);
        $webhookService = new WebhookService();
        try {
            $webhookService->process($this->getRequestHeaders(), $body);
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['success' => 1]);
            die;
        } catch (Exception $exception) {
            $this->logService->warning('webhook exception', ['msg' => $exception->getMessage()]);
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => 0]);
            die;
        }
    }

    private function getRequestHeaders()
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                $headers[strtolower($k)] = $v;
            }
            return $headers;
        }

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value;
            }
        }
        return $headers;
    }

    protected function handleStatusForConfirmation(PaymentIntent $paymentIntent, WC_Order $order)
    {
        $validStatuses = array_merge(
            [
                PaymentIntent::STATUS_SUCCEEDED,
                PaymentIntent::STATUS_REQUIRES_CAPTURE,
            ],
            PaymentIntent::PENDING_STATUSES
        );

        if (!in_array($paymentIntent->getStatus(), $validStatuses, true)) {
            $this->logService->warning('paymentConfirmation() invalid status', [$paymentIntent->toArray()]);
            //no valid payment intent
            $this->setTemporaryOrderStateAfterDecline($order);
            wc_add_notice(__('Airwallex payment error', 'airwallex-online-payments-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
        if (in_array($paymentIntent->getStatus(), PaymentIntent::PENDING_STATUSES, true)) {
            $this->logService->debug('paymentConfirmation() pending status', [$paymentIntent->toArray()]);
            (new OrderService())->setPendingStatus($order);
        }

    }
}
