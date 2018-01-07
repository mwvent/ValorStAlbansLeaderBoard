<?php
require_once("settings.php");

// messy error trapping functions 
function error_response_message($messagetext) : bool {
	global $hubVerifyToken;
	global $accessToken;
	error_log ( $messagetext );
	$data = json_decode(file_get_contents("php://input"), true, 512, JSON_BIGINT_AS_STRING);
	if (!empty($data['entry'][0]['messaging'][0]['sender']['id'])) {
		$response = [
			'recipient' =>	[ 'id' => $data['entry'][0]['messaging'][0]['sender']['id'] ],
			'message' =>	[ 'text' => $messagetext ]
		];
		$ch = curl_init('https://graph.facebook.com/v2.6/me/messages?access_token='.$accessToken);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_exec($ch);
		curl_close($ch);
		return true;
	} else {
		return false;
	}
}

function error_trap($errno, $errstr="", $errfile="", $errline="") {
	$data = json_decode(file_get_contents("php://input"), true, 512, JSON_BIGINT_AS_STRING);
	$message = "$errno : $errstr : $errfile : $errline";
	error_response_message("Oh No! A wild error appeared " . PHP_EOL . $message);
	header('HTTP/1.1 200 Success', true, 200);
	exit;
}

function fatal_handler() {
    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if( $error !== NULL) {
        $errno   = isset($error["type"]) ? $error["type"] : "";
        $errfile = isset($error["type"]) ? $error["file"] : "";
        $errline = isset($error["type"]) ? $error["line"] : "";
        $errstr  = isset($error["type"]) ? $error["message"] : "";

        $message = "$errno : $errstr : $errfile : $errline";
        error_response_message("Oh No! A wild error appeared " . PHP_EOL .$message);
        header('HTTP/1.1 200 Success', true, 200);
        exit;
    }
    
    header('HTTP/1.1 200 Success', true, 200);
	exit;
}

// if this is a challenge request handle it and exit
if( isset($_REQUEST['hub_verify_token']) ) {
	if ($_REQUEST['hub_verify_token'] === $hubVerifyToken) {
		echo $_REQUEST['hub_challenge'];
  		exit;
	}
}

// pass data to the handler class
set_error_handler("error_trap"); 
set_exception_handler("error_trap"); 
register_shutdown_function( "fatal_handler" );
require_once("messenger_hook_class.php");
$mhc = new messenger_hook_class($hubVerifyToken, $accessToken);
$mhc->handleMessages(json_decode(file_get_contents("php://input"), true, 512, JSON_BIGINT_AS_STRING));
