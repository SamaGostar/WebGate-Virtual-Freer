<?
/*
  Virtual Freer
  http://freer.ir/virtual

  Copyright (c) 2021 Zarinpal , zarinpal.com

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v3 (http://www.gnu.org/licenses/gpl-3.0.html)
  as published by the Free Software Foundation.
*/
//-- اطلاعات کلی پلاگین
$pluginData[zarinpal][type] = 'payment';
$pluginData[zarinpal][name] = 'زرین پال';
$pluginData[zarinpal][uniq] = 'zarinpal';
$pluginData[zarinpal][description] = 'مخصوص پرداخت با دروازه پرداخت <a href="http://zarinpal.com">زرین‌پال‌</a>';
$pluginData[zarinpal][author][name] = 'Armin Zahedi';
$pluginData[zarinpal][author][url] = 'https://www.zarinpal.com';
$pluginData[zarinpal][author][email] = 'armin.z@zarinpal.com';

//-- فیلدهای تنظیمات پلاگین
$pluginData[zarinpal][field][config][1][title] = 'مرچنت';
$pluginData[zarinpal][field][config][1][name] = 'merchant';
$pluginData[zarinpal][field][config][2][title] = 'عنوان خرید';
$pluginData[zarinpal][field][config][2][name] = 'title';

//-- تابع انتقال به دروازه پرداخت
function gateway__zarinpal($data)
{
    global $config, $db, $smarty;
    $merchantID = trim($data[merchant]);
    $QR = trim($data[QR]);
    $amount = round($data[amount]);
    $invoice_id = $data[invoice_id];
    $callBackUrl = $data[callback];


    $param_request = array(
        'merchant_id' => $merchantID,
        'amount' => $amount,
        'description' => $data[title] . ' - ' . $data[invoice_id],
        'callback_url' => $callBackUrl
    );
    $jsonData = json_encode($param_request);

    $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
    curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ));


    $result = curl_exec($ch);
    $err = curl_error($ch);
    $result = json_decode($result, true, JSON_PRETTY_PRINT);
    curl_close($ch);
    if ($err) {
        $data[title] = 'خطای سیستم';
        $data[message] = '<font color="red">در اتصال به درگاه زرین‌پال مشکلی به وجود آمد٬ لطفا از درگاه سایر بانک‌ها استفاده نمایید.</font>' . '<br /><a href="index.php" class="button">بازگشت</a>';
        $query = 'SELECT * FROM `config` WHERE `config_id` = "1" LIMIT 1';
        $conf = $db->fetch($query);
        $smarty->assign('config', $conf);
        $smarty->assign('data', $data);
        $smarty->display('message.tpl');
    } else {
        if (empty($result['errors'])) {
            if ($result['data']['code'] == 100) {
                $update[payment_rand] = $result['data']["authority"];
                $sql = $db->queryUpdate('payment', $update, 'WHERE `payment_rand` = "' . $invoice_id . '" LIMIT 1;');
                $db->execute($sql);
                   header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
            }
        } else {
            echo 'Error Code: ' . $result['errors']['code'];
            echo 'message: ' . $result['errors']['message'];

        }
    }

}

//-- تابع بررسی وضعیت پرداخت
function callback__zarinpal($data)
{

    global $db, $get;
    $au = $get['Authority'];
    $ref_id = $get['ref_id'];
    if (strlen($au) == 36) {
        $merchantID = $data[merchant];
        $sql = 'SELECT * FROM `payment` WHERE `payment_rand` = "' . $au . '" LIMIT 1;';
        $payment = $db->fetch($sql);
        $amount = round($payment[payment_amount]);

        $Authority = $_GET['Authority'];
        $param_verify = array("merchant_id" => $merchantID, "authority" => $Authority, "amount" => $amount);
        $jsonData = json_encode($param_verify);
        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));

        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $result = json_decode($result, true);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            if ($payment[payment_status] == 1) {

                if ($result['data']['code'] == 100) {
                    $output[status] = 1;
                    $output[res_num] = $au;
                    $output[ref_num] = $result['data']['ref_id'];
                    $output[payment_id] = $payment[payment_id];
                } else {
                    $output[status] = 0;
                    $output[message] = 'پرداخت توسط زرین‌پال تایید نشد‌.';
                }
            }

            return $output;
        }
    }

}