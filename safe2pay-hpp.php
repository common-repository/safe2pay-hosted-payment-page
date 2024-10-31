<?php
/*
  Plugin Name: Safe2Pay Hosted Payment Page
  Plugin URI: http://www.safe2pay.com.au/
  Description: Allows integration with Safe2Pay's hosted payment page
  Version: 2.1
  Author: Iman Biglari, DataQuest PTY Ltd.
  Author URI: http://www.dataquest.com.au/
 */

if (!defined("ABSPATH")) {
    exit;
}
require_once(ABSPATH . "wp-includes/pluggable.php");
define("SAFE2PAY_HPP_SETTING_GROUP", "safe2pay-hpp-settings-group");
define("SAFE2PAY_HPP_USER_ID", "safe2pay_hpp_user_id");
define("SAFE2PAY_HPP_TOKEN", "safe2pay_hpp_token");
define("SAFE2PAY_HPP_CURRENCY_UNIT", "safe2pay_hpp_currency_unit");
define("SAFE2PAY_HPP_SUCCESS_PAGE", "safe2pay_hpp_success_page");
define("SAFE2PAY_HPP_FAILURE_PAGE", "safe2pay_hpp_failure_page");

define("SAFE2PAY_HPP_LANDING_PAGE", "/s2phpp");
define("SAFE2PAY_HPP_RESULT_PAGE", "/s2phppchk");

function safe2pay_hpp_manual_invoice_entry_page() {
    $unit = strcmp(get_option(SAFE2PAY_HPP_CURRENCY_UNIT), SAFE2PAY_HPP_CURRENCY_UNIT . "_dollars") ? "&#162;" : '$';
    return "
    <form id='invoice' method='get' action='/s2phpp'>
        <table>
            <tr>
                <td style='text-align: right;'>
                    <label for='invoice'>Invoice Number:&nbsp;</label>
                </td>
                <td class='safe2pay-hpp-invoice'>
                    <input type='text' name='invoice' class='safe2pay-hpp-invoice' aria-required='true' aria-invalid='false'>
                </td>
            </tr>
            <tr>
                <td style='text-align: right;'>
                    <label for='amount'>Amount ($unit):&nbsp;</label>
                </td>
                <td class='safe2pay-hpp-amount'>
                    <input type='text' name='amount' class='safe2pay-hpp-amount' aria-required='true' aria-invalid='false'>
                </td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>
                    <input type='submit' value='Pay'/>
                </td>
            </tr>
        </table>
    </form>
    ";
}

function safe2pay_hpp_initiate_payment($invoiceNo, $amount) {
    //Check to see if the CF-Connecting-IP header exists.
    $ipAddress = filter_var($_SERVER["HTTP_CF_CONNECTING_IP"], FILTER_SANITIZE_STRING);
    if (empty($ipAddress)) {
        $ipAddress = filter_var($_SERVER["REMOTE_ADDR"], FILTER_SANITIZE_STRING);
    }
    $url = "https://gateway.safe2pay.com.au/requestToken.php";
    $username = get_option(SAFE2PAY_HPP_USER_ID);
    $password = get_option(SAFE2PAY_HPP_TOKEN);
    $f = $amount * (strcmp(get_option(SAFE2PAY_HPP_CURRENCY_UNIT), SAFE2PAY_HPP_CURRENCY_UNIT . "_dollars") ? 1 : 100);
    $data = array(
        'InvoiceNo' => $invoiceNo . "##" . hash("crc32b", time()),
        'Amount' => $f,
        'UserIP' => $ipAddress,
        'Recurring' => 0,
        'RedirectURL' => get_site_url() . SAFE2PAY_HPP_RESULT_PAGE
    );

    $args = array(
        'body' => $data,
        'timeout' => '30',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ),
        'cookies' => array()
    );
    $response = wp_remote_post($url, $args);

    $httpCode = wp_remote_retrieve_response_code($response);

    if ($httpCode == 200) {
        $token = wp_remote_retrieve_body($response);
        ?>
        <form id="transfer" action="https://gateway.safe2pay.com.au/payment.php" method="post">
            <?php
            echo '<input type="hidden" name="Token" value="' . $token . '"/>';
            ?>
            <input type="submit" style="display: none;"/>
        </form>
        <script type="text/javascript">
            document.getElementById('transfer').submit();
        </script>
        <?php
        die;
    } else {
        // Handle errors here
        echo "Could not communicate with gateway. Please contact support!<br/>";
        print_r($response);
    }
}

function safe2pay_hpp_check_payment($session) {
    //Check to see if the CF-Connecting-IP header exists.
    $ipAddress = filter_var($_SERVER["HTTP_CF_CONNECTING_IP"], FILTER_SANITIZE_STRING);
    if (empty($ipAddress)) {
        $ipAddress = filter_var($_SERVER["REMOTE_ADDR"], FILTER_SANITIZE_STRING);
    }
    $url = "https://gateway.safe2pay.com.au/verifyPayment.php";
    $username = get_option(SAFE2PAY_HPP_USER_ID);
    $password = get_option(SAFE2PAY_HPP_TOKEN);
    $data = array(
        'Token' => $session
    );

    $args = array(
        'body' => $data,
        'timeout' => '30',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ),
        'cookies' => array()
    );
    $response = wp_remote_post($url, $args);

    $httpCode = wp_remote_retrieve_response_code($response);

    if ($httpCode == 200) {
        $data = wp_remote_retrieve_body($response);
        $status = json_decode($data);
        mail("i@dataquest.com.au", "HPP", $data);
        if ($status->status === "OK") {
            $url = get_page_link(get_option(SAFE2PAY_HPP_SUCCESS_PAGE));
        } else {
            $url = get_page_link(get_option(SAFE2PAY_HPP_FAILURE_PAGE));
        }
        ?>
        <script type="text/javascript">
            window.location = "<?= $url ?>";
        </script>
        <?php
        wp_die();
    } else {
        // Handle errors here
        echo "Could not communicate with gateway. Please contact support!<br/>";
    }
}

function safe2pay_hpp_register_settings() {
    //register our settings
    register_setting(SAFE2PAY_HPP_SETTING_GROUP, SAFE2PAY_HPP_USER_ID);
    register_setting(SAFE2PAY_HPP_SETTING_GROUP, SAFE2PAY_HPP_TOKEN);
    register_setting(SAFE2PAY_HPP_SETTING_GROUP, SAFE2PAY_HPP_CURRENCY_UNIT);
    register_setting(SAFE2PAY_HPP_SETTING_GROUP, SAFE2PAY_HPP_SUCCESS_PAGE);
    register_setting(SAFE2PAY_HPP_SETTING_GROUP, SAFE2PAY_HPP_FAILURE_PAGE);
}

function safe2pay_hpp_settings_page() {
    ?>
    <div class="wrap">
        <h1>Safe2Pay Hosted Payment Page</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields(SAFE2PAY_HPP_SETTING_GROUP);
            do_settings_sections(SAFE2PAY_HPP_SETTING_GROUP);
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Instructions</th>
                    <td>
                        <h4>How to generate the link</h4>
                        <div>Your link should contain invoice number and amount (in cents).</div>
                        <div>You can use <pre style="display:inline"><?= get_site_url() . "/s2phpp?invoice=[Invoice No]&amount=[Amount, in cents]" ?></pre> on your invoices.</div>
                        <h4>Example</h4>
                        <ul>
                            <li style="list-style: circle">Invoice Number: A-123</li>
                            <li style="list-style: circle">Amount: $149.95</li>
                            <li style="list-style: circle">Link: <a href="#"><?= get_site_url() . "/s2phpp?invoice=A-123&amount=14995" ?></a></li>
                        </ul>
                    </td> 
                </tr>
                <tr valign="top">
                    <th scope="row">Gateway User ID</th>
                    <td><input type="text" required style="width:85%;" name="<?= SAFE2PAY_HPP_USER_ID ?>" value="<?php echo esc_attr(get_option(SAFE2PAY_HPP_USER_ID)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Gateway Token</th>
                    <td><input type="password" required style="width:85%;" name="<?= SAFE2PAY_HPP_TOKEN ?>" value="<?php echo esc_attr(get_option(SAFE2PAY_HPP_TOKEN)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Amount will be entered in</th>
                    <td>
                        <input type="radio" name="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>" value="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>_dollars" id="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>_dollars" <?php checked(get_option(SAFE2PAY_HPP_CURRENCY_UNIT), SAFE2PAY_HPP_CURRENCY_UNIT . "_dollars"); ?>/>
                        <label for="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>_dollars">Dollars</label><br/>
                        <input type="radio" name="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>" value="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>_cents" id="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>_cents" <?php checked(get_option(SAFE2PAY_HPP_CURRENCY_UNIT), SAFE2PAY_HPP_CURRENCY_UNIT . "_cents"); ?>/>
                        <label for="<?= SAFE2PAY_HPP_CURRENCY_UNIT ?>_cents">Cents</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Payment successful page</th>
                    <td><?php
                        wp_dropdown_pages(array(
                            'name' => SAFE2PAY_HPP_SUCCESS_PAGE,
                            'sort_column' => 'menu_order, post_title',
                            'selected' => esc_attr(get_option(SAFE2PAY_HPP_SUCCESS_PAGE))
                        ));
                        ?></td>
                </tr>
                <tr>
                    <th scope="row">Payment cancelled page</th>
                    <td><?php
                        wp_dropdown_pages(array(
                            'name' => SAFE2PAY_HPP_FAILURE_PAGE,
                            'sort_column' => 'menu_order, post_title',
                            'selected' => esc_attr(get_option(SAFE2PAY_HPP_FAILURE_PAGE))
                        ));
                        ?></td>
                </tr>
            </table>

            <?php submit_button();
            ?>

        </form>
    </div>
    <?php
}

function safe2pay_hpp_settings() {
    add_menu_page("Safe2Pay Hosted Payment Page", "Safe2Pay Hosted Payment Page", "administrator", __FILE__, "safe2pay_hpp_settings_page", "dashicons-tickets-alt");
    add_action("admin_init", "safe2pay_hpp_register_settings");
}

function safe2pay_hpp_parse_request() {
    $urlArr = parse_url($_SERVER['REQUEST_URI']);
    if ($urlArr["path"] === SAFE2PAY_HPP_LANDING_PAGE) {
        $invoice = filter_input(INPUT_GET, "invoice");
        $amount = filter_input(INPUT_GET, "amount");
        if (!empty($invoice)) {
            if (!empty($amount)) {
                safe2pay_hpp_initiate_payment($invoice, $amount);
                wp_die();
            }
        }
    } else if ($urlArr["path"] === SAFE2PAY_HPP_RESULT_PAGE) {
        $session = filter_input(INPUT_GET, "Session");
        if (!empty($session)) {
            safe2pay_hpp_check_payment($session);
            wp_die();
        }
    }
}

function safe2pay_hpp_init() {
    add_action("admin_menu", "safe2pay_hpp_settings");

    add_action('parse_request', 'safe2pay_hpp_parse_request');

    add_shortcode('s2phpp-manual-invoice-entry-page', 'safe2pay_hpp_manual_invoice_entry_page');
}

add_action('plugins_loaded', 'safe2pay_hpp_init', 0);
