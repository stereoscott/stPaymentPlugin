<?php

class stAuthorizeNetSilentPostTest 
{
  protected $url = 'http://your_domain.com/silent_post';

  protected $data = array(
  	'x_response_code' => '1',
  	'x_response_subcode' => '1',
  	'x_response_reason_code' => '1',
  	'x_response_reason_text' => 'This transaction has been approved.',
  	'x_auth_code' => '',
  	'x_avs_code' => 'P',
  	'x_trans_id' => '1821199455',
  	'x_invoice_num' => '',
  	'x_description' => '',
  	'x_amount' => '9.95',
  	'x_method' => 'CC',
  	'x_type' => 'auth_capture',
  	'x_cust_id' => '1',
  	'x_first_name' => 'John',
  	'x_last_name' => 'Smith',
  	'x_company' => '',
  	'x_address' => '',
  	'x_city' => '',
  	'x_state' => '',
  	'x_zip' => '',
  	'x_country' => '',
  	'x_phone' => '',
  	'x_fax' => '',
  	'x_email' => '',
  	'x_ship_to_first_name' => '',
  	'x_ship_to_last_name' => '',
  	'x_ship_to_company' => '',
  	'x_ship_to_address' => '',
  	'x_ship_to_city' => '',
  	'x_ship_to_state' => '',
  	'x_ship_to_zip' => '',
  	'x_ship_to_country' => '',
  	'x_tax' => '0.0000',
  	'x_duty' => '0.0000',
  	'x_freight' => '0.0000',
  	'x_tax_exempt' => 'FALSE',
  	'x_po_num' => '',
  	'x_MD5_Hash' => 'A375D35004547A91EE3B7AFA40B1E727',
  	'x_cavv_response' => '',
  	'x_test_request' => 'false',
  	'x_subscription_id' => 'R2451D6D13EA', #'365314',
  	'x_subscription_paynum' => '1',
  );
  
  public function __construct($url = null) {
    if ($url !== null) {
      $this->setUrl($url);
    }
  }
  
  public function getUrl() {
    return $this->url;
  }

  public function setUrl($v) {
    $this->url = $v;
  }

  public function getData() {
    return $this->data;
  }

  public function setData($v) {
    $this->data = $v;
  }

  public function doTest()
  {
    $parameters = array(
    	'http' => array(
    		'method' => 'POST',
    		'header'  => 'Content-type: application/x-www-form-urlencoded' . "\r\n",
    		'content' => http_build_query($this->data),
    	)
    );
    
    $start = microtime(true);
    $pointer = fopen($this->url, 'rb', false, stream_context_create($parameters));
    if (!$pointer) {
    	die('Cannot open URL.');
    }

    $response = stream_get_contents($pointer);

    fclose($pointer);

    $elapsed = microtime(true) - $start;

    if ($response === false) {
    	die('Cannot read from URL');
    }

    echo 'Request took ' . round($elapsed, 2) . ' seconds. ';
    echo 'Response (' . strlen(utf8_decode($response)) . ' Bytes) was:<hr/>' . $response;
  }
 
}