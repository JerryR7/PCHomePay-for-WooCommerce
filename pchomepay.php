<?php
/*
 * @copyright  Copyright © 2017 PChomePay Electronic Payment Co., Ltd.(https://www.pchomepay.com.tw)
 * @version 0.0.1
 *
 * Plugin Name: PChomePay Payment
 * Plugin URI: https://www.pchomepay.com.tw/
 * Description: PChomePay Integration Payment Gateway for WooCommerce
 * Version: 0.0.1
 * Author: PChomePay Electronic Payment Co., Ltd.
 * Author URI: https://www.pchomepay.com.tw
 */

add_action('plugins_loaded', 'pchomepay_gateway_init', 0);

function pchomepay_gateway_init()
{
    # Make sure WooCommerce is setted.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once(dirname(__FILE__) . '/PChomePayClient.php');

    class WC_Gateway_PChomepay extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'pchomepay';
            $this->icon = apply_filters('woocommerce_pchomepay_icon', plugins_url('images/pchomepay_logo.png', __FILE__));;
            $this->has_fields = false;
            $this->method_title = __('PChomePay', 'woocommerce');
            $this->method_description = '透過 PChomePay 付款。<br>會連結到 PChomePay 付款頁面。';

            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->app_id = trim($this->get_option('app_id'));
            $this->secret = trim($this->get_option('secret'));
            $this->atm_expiredate = $this->get_option('atm_expiredate');
            $this->test_mode = $this->get_option('test_mode');
            $this->notify_url = WC()->api_request_url(get_class($this));
            $this->payment_methods = $this->get_option('payment_methods');
            $this->card_installment = $this->get_option('card_installment');
            $this->card_rate = $this->get_option('card_rate');

            // Test Mode
            $this->test_mode = ($this->get_option('test_mode') === 'yes') ? true : false;

            if (empty($this->app_id) || empty($this->secret)) {
                $this->enabled = false;
            } else {
                $this->client = new PchomePayClient($this->app_id, $this->secret, $this->test_mode);
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'receive_response'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woocommerce'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('PChomePay', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('透過 PChomePay 付款。<br>會連結到 PChomePay 付款頁面。', 'woocommerce'),
                ),
                'test_mode' => array(
                    'title' => __('Test Mode', 'woocommerce'),
                    'label' => __('Enable', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => __('Test order will add date as prefix.', 'woocommerce'),
                    'default' => 'no'
                ),
                'app_id' => array(
                    'title' => __('APP ID', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'ED25E956580083F635C2F2EC6C16'
                ),
                'secret' => array(
                    'title' => __('SECRET', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'rV_lOkRdWiFA3Ah_usq5z8FKlnMVlFO7lJ8q63ya'
                ),
                'payment_methods' => array(
                    'title' => __('Payment Method', 'woocommerce'),
                    'type' => 'multiselect',
                    'description' => __('Press CTRL and the right button on the mouse to select multi payments.', 'woocommerce'),
                    'options' => array(
                        'CARD' => __('CARD'),
                        'ATM' => __('ATM'),
                        'EACH' => __('EACH'),
                        'ACCT' => __('ACCT')
                    )
                ),
                'card_installment' => array(
                    'title' => __('Card Installment', 'woocommerce'),
                    'type' => 'multiselect',
                    'description' => __('Card Installment Setting<br>Press CTRL and the right button on the mouse to select multi payments.', 'woocommerce'),
                    'options' => array(
                        'CRD_0' => __('Credit', 'woocommerce'),
                        'CRD_3' => __('Credit_3', 'woocommerce'),
                        'CRD_6' => __('Credit_6', 'woocommerce'),
                        'CRD_12' => __('Credit_12', 'woocommerce'),
                    )
                ),
                'card_rate' => array(
                    'title' => __('Card Installment', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Card Rate Setting', 'woocommerce'),
                    'options' => array(
                        '0' => __('Zero-percent Interest Rate', 'woocommerce'),
                        '1' => __('General Interest Rate', 'woocommerce')
                    )
                ),
                'atm_expiredate' => array(
                    'title' => __('ATM Expire Date', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("Please enter ATM expire date (1~5 days), default is 5 days", 'woocommerce'),
                    'default' => 5
                )
            );
        }

        public function admin_options()
        {
            ?>
            <h2><?php _e('PChomePay 收款模組', 'woocommerce'); ?></h2>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <?php
        }

        private function get_pchomepay_args($order)
        {
            global $woocommerce;

            $order_id = date('Ymd') . $order->get_order_number();
            $pay_type = $this->payment_methods;
            $amount = ceil($order->get_total());
            $return_url = $this->get_return_url($order);
            $notify_url = $this->notify_url;
            $buyer_email = $order->get_billing_email();
            $atm_info = (object)['expire_days' => (int)$this->atm_expiredate];

            $card_info = [];
            $card_rate = $this->card_rate == 1 ? null : 0;

            foreach ($this->card_installment as $items) {
                switch ($items) {
                    case 'CRD_3' :
                        $card_installment['installment'] = 3;
                        $card_installment['rate'] = $card_rate;
                        break;
                    case 'CRD_6' :
                        $card_installment['installment'] = 6;
                        $card_installment['rate'] = $card_rate;
                        break;
                    case 'CRD_12' :
                        $card_installment['installment'] = 12;
                        $card_installment['rate'] = $card_rate;
                        break;
                    default :
                        unset($card_installment);
                        break;
                }
                if (isset($card_installment)) {
                    $card_info[] = (object)$card_installment;
                }
            }

            $items = [];

            $order_items = $order->get_items();
            foreach ($order_items as $item) {
                $product = [];
                $order_item = new WC_Order_Item_Product($item);
                $product_id = ($order_item->get_product_id());
                $product['name'] = $order_item->get_name();
                $product['url'] = get_permalink($product_id);

                $items[] = (object)$product;
            }

            $pchomepay_args = [
                'order_id' => $order_id,
                'pay_type' => $pay_type,
                'amount' => $amount,
                'return_url' => $return_url,
                'notify_url' => $notify_url,
                'items' => $items,
                'buyer_email' => $buyer_email,
                'atm_info' => $atm_info,
                'card_info' => $card_info
            ];

            $pchomepay_args = apply_filters('woocommerce_spgateway_args', $pchomepay_args);

            return $pchomepay_args;
        }

        public function process_payment($order_id)
        {
            try {
                global $woocommerce;

                $order = new WC_Order($order_id);

                // 更新訂單狀態為等待中 (等待第三方支付網站返回)
                $order->update_status('pending', __('Awaiting PChomePay payment', 'woocommerce'));

                $pchomepay_args = json_encode($this->get_pchomepay_args($order));

                if (!class_exists('PChomePayClient')) {
                    if (!require(dirname(__FILE__) . 'PChomePayClient.php')) {
                        throw new Exception(__('PChomePayClient Class missed.', 'woocommerce'));
                    }
                }

                // 建立訂單
                $result = $this->client->postPayment($pchomepay_args);
                // 減少庫存
                wc_reduce_stock_levels($order_id);
                // 清空購物車
                update_post_meta($order_id, '_hpd_linepay_transactionId', $response_data->info->transactionId);
                $woocommerce->cart->empty_cart();
                // 返回感謝購物頁面跳轉
                return array(
                    'result' => 'success',
//                'redirect' => $order->get_checkout_payment_url(true)
                    'redirect' => json_decode($result)->payment_url
                );

            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }

        public function receive_response()
        {
            $notify_type = $_REQUEST['notify_type'];
            $notify_message = $_REQUEST['notify_message'];

            if (!$notify_type || !$notify_message) {
                http_response_code(404);
                exit;
            }

            $order_data = json_decode($notify_message);
            $order = new WC_Order($order_data->order_id);

            if ($notify_type == 'order_expired') {
                $order->update_status(
                    'failed',
                    sprintf(
                        __('Error return code: %1$s', 'woocommerce'),
                        $order_data->status_code
                    )
                );
            } elseif ($notify_type == 'order_confirm') {
                $order->payment_complete();
            } else {
                // Do nothing
            }

            wp_die();
            exit;
        }

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            try {
                
                $response_data = $this->client->refund($data);

                if ($response_data->returnCode !== '0000') {
                    return false;
                }

                return true;
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    function add_pchomepay_gateway_class($methods)
    {
        $methods[] = 'WC_Gateway_PChomepay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_pchomepay_gateway_class');
}