<?
use \Bitrix\Sale\Order;

define("STOP_STATISTICS", true);
define('NO_AGENT_CHECK', true);
define("DisableEventsCheck", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule("sale");


// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$json = $_POST['Data'];
	$signature = $_POST['Signature'];
	
	// Преобразуем из JSON в Object
	$data = json_decode($json);
	
	if($arOrder = CSaleOrder::GetByID(IntVal($data->AccountNo)))
	{
		// инициализация переменных платежной системы
		CSalePaySystemAction::InitParamArrays($arOrder, $arOrder["ID"]);
	
		// Использование цифровой подписи указывается в настройках личного кабинета
		$isUseSignature = CSalePaySystemAction::GetParamValue("IS_SIGNATURE");
		
		// Проверяем использование цифровой подписи
		if($isUseSignature == 'Y') {
		
		// Секретное слово указывается в настройках личного кабинета
		$secretWord = CSalePaySystemAction::GetParamValue("SECRET_WORD");
		
			// Проверяем цифровую подпись
			if($signature == computeSignature($json, $secretWord)) {
			
				updateOrder($data);
				
				$status = 'OK | payment received';
				header("HTTP/1.0 200 OK");
			} else {
				
				$status = 'FAILED | wrong notify signature'; 
				header("HTTP/1.0 400 Bad Request");
			}
		} else {
			updateOrder($data);

			$status = 'OK | payment received';
			header("HTTP/1.0 200 OK");
		}
	} else {
		$status = 'FAILED | ID заказа неизвестен'; 
		header("HTTP/1.0 200 Bad Request");
	}
}

function computeSignature($json, $secretWord) {
    $hash = NULL;
    
	$secretWord = trim($secretWord);
	
    if (empty($secretWord))
		$hash = strtoupper(hash_hmac('sha1', $json, ""));
    else
        $hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
    return $hash;
}

// обновление статуса заказа
function updateOrder($data) {
	// Изменился статус счета
	if($data->CmdType == '3') {	
		// Счет оплачен
		if($data->Status == '3') {		
			// получение заказа по номеру лицевого счета
			$order = CSaleOrder::GetByID($data->AccountNo);

			// заказ существует
			if(isset($order)) {
				CSalePaySystemAction::InitParamArrays($order, $order["ID"]);
				
				// помечаем заказ как оплаченный
				$arFields = array(
					"PAYED" => "Y",
					"STATUS_ID" => "F",
				);
		 
				CSaleOrder::Update($order["ID"], $arFields);
			}		
		}
	}
}

?>