<?php

namespace Airwallex\Services;

use Airwallex\CardClient;
use Airwallex\Gateways\Card;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;
use WC_Order_Refund;

class WebhookService
{
    /**
     * @param $headers
     * @param $msg
     * @throws Exception
     */
    public function process($headers, $msg)
    {
        $logService = new LogService();
        $orderService = new OrderService();
        try {
            $this->verifySignature($headers, $msg);
        } catch (Exception $e) {
            $logService->warning('unable to verify webhook signature', [$headers, $msg]);
            return;
        }

        $messageData = json_decode($msg, true);
        $logService->debug('🖧 received webhook notification', $messageData);
        $eventType = $messageData['name'];
        $eventObjectType = explode('.', $eventType)[0];
        if ($eventObjectType === 'payment_intent') {
            $logService->debug('🖧 received payment_intent webhook');
            $paymentIntent = new PaymentIntent($messageData['data']['object']);
            $orderId = $this->getOrderIdForPaymentIntent($paymentIntent);

            if ($orderId === 0) {
                throw new Exception('no order found for payment intent: ' . print_r(['payment_intent' => $paymentIntent->toArray(), 'order_id' => $orderId], true));
            }

            /** @var \WC_Order $order */
            $order = wc_get_order($orderId);

            if (!empty($order)) {
                switch ($eventType) {
                    case 'payment_intent.cancelled':
                        $order->update_status('failed', 'Airwallex Webhook');
                        break;
                    case 'payment_intent.capture_required':
                        if ($order->get_payment_method() === Card::GATEWAY_ID) {
                            $cardGateway = new Card();
                            if (!$cardGateway->is_capture_immediately()) {
                                $orderService->setPaymentSuccess($order, $paymentIntent);
                            }
                        }
                        break;
                    case 'payment_intent.succeeded':
                        $orderService->setPaymentSuccess($order, $paymentIntent);
                        break;
                }

                $order->add_order_note('Airwallex Webhook notification: ' . $eventType . "\n\n" . 'Amount: ' . $paymentIntent->getAmount() . "\n\nCaptured amount: " . $paymentIntent->getCapturedAmount());
            }
        } elseif ($eventType === 'refund.processing' || $eventType === 'refund.succeeded') {
            $logService->debug('🖧 received refund webhook');
            $refund = new Refund($messageData['data']['object']);
            $paymentIntentId = $refund->getPaymentIntentId();
            $order = $orderService->getOrderByPaymentIntentId($paymentIntentId);

            if (empty($order)) {
                $logService->warning('no order found for refund', ['paymentIntent' => $paymentIntentId]);
                throw new Exception('no order found for refund on payment_intent ' . $paymentIntentId);

            }
            $order->add_order_note('Airwallex Webhook notification: ' . $eventType . "\n\n" . 'Amount: ' . $refund->getAmount());

            if (!$orderService->getRefundIdByAirwallexRefundId($refund->getId())) {
                if ($wcRefundId = $orderService->getRefundByAmountAndTime($order->get_id(), $refund->getAmount(), date('Y-m-d H:i:s'))) {
                    update_post_meta($wcRefundId, '_airwallex_refund_id', $refund->getId());
                } else {
                    $wcRefund = wc_create_refund([
                        'amount' => $refund->getAmount(),
                        'reason' => $refund->getReason(),
                        'order_id' => $order->get_id(),
                        'refund_payment' => false,
                        'restock_items' => false,
                    ]);
                    if ($wcRefund instanceof WC_Order_Refund) {
                        update_post_meta($wcRefund->get_id(), '_airwallex_refund_id', $refund->getId());
                    } else {
                        $logService->error('failed to create WC refund from webhook notification', $wcRefund);
                    }
                }
            }
        }
    }



    /**
     * @param array $headers
     * @param string $msg
     * @throws Exception
     */
    private function verifySignature($headers, $msg)
    {

        $timestamp = $headers['x-timestamp'];
        $secret = get_option('airwallex_webhook_secret');
        $signature = $headers['x-signature'];

        if (hash_hmac('sha256', $timestamp . $msg, $secret) !== $signature) {
            throw new Exception('Invalid signature');
        }
    }

    /**
     * @param PaymentIntent $paymentIntent
     * @return int
     */
    private function getOrderIdForPaymentIntent(PaymentIntent $paymentIntent)
    {
        $metaData = $paymentIntent->getMetadata();
        if (!empty($metaData['wp_order_id'])) {
            return (int)$metaData['wp_order_id'];
        } else {
            return (int)$paymentIntent->getMerchantOrderId();
        }
    }
}
