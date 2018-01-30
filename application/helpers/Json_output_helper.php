<?php
defined('BASEPATH') or exit('No direct script access allowed');


function json_output($statusHeader, $response)
{
	$ci = &get_instance();
	$ci->output->set_content_type('application/json');
	$ci->output->set_status_header($statusHeader);
	$ci->output->set_output(json_encode($response));
}

function getRequestBodyArray($body)
{
	if (isset($body)) {
		$result = json_decode($body);
		if (json_last_error() === JSON_ERROR_NONE) {
			return $result;
		} else {
			json_output(100, array('status' => 400, 'message' => 'invalid request body.', 'params' => $body));
		}
	} else {
		json_output(100, array('status' => 400, 'message' => 'invalid request body.', 'params' => $body));
	}
}

function encryptDcrypt($string, $action = 'e')
{
    // you may change these values to your own
	$secret_key = 'my_simple_secret_key';
	$secret_iv = 'my_simple_secret_iv';

	$output = false;
	$encrypt_method = "AES-256-CBC";
	$key = hash('sha256', $secret_key);
	$iv = substr(hash('sha256', $secret_iv), 0, 16);

	if ($action == 'e') {
		$output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
	} else if ($action == 'd') {
		$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
	}

	return $output;
}

