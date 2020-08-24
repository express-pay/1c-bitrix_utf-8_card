<?
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("sale");

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

log_info('payment','Begin payment process');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

	log_info('payment','GET');
	if(isset($_REQUEST['result']))
	{

		log_info('payment','result');
		if($_REQUEST['result'] == 'success' && validSignature($_REQUEST['Signature']))
		{

			log_info('payment','success');
			$inv_id = $_REQUEST['ExpressPayAccountNo'];
			$out_summ = $_REQUEST['ExpressPayAmount'];

			$invoice_template = 'Счет успешно оплачен.<br/>
						Сумма оплаты: <b>##SUM## BYN</b><br />';
															
			$invoice_description = str_replace("##SUM##", $out_summ, $invoice_template);
				
			$result = $invoice_description;

			log_info('payment','result: ' . $result);

			echo $result;
		}
		else
		{
			log_info('payment','FAIL REQUEST: ' . json_encode($_REQUEST));
			echo 'При попытке оплаты произошла ошибка.';
		}
	}
	else
	{

		$isTest = CSalePaySystemAction::GetParamValue("EXPRESSPAY_CARD_IS_TEST_API");
		$baseUrl = "https://api.express-pay.by/v1/";
		
		if($isTest == 'Y')
			$baseUrl = "https://sandbox-api.express-pay.by/v1/";
		
		$url = $baseUrl . "web_cardinvoices";

		$request_params = getInvoiceParam();

		log_info('payment','REQUEST PARAMS: ' . json_encode($request_params));

		$button         = '<form method="POST" action="'.$url.'">';

        foreach($request_params as $key => $value)
        {
            $button .= "<input type='hidden' name='$key' value='$value'/>";
        }

        $button .= '<input type="submit" class="checkout_button" name="submit_button" value="Оплатить счет" />';
		$button .= '</form>';
		
		echo $button;
	}
	
}

function getInvoiceParam()
{
	$inv_id = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ID"];//Номер заказа
	$shouldPay = (strlen(CSalePaySystemAction::GetParamValue("SHOULD_PAY", '')) > 0) ? CSalePaySystemAction::GetParamValue("SHOULD_PAY", 0) : $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["SHOULD_PAY"];
	$out_summ = number_format(floatval($shouldPay), 2, ',', '');//Формирование суммы с 2 числами после ","

	$token = CSalePaySystemAction::GetParamValue("EXPRESSPAY_CARD_TOKEN");
	$secret_word = CSalePaySystemAction::GetParamValue("EXPRESSPAY_CARD_SECRET_WORD");
	$serviceId = CSalePaySystemAction::GetParamValue("EXPRESSPAY_CARD_SERVICE_ID");
	$info_template = CSalePaySystemAction::GetParamValue("EXPRESSPAY_CARD_INFO_TEMPLATE");
	$info = str_replace("##ORDER_ID##", $inv_id, $info_template);

	$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	$request_params = array(
		'ServiceId'         => $serviceId,
		'AccountNo'         => $inv_id,
		'Amount'            => $out_summ,
		'Currency'          => 933,
		'ReturnType'        => 'redirect',
		'ReturnUrl'         => $url."&result=success&ExpressPayAmount={$out_summ}" ,
		'FailUrl'           => $url."&result=fail",
		'Expiration'        => '',
		'Info'              => $info,
	);

	$request_params['Signature'] = compute_signature($request_params, $token, $secret_word);

	return $request_params;

}

function log_error_exception($name, $message, $e) {
	expresspay_log($name, "ERROR" , $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
}

function log_error($name, $message) {
	expresspay_log($name, "ERROR" , $message);
}

function log_info($name, $message) {
	expresspay_log($name, "INFO" , $message);
}

function expresspay_log($name, $type, $message) {
	$log_url = dirname(__FILE__) . '/log';

	if(!file_exists($log_url)) {
		$is_created = mkdir($log_url, 0777);

		if(!$is_created)
			return;
	}

	$log_url .= '/express-pay-' . date('Y.m.d') . '.log';

	file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);

}

function compute_signature($request_params, $token, $secret_word, $method = 'add_invoice') {
	$secret_word = trim($secret_word);
	$normalized_params = array_change_key_case($request_params, CASE_LOWER);
	$api_method = array( 
		'add_invoice' => array(
							"serviceid",
							"accountno",
							"expiration",
							"amount",
							"currency",
							"info",
							"returnurl",
							"failurl",
							"language",
							"sessiontimeoutsecs",
							"expirationdate",
							"returntype"),
		'add_invoice_return' => array(
							"accountno"
		)
	);

	$result = $token;

	foreach ($api_method[$method] as $item)
		$result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';

	$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

	return $hash;
}

function validSignature($signature)
{
	$token = CSalePaySystemAction::GetParamValue("EXPRESSPAY_CARD_TOKEN");
	$secret_word = CSalePaySystemAction::GetParamValue("EXPRESSPAY_CARD_SECRET_WORD");

	$signature_param = array(
		"AccountNo" => $_REQUEST['ExpressPayAccountNumber'],
		);

	$validSignature = compute_signature($signature_param, $token, $secret_word, 'add_invoice_return');

	return $validSignature == $signature;
}

?>

