<?php

use Airwallex\CardClient;
use Airwallex\Gateways\Card;
use Airwallex\Gateways\WeChat;
use Airwallex\Services\LogService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\WeChatClient;

class AirwallexController
{
    public function cardPayment()
    {
        try {
            $apiClient = CardClient::getInstance();
            $orderId   = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $gateway = new Card();
            $order   = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }
            $paymentIntent             = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details());
            $paymentIntentId           = $paymentIntent->getId();
            $paymentIntentClientSecret = $paymentIntent->getClientSecret();
            $confirmationUrl           = $gateway->get_payment_confirmation_url();
            $isSandbox                 = $gateway->is_sandbox() ? 'demo' : 'prod';
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
            $orderId   = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $gateway = new WeChat();
            $order   = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }
            $paymentIntent             = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details());
            $paymentIntentId           = $paymentIntent->getId();
            $paymentIntentClientSecret = $paymentIntent->getClientSecret();
            $confirmationUrl           = $gateway->get_payment_confirmation_url();
            $isSandbox                 = $gateway->is_sandbox() ? 'demo' : 'prod';
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
        try {
            $apiClient = CardClient::getInstance();
            $orderId   = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $gateway = new Card();
            $order   = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }
            $paymentIntent = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $gateway->is_submit_order_details());
            WC()->session->set('airwallex_payment_intent_id', $paymentIntent->getId());
            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode([
                'paymentIntent' => $paymentIntent->getId(),
                'clientSecret' => $paymentIntent->getClientSecret(),
            ]);
            die;
        } catch (Exception $e) {
            (new LogService())->error('async intent controller action failed', $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'error' => 1,
            ]);
            die;
        }
    }

    public function paymentConfirmation()
    {
        try {
            $paymentIntentId = WC()->session->get('airwallex_payment_intent_id');
            $apiClient       = CardClient::getInstance();
            $paymentIntent   = $apiClient->getPaymentIntent($paymentIntentId);
            $orderId         = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $order = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }
            if (!in_array($paymentIntent->getStatus(), [PaymentIntent::STATUS_REQUIRES_CAPTURE, PaymentIntent::STATUS_SUCCEEDED], true)) {
                //no valid payment intent
                wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
                wp_redirect(wc_get_checkout_url());
                die;
            }

            if (number_format($paymentIntent->getAmount(), 2) !== number_format($order->get_total(), 2)) {
                //amount mismatch
                (new LogService())->error('payment amounts did not match', $paymentIntent->toArray());
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
                    $order->payment_complete($paymentIntentId);
                    $order->add_order_note('Airwallex payment authorized');
                }
            }
            WC()->cart->empty_cart();
            wp_redirect($order->get_checkout_order_received_url());
            die;
        } catch (Exception $e) {
            (new LogService())->error('payment confirmation controller action failed', $e->getMessage());
            wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
    }

    public function webhook()
    {
        $body           = file_get_contents('php://input');
        $webhookService = new \Airwallex\Services\WebhookService();
        try {
            $webhookService->process($this->getRequestHeaders(), $body);
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['success' => 1]);
            die;
        } catch (Exception $exception) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => 0]);
            die;
        }
    }

    private function getRequestHeaders()
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}