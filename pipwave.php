<?php

/**
 *
 * pipwave payment plugin
 *
 * @author pipwave Development Team
 * @version $Id$
 * @package VirtueMart
 * @subpackage payment
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @copyright (C) 2016 pipwave, a division of Dynamic Podium. All rights reserved
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */

defined('_JEXEC') or die('Direct access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentPipwave extends vmPSPlugin {

    // instance of class
    public static $_this = false;

    function __construct(& $subject, $config) {
        parent::__construct($subject, $config);
        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->setConvertable(array('min_amount', 'max_amount', 'cost_per_transaction', 'cost_min_transaction'));
        $this->setConvertDecimal(array('min_amount', 'max_amount', 'cost_per_transaction', 'cost_min_transaction', 'cost_percent_total'));
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     */
    public function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment pipwave Table');
    }

    /**
     * Fields to create the payment table
     *
     * @return string SQL Fileds
     */
    function getTableSQLFields() {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'email_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_min_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)'
        );

        return $SQLfields;
    }

    static function getPaymentCurrency(&$method, $selectedUserCurrency = false) {
        if (empty($method->payment_currency)) {
            $vendor_model = VmModel::getModel('vendor');
            $vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
            $method->payment_currency = $vendor->vendor_currency;
            return $method->payment_currency;
        } else {
            $vendor_model = VmModel::getModel('vendor');
            $vendor_currencies = $vendor_model->getVendorAndAcceptedCurrencies($method->virtuemart_vendor_id);

            if (!$selectedUserCurrency) {
                if ($method->payment_currency == -1) {
                    $mainframe = JFactory::getApplication();
                    $selectedUserCurrency = $mainframe->getUserStateFromRequest("virtuemart_currency_id", 'virtuemart_currency_id', vRequest::getInt('virtuemart_currency_id', $vendor_currencies['vendor_currency']));
                } else {
                    $selectedUserCurrency = $method->payment_currency;
                }
            }

            $vendor_currencies['all_currencies'] = explode(',', $vendor_currencies['all_currencies']);
            if (in_array($selectedUserCurrency, $vendor_currencies['all_currencies'])) {
                $method->payment_currency = $selectedUserCurrency;
            } else {
                $method->payment_currency = $vendor_currencies['vendor_currency'];
            }

            return $method->payment_currency;
        }
    }

    function plgVmConfirmedOrder($cart, $order) {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        VmConfig::loadJLang('com_virtuemart', true);
        VmConfig::loadJLang('com_virtuemart_orders', TRUE);

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists('VmModel'))
            require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'vmmodel.php');
        if (!class_exists('VmConfig'))
            require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
        if (!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');

        $session = JFactory::getSession(); //Get session data
        $this->getPaymentCurrency($method, $order['details']['BT']->payment_currency_id);
        $currency_code_3 = $this->getCurrency($method->payment_currency, 'currency_code_3');
        $email_currency = $this->getEmailCurrency($method);

        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

        $dbValues['payment_name'] = $this->renderPluginName($method) . '<br />' . $method->payment_info;
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_min_transaction'] = $method->cost_min_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['email_currency'] = $email_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);
        $payment_info = '';

        if (!empty($method->payment_info)) {
            $lang = JFactory::getLanguage();
            if ($lang->hasKey($method->payment_info)) {
                $payment_info = vmText::_($method->payment_info);
            } else {
                $payment_info = $method->payment_info;
            }
        }

        $timestamp = time();
        $caller_version = 'Joomla_v' . JVERSION::getShortVersion() . ' ' . vmVersion::$PRODUCT . "_v" . vmVersion::$RELEASE;
        //General variables for post
        $post_variables = array(
            'action' => "initiate-payment",
            'timestamp' => $timestamp,
            'api_key' => $method->apikey,
            'txn_id' => $order['details']['BT']->order_number,
            'amount' => $totalInPaymentCurrency['value'],
            'currency_code' => $currency_code_3,
            'short_description' => 'Payment for Order#' . $order['details']['BT']->order_number,
            'payment_info' => $payment_info,
            'session_info' => array(
                'id' => $session->getId(),
                'ip_address' => $this->getRemoteIPAddress(),
                'language' => $this->getLang()
            ),
            'version' => $caller_version
        );

        // If buyer is a guest
        if (JFactory::getUser()->guest) {
            $post_variables['buyer_info'] = array(
                'id' => $order['details']['BT']->customer_number,
                'email' => $order['details']['BT']->email,
                'contact_no' => $order['details']['BT']->phone_2,
                'country_code' => $this->getCountry($order['details']['BT']->virtuemart_country_id, 'country_2_code')
            );
        } else {
            $post_variables['buyer_info'] = array(
                'id' => $order['details']['BT']->customer_number,
                'email' => $order['details']['BT']->email,
                'first_name' => $order['details']['BT']->first_name,
                'last_name' => $order['details']['BT']->last_name,
                'contact_no' => $order['details']['BT']->phone_2,
                'country_code' => $this->getCountry($order['details']['BT']->virtuemart_country_id, 'country_2_code'),
                'processing_fee_group' => $this->checkRef($cart->user->shopper_groups[0])
            );
        }
        //Set billing info to post
        $post_variables['billing_info'] = array(
            'name' => $order['details']['BT']->first_name . " " . $order['details']['BT']->last_name,
            'address1' => $order['details']['BT']->address_1,
            'address2' => $order['details']['BT']->address_2,
            'city' => $order['details']['BT']->city,
            'state' => $this->getState($order['details']['BT']->virtuemart_state_id),
            'zip' => $order['details']['BT']->zip,
            'country' => $this->getCountry($order['details']['BT']->virtuemart_country_id, 'country_name'),
            'country_iso2' => $this->getCountry($order['details']['BT']->virtuemart_country_id, 'country_2_code'),
            'contact_no' => $order['details']['BT']->phone_2,
            'email' => $order['details']['BT']->email,
        );
        //Set shipping info to post
        $post_variables['shipping_info'] = array(
            'name' => $order['details']['ST']->first_name . " " . $order['details']['ST']->last_name,
            'address1' => $order['details']['ST']->address_1,
            'address2' => $order['details']['ST']->address_2,
            'city' => $order['details']['ST']->city,
            'state' => $this->getState($order['details']['ST']->virtuemart_state_id),
            'zip' => $order['details']['ST']->zip,
            'country' => $this->getCountry($order['details']['ST']->virtuemart_country_id, 'country_name'),
            'country_iso2' => $this->getCountry($order['details']['ST']->virtuemart_country_id, 'country_2_code'),
            'contact_no' => $order['details']['ST']->phone_2,
            'email' => $order['details']['ST']->email
        );

        //Set item info to post
        foreach ($cart->products as $key => $product) {
            $post_variables['item_info'][] = array(
                "name" => $product->product_name,
                "sku" => $product->product_sku,
                "amount" => $product->prices['product_price'],
                "tax_amount" => $product->prices['taxAmount'],
                "description" => $product->product_s_desc,
                "quantity" => $product->quantity,
                "currency_code" => $this->getCurrency($product->prices['product_currency'], 'currency_code_3')
            );
        }
        //Subtotal info (order)
        $post_variables['subtotal_info'] = array(
            0 => array(
                "name" => "order_total",
                "value" => $order['details']['BT']->order_total
            ),
            1 => array(
                "name" => "order_subtotal",
                "value" => $order['details']['BT']->order_subtotal
            ),
            2 => array(
                "name" => "order_tax",
                "value" => $order['details']['BT']->order_tax,
            ),
            3 => array(
                "name" => "order_shipment",
                "value" => $order['details']['BT']->order_shipment,
            ),
            4 => array(
                "name" => "order_shipment_tax",
                "value" => $order['details']['BT']->order_shipment_tax,
            ),
            5 => array(
                "name" => "order_discountAmount",
                "value" => $order['details']['BT']->order_discountAmount,
            ),
        );

        //Api override
        $post_variables['api_override'] = array(
            //Check plgVmOnPaymentResponseReceived for returned page
            "success_url" => JURI::root() . 'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id,
            "fail_url" => JURI::root() . 'index.php?option=com_virtuemart&view=vmplg&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id,
            "notification_url" => JURI::root() . 'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id
        );

        //prepare signature parameters       
        $signatureParam = array(
            'action' => "initiate-payment",
            'txn_id' => $post_variables['txn_id'],
            'amount' => $post_variables['amount'],
            'currency_code' => $post_variables['currency_code'],
            'timestamp' => $timestamp
        );
        $post_variables['signature'] = $this->generateSignature($signatureParam, $method);
        $pipwave_res = $this->_sendRequest($method, $post_variables);

        //Validate post data response
        $app = JFactory::getApplication();
        if (isset($pipwave_res['status']) && $pipwave_res['status'] == 200) {
            $app->redirect($pipwave_res['redirect_url']);
        } else {
            if ($pipwave_res['message'] == "")
                $pipwave_res['message'] = "NULL";
            JError::raiseError("API Response " . $pipwave_res['status'], ' - ' . $pipwave_res['message']);
        }
    }

    function generateSignature($array, $method) {
        $array['api_key'] = $method->apikey;
        $array['api_secret'] = $method->secretcode;
        ksort($array);
        $s = "";
        foreach ($array as $key => $value) {
            $s .= $key . ':' . $value;
        }
        return sha1($s);
    }

    private function _sendRequest($method, $data) {
        // test mode is on
        if ($method->test_mode == '1') {
            $url = "https://staging-api.pipwave.com/payment";
        } else {
            $url = "https://api.pipwave.com/payment";
        }
        $agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('x-api-key:' . $method->apikey));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);


        $response = curl_exec($ch);
        if ($response == false) {
            echo "<pre>";
            echo 'CURL ERROR: ' . curl_errno($ch) . '::' . curl_error($ch);
            die;
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    function plgVmOnUserPaymentCancel() {
        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $order_number = vRequest::getString('on', '');
        $virtuemart_paymentmethod_id = vRequest::getInt('pm', '');
        if (empty($order_number) or empty($virtuemart_paymentmethod_id) or ! $this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
            JError::raiseError("No matching order / matching payment method found.");
            return NULL;
        }
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            JError::raiseError("No matching order / matching payment method found.");
            return NULL;
        }
        if (!($paymentTable = $this->getDataByOrderNumber($order_number))) {
            JError::raiseError("No matching order / matching payment method found.");
            return NULL;
        }
        $method = $this->getPluginMethod($virtuemart_paymentmethod_id);
        $oM = VmModel::getModel('orders');
        $theorder = $oM->getOrder($virtuemart_order_id);

        VmInfo(vmText::_('VMPAYMENT_PIPWAVE_PAYMENT_CANCELLED'));
        $this->handlePaymentUserCancel($virtuemart_order_id);
        return TRUE;
    }

    /**
     * @param $html
     * @return bool|null|string
     */
    function plgVmOnPaymentResponseReceived(&$html) {
        if (!class_exists('VirtueMartCart')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $order_number = vRequest::getString('on', 0);
        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);

        if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            JError::raiseError("No matching order / matching payment method found.");
            return NULL;
        }
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            JError::raiseError("No matching order id found for the order number.");
            return NULL;
        }

        //Load order and method
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($virtuemart_order_id);
        $method = $this->getVmPluginMethod($virtuemart_paymentmethod_id);
        $renderPage = true;

        //Normal Order - redirected back from PG
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $currency = $this->getAmountInCurrency($order['details']['BT']->order_total, $order['details']['BT']->payment_currency_id);
            $payment_name = $this->renderPluginName($this->_currentMethod);

            //Update order status to Pending
            $order['order_status'] = $this->getNewStatus($method);
            $order['customer_notified'] = 1;
            $order['comments'] = '';
            if ($order['order_status'] != $this->_currentMethod->status_pending)
                $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);
            //We delete the old stuff
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $renderPage = false;
            //IPN from pipwave
            header('HTTP/1.1 200 OK');
            echo "OK";
            $post_data = json_decode(file_get_contents('php://input'), true);
            $timestamp = (isset($post_data['timestamp']) && !empty($post_data['timestamp'])) ? $post_data['timestamp'] : time();
            $pw_id = (isset($post_data['pw_id']) && !empty($post_data['pw_id'])) ? $post_data['pw_id'] : '';
            $order_number = (isset($post_data['txn_id']) && !empty($post_data['txn_id'])) ? $post_data['txn_id'] : '';
            $amount = (isset($post_data['amount']) && !empty($post_data['amount'])) ? $post_data['amount'] : '';
            $final_amount = (isset($post_data['final_amount']) && !empty($post_data['final_amount'])) ? $post_data['final_amount'] : 0.00;
            $currency_code = (isset($post_data['currency_code']) && !empty($post_data['currency_code'])) ? $post_data['currency_code'] : '';
            $transaction_status = (isset($post_data['transaction_status']) && !empty($post_data['transaction_status'])) ? $post_data['transaction_status'] : '';
            $payment_method = 'pipwave' . (!empty($post_data['payment_method_title']) ? (" - " . $post_data['payment_method_title']) : "");
            $signature = (isset($post_data['signature']) && !empty($post_data['signature'])) ? $post_data['signature'] : '';
            $signatureParam = array(
                'timestamp' => $timestamp,
                'pw_id' => $pw_id,
                'txn_id' => $order_number,
                'amount' => $amount,
                'currency_code' => $currency_code,
                'transaction_status' => $transaction_status,
            );

            $generatedSignature = $this->generateSignature($signatureParam, $this->_currentMethod);
            if ($signature != $generatedSignature) {
                $transaction_status = -1;
            }
            $with_warning_msg = ($post_data['status'] == 3001) ? " (with warning)" : '';

            $note = array();
            $note[] = sprintf("Paid with: %s", $payment_method);

            switch ($transaction_status) {
                case 5: // pending
                    $note[] = "Payment Status: Pending$with_warning_msg";
                    $status = $method->status_pending;
                    break;
                case 1: // failed
                    $note[] = "Payment Status: Failed$with_warning_msg";
                    $status = $method->status_failed;
                    break;
                case 2: // cancelled
                    $note[] = "Payment Status: Cancelled$with_warning_msg";
                    $status = $method->status_cancelled;
                    break;
                case 10: // complete
                    $note[] = "Payment Status: Completed$with_warning_msg";
                    $status = $method->status_paid;
                    break;
                case 20: // refunded
                    $note[] = "Payment Status: Refunded$with_warning_msg";
                    $status = $method->status_full_refunded;
                    break;
                case 25: // partial refunded
                    $note[] = "Payment Status: Refunded$with_warning_msg";
                    $status = $method->status_partial_refunded;
                    break;
                case -1: // signature mismatch
                    $note[] = "Signature mismatch$with_warning_msg";
                    break;
                default:
                    $note[] = "\nUnknown payment status\n";
            }

            $order_currency = $this->getCurrency($order['details']['BT']->payment_currency_id, 'currency_code_3');
            if (in_array($transaction_status, array(10, 20, 25))) {
                $note[] = sprintf("Currently paid : %s %s", $currency_code, $final_amount);
            }
            $note[] = sprintf("pipwave Reference ID : %s\n", $pw_id);
            //Compare using order
            if ($currency_code != $order_currency) {
                $note[] = "Currency mismatch";
            }
            if ($amount != number_format($order['details']['BT']->order_total, 2)) {
                $note[] = "Amount mismatch";
            }

            //Update order status
            $order['order_status'] = $status;
            $order['customer_notified'] = 1;
            $note = implode("<br>", $note);
            $order['comments'] = $note;
            $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);
            $this->mod_UserOrder($payment_method, $order_number);
        }

        if ($renderPage) {
            //render order page
            $html = $this->renderByLayout('post_payment', array(
                'order_number' => $order['details']['BT']->order_number,
                'order_pass' => $order['details']['BT']->order_pass,
                'displayTotalInPaymentCurrency' => $currency['display']
            ));
        } else {
            exit;
        }
    }

    /*
     *  Check the user groups with the current payment plugin setting
     *  If matching group is ticked then send the reference ID instead of virtuemart group id
     */

    function checkRef($id) {
        $g[0] = $this->methods[0]->getgroups1;
        $g[1] = $this->methods[0]->getgroups2;
        $g[2] = $this->methods[0]->getgroups3;
        $r[0] = $this->methods[0]->ref_ID1;
        $r[1] = $this->methods[0]->ref_ID2;
        $r[2] = $this->methods[0]->ref_ID3;
        $key = 0;
        while ($id != $g[$key] && $key < sizeof($g)) {
            $key++;
            if ($id == $g[$key]) {
                return $r[$key];
            }
        }
        return ""; //return nothing if no matching ref ID
    }

    /*
     *  Modify payment name of order in plugin 
     *  Will not affect admin side as admin side use id to display
     */

    function mod_UserOrder($payment_name, $order_number) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        // Fields to update.
        $fields = array(
            $db->quoteName('payment_name') . ' = ' . $db->quote($payment_name)
        );

        // Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('order_number') . ' = ' . $db->quote($order_number),
        );

        $query->update($db->quoteName('#__virtuemart_payment_plg_pipwave'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();
    }

    /*
     * Keep backwards compatibility
     * a new parameter has been added in the xml file
     */

    function getNewStatus($method) {
        if (isset($method->status_pending) and $method->status_pending != "") {
            return $method->status_pending;
        } else {
            return 'P';
        }
    }

    /**
     * Display stored payment data for an order
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return NULL;
        }
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency);
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * 
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {
        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        if ($this->_toConvert) {
            $this->convertToVendorCurrency($method);
        }
        //vmdebug('standard checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
                OR ( $method->min_amount <= $amount AND ( $method->max_amount == 0)));
        if (!$amount_cond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries)) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     * 
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }
        return TRUE; // this method was selected , and the data is valid by default
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
     *
     * @param object  $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        if ($this->getPluginMethods($cart->vendorId) === 0) {
            if (empty($this->_name)) {
                vmAdminInfo('displayListFE cartVendorId=' . $cart->vendorId);
                $app = JFactory::getApplication();
                $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
                return FALSE;
            } else {
                return FALSE;
            }
        }

        $html = array();
        $method_name = $this->_psType . '_name';

        foreach ($this->methods as $method) {
            if ($this->checkConditions($cart, $method, $cart->cartPrices)) {

                // the price must not be overwritten directly in the cart
                $prices = $cart->cartPrices;
                $methodSalesPrice = $this->setCartPrices($cart, $prices, $method);

                $method->$method_name = $this->renderPluginName($method);
                $html [] = $this->getPluginHtml($method, $selected, $methodSalesPrice);
            }
        }
        if (!empty($html)) {
            $htmlIn[] = $html;
            return TRUE;
        }

        return FALSE;
    }

    /**
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * 
     * @param VirtueMartCart cart: the current cart
     * @param Array cart_prices: the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * @param $orderDetails
     * @param $data
     * @return null
     */
    function plgVmOnUserInvoice($orderDetails, &$data) {
        if (!($method = $this->getVmPluginMethod($orderDetails['virtuemart_paymentmethod_id']))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return NULL;
        }
        //vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

        if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null == 1 or $orderDetails['order_total'] > 0.00) {
            return NULL;
        }

        if ($orderDetails['order_salesPrice'] == 0.00) {
            $data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
        }
    }

    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     * @return bool|null
     */
    function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }

        if (empty($method->email_currency)) {
            
        } else if ($method->email_currency == 'vendor') {
            $vendor_model = VmModel::getModel('vendor');
            $vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
            $emailCurrencyId = $vendor->vendor_currency;
        } else if ($method->email_currency == 'payment') {
            $emailCurrencyId = $this->getPaymentCurrency($method);
        }
    }

    /**
     * This method is fired when showing or printing an Order
     * It displays the the payment method-specific data.
     *
     * @param string $order_number The order ID
     * @param integer $method_id  Payment method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function getRemoteIPAddress() {
        if (!class_exists('ShopFunctions'))
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php');
        //return ShopFunctions::getClientIP(); internal get ip address function by VirtueMart , return false for local host
        return $_SERVER['REMOTE_ADDR'];
    }

    function getLang() {
        $language = JFactory::getLanguage();
        $tag = $language->getTag();
        return $tag;
    }

    function getCountry($id, $fld) {
        if (!class_exists('ShopFunctions'))
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php');
        return ShopFunctions::getCountryByID($id, $fld);
    }

    function getState($id) {
        if (!class_exists('ShopFunctions'))
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php');
        return ShopFunctions::getStateByID($id);
    }

    function getCurrency($id, $fld) {
        if (!class_exists('ShopFunctions'))
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php');
        return ShopFunctions::getCurrencyByID($id, $fld);
    }

}
