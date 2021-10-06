<?php

namespace Airwallex\Services;


use Airwallex\AbstractClient;
use Exception;
use WC_Order;
use wpdb;

class OrderService
{
    /**
     * @param $paymentIntentId
     * @return WC_Order|null
     */
    public function getOrderByPaymentIntentId($paymentIntentId){
        global  $wpdb;
        $q = $wpdb->prepare("
            SELECT p.ID FROM ".$wpdb->posts." p
                JOIN ".$wpdb->postmeta." pm ON (p.ID = pm.post_id AND pm.meta_key = '_transaction_id')
            WHERE
                p.post_type = 'shop_order'
                    AND
                pm.meta_value = %s",
            [
                $paymentIntentId
            ]
        );
        if($row = $wpdb->get_row($q)){
            $order = wc_get_order((int)$row->ID);
            if($order instanceof WC_Order){
                return $order;
            }
        }
        return null;
    }

    public function getRefundByAmountAndTime($orderId, $amount, $dateTime){
        global  $wpdb;

        $startTime = (new \DateTime("now - 600 seconds", new \DateTimeZone('+0000')))->format('Y-m-d H:i:s');
        $endTime = (new \DateTime("now + 600 seconds", new \DateTimeZone('+0000')))->format('Y-m-d H:i:s');
        $q = $wpdb->prepare("
            SELECT p.ID FROM 
                             ".$wpdb->posts." p
                             JOIN ".$wpdb->postmeta." pm ON (p.ID = pm.post_id AND pm.meta_key = '_refund_amount')
                             JOIN ".$wpdb->postmeta." pm_payment ON (p.post_parent = pm_payment.post_id AND pm_payment.meta_key = '_payment_method' AND  pm_payment.meta_value LIKE %s)
                             LEFT JOIN ".$wpdb->postmeta." pm_refund_id ON (p.ID = pm.post_id AND pm.meta_key = '_airwallex_refund_id')
            WHERE
                p.post_type = 'shop_order_refund'
                    AND
                p.post_parent = %s
                    AND
                p.post_date_gmt > %s
                    AND
                p.post_date_gmt < %s
                    AND
                pm.meta_value = %s
                    AND
                pm_refund_id.meta_value IS NULL",
            [
                'airwallex_%',
                $orderId,
                $startTime,
                $endTime,
                number_format($amount, 2)
            ]
        );
        if($row = $wpdb->get_row($q)){
            return $row->ID;
        }
        return null;
    }

    /**
     * @param string $refundId
     * @return null|string
     */
    public function getRefundIdByAirwallexRefundId($refundId){
        global  $wpdb;
        $q = $wpdb->prepare("
            SELECT p.ID FROM 
                             ".$wpdb->posts." p
                             JOIN ".$wpdb->postmeta." pm ON (p.ID = pm.post_id AND pm.meta_key = '_airwallex_refund_id')
            WHERE
                p.post_type = 'shop_order_refund'
                    AND
                pm.meta_value = %s",
            [
                $refundId
            ]
        );
        if($row = $wpdb->get_row($q)){
            return $row->ID;
        }
        return null;
    }

    /**
     * @param int $wordpressCustomerId
     * @param AbstractClient $client
     * @return int|mixed
     * @throws Exception
     */
    public function getAirwallexCustomerId($wordpressCustomerId, AbstractClient $client){
        if($customer = $client->getCustomer($wordpressCustomerId)){
            return $customer->getId();
        }
        $customer = $client->createCustomer($wordpressCustomerId);
        return $customer->getId();
    }


    public function containsSubscription($orderId)
    {
        return (function_exists('wcs_order_contains_subscription') && (wcs_order_contains_subscription($orderId) || wcs_is_subscription($orderId) || wcs_order_contains_renewal($orderId)));
    }

}