<?php

use Airwallex\CardClient;
use Airwallex\Gateways\Card;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use Airwallex\Services\WebhookService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\WeChatClient;

class AirwallexController
{
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
            include AIRWALLEX_PLUGIN_PATH . '/html/card-payment.php';
            die;
        } catch (Exception $e) {
            (new LogService())->error('card payment controller action failed', $e->getMessage());
            wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
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

            include AIRWALLEX_PLUGIN_PATH . '/html/wechat.php';
            die;
        } catch (Exception $e) {
            (new LogService())->error('wechat payment controller action failed', $e->getMessage());
            wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
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
        $logService = new LogService();
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
                throw new Exception('Order not found: ' . $orderId);
            }
            $orderService = new OrderService();
            $airwallexCustomerId = null;
            if ($orderService->containsSubscription($order->get_id())) {
                $airwallexCustomerId = $orderService->getAirwallexCustomerId($order->get_customer_id(''), $apiClient);
            }
            $logService->debug('asyncIntent() before create', ['orderId' => $orderId]);
            $paymentIntent = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details(), $airwallexCustomerId);
            WC()->session->set('airwallex_payment_intent_id', $paymentIntent->getId());
            header('Content-Type: application/json');
            http_response_code(200);
            $response = [
                'paymentIntent' => $paymentIntent->getId(),
                'createConsent' => !empty($airwallexCustomerId),
                'customerId' => !empty($airwallexCustomerId) ? $airwallexCustomerId : '',
                'currency' => $order->get_currency(''),
                'clientSecret' => $paymentIntent->getClientSecret(),
            ];
            $logService->debug('asyncIntent() response', [
                'response' => $response,
                'session' => [
                    'cookie' => WC()->session->get_session_cookie(),
                    'data' => WC()->session->get_session_data(),
                ],
            ]);
            echo json_encode($response);
            die;
        } catch (Exception $e) {
            $logService->error('async intent controller action failed', $e->getMessage());
            http_response_code(200);
            echo json_encode([
                'error' => 1,
            ]);
            die;
        }
    }

    public function paymentConfirmation()
    {
        $logService = new LogService();
        try {

            $orderId = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $paymentIntentId = WC()->session->get('airwallex_payment_intent_id');

            $logService->debug('paymentConfirmation() init', [
                'paymentIntent' => $paymentIntentId,
                'orderId' => $orderId,
                'session' => [
                    'cookie' => WC()->session->get_session_cookie(),
                    'data' => WC()->session->get_session_data(),
                ],
            ]);

            $apiClient = CardClient::getInstance();
            $paymentIntent = $apiClient->getPaymentIntent($paymentIntentId);

            $order = wc_get_order($orderId);
            
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }

            if ($paymentIntent->getPaymentConsentId()) {
                $order->add_meta_data('airwallex_consent_id', $paymentIntent->getPaymentConsentId());
                $order->add_meta_data('airwallex_customer_id', $paymentIntent->getCustomerId());
                $logService->debug('paymentConfirmation() save consent id', [$paymentIntent->toArray()]);
                $order->save();
            }


            if (!in_array($paymentIntent->getStatus(), [PaymentIntent::STATUS_REQUIRES_CAPTURE, PaymentIntent::STATUS_SUCCEEDED], true)) {
                $logService->warning('paymentConfirmation() invalid status', [$paymentIntent->toArray()]);
                //no valid payment intent
                wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
                wp_redirect(wc_get_checkout_url());
                die;
            }

            if (number_format($paymentIntent->getAmount(), 2) !== number_format($order->get_total(), 2)) {
                //amount mismatch
                $logService->error('payment amounts did not match', $paymentIntent->toArray());
                wc_add_notice('Airwallex payment error', 'error');
                wp_redirect(wc_get_checkout_url());
                die;
            }

            if ($paymentIntent->getStatus() === PaymentIntent::STATUS_SUCCEEDED) {
                $order->payment_complete($paymentIntentId);
                (new LogService())->debug('payment success during checkout', $paymentIntent->toArray());
                $order->add_order_note('Airwallex payment complete');
            } else {
                //handle REQUIRES_CAPTURE state (card payments only)
                $cardGateway = new Card();
                if ($cardGateway->is_capture_immediately()) {
                    $logService->debug('paymentConfirmation() start capture', [$paymentIntent->toArray()]);
                    $paymentIntentAfterCapture = $apiClient->capture($paymentIntentId, $paymentIntent->getAmount());
                    if ($paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED) {
                        $order->payment_complete($paymentIntentId);
                        $order->add_order_note('Airwallex payment captured');
                        (new LogService())->debug('payment success during checkout', $paymentIntent->toArray());
                    } else {
                        (new LogService())->error('payment capture failed during checkout', $paymentIntentAfterCapture->toArray());
                        wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
                        wp_redirect(wc_get_checkout_url());
                        die;
                    }
                } else {
                    $logService->debug('paymentConfirmation() payment complete', []);
                    $order->payment_complete($paymentIntentId);
                    $order->add_order_note('Airwallex payment authorized');
                }
            }
            WC()->cart->empty_cart();
            wp_redirect($order->get_checkout_order_received_url());
            die;
        } catch (Exception $e) {
            $logService->error('payment confirmation controller action failed', $e->getMessage());
            wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
    }

    public function webhook()
    {
        $logService = new LogService();
        $body = file_get_contents('php://input');
        $logService->debug('webhook body', ['body' => $body]);
        $webhookService = new WebhookService();
        try {
            $webhookService->process($this->getRequestHeaders(), $body);
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['success' => 1]);
            die;
        } catch (Exception $exception) {
            $logService->warning('webhook exception', ['msg' => $exception->getMessage()]);
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
}
