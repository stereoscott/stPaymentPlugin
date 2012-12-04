<?php

/**
 * PluginAuthNetTransaction
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class PluginAuthNetTransaction extends BaseAuthNetTransaction
{
  public function getGrandTotal(){
    return (int) ($this->getAmount()?$this->getAmount():0) + 
           (int) ($this->getFreight()?$this->getFreight():0) +
           (int) ($this->getDuty()?$this->getDuty():0) + 
           (int) ($this->getTax()?$this->getTax():0);
  }
  
  public function isApproved()
  {
    return $this['response_code'] == AuthorizeNetResponse::APPROVED;
  }
  
  public function isDeclined()
  {
    return $this['response_code'] == AuthorizeNetResponse::DECLINED;
  }
  
  public function isError()
  {
    return $this['response_code'] == AuthorizeNetResponse::ERROR;
  }
  
  public function isHeld()
  {
    return $this['response_code'] == AuthorizeNetResponse::HELD;
  }
  
  public function configureMerchantAccountId($value){
    if(1 == $value):
      $this['merchant_account_id'] = $value;
    else:
      $query = Doctrine::getTable('MerchantAccount')
        ->createQuery('m')
        ->select('m.id')
        ->where('m.config_key = ?',$value);
      $result = $query->fetchOne(array(), Doctrine_Core::HYDRATE_SCALAR);
      sfContext::getInstance()->getLogger()->debug('About to set to '.$result['m_id'].' setMerchantAccountId in PluginAuthNetTransaction');
      $this->merchant_account_id = $result['m_id'];
      sfContext::getInstance()->getLogger()->debug('About to unset query in PluginAuthNetTransaction');
      $query->free();
      unset($query);
    endif;
  }
  
  public function retrieveANS()
  {
  	$existingSub = Doctrine::getTable('AuthNetSubscription')->createQuery('a')
        ->where('a.subscription_id = ?', $this->subscription_id)
		//->andWhere('a.id IN (SELECT cs.auth_net_subscription_id FROM customer_subscription AS cs WHERE cs')
		//->leftJoin('CustomerSubscription cs ON cs.auth_net_subscription_id = a.id')
        ->orderBy('a.created_at DESC') // put newest (smallest) at the top of the result set
        ->fetchOne();
		
	if($existingSub){
		return $existingSub;
	} else {
		sfContext::getInstance()->getLogger()->crit('Unknown AuthNetSubscription for transaction with subscription_id: '.$this->subscription_id);
		throw new Exception('Unknown AuthNetSubscription for transaction with subscription_id: '.$this->subscription_id);
		return false;
	}
  }
  
  public static function fromSilentPost(stAuthorizeNetSilentPostResponse $response) 
  {
    $transaction = new AuthNetTransaction();
    
    $values = array(    
      'response_code'        => $response->response_code,        #1, 2, 3, 4
      'response_subcode'     => $response->response_subcode,     
      'response_reason_code' => $response->response_reason_code, 
      'response_reason_text' => $response->response_reason_text, 
      'auth_code'            => $response->authorization_code,            
      'avs_code'             => $response->avs_response,             
      'trans_id'             => $response->transaction_id,             
      'invoice_num'          => $response->invoice_number,          
      'description'          => $response->description,          
      'amount'               => $response->amount,               
      'method'               => $response->method,                # CC or ECHECK
      'type'                 => $response->transaction_type,      # AUTH_CAPTURE, AUTH_ONLY, CAPTURE_ONLY, CREDIT, PRIOR_AUTH_CAPTURE, VOID
      'cust_id'              => $response->customer_id,              
      'customer_ip'          => $response->customer_ip,          
      'first_name'           => $response->first_name,           
      'last_name'            => $response->last_name,            
      'company'              => $response->company,              
      'address'              => $response->address,              
      'city'                 => $response->city,                 
      'state'                => $response->state,                
      'zip'                  => $response->zip,                  
      'country'              => $response->country,              
      'phone'                => $response->phone,                
      'fax'                  => $response->fax,                  
      'email'                => $response->email_address,
      'ship_to_first_name'   => $response->ship_to_first_name,   
      'ship_to_last_name'    => $response->ship_to_last_name,    
      'ship_to_company'      => $response->ship_to_company,      
      'ship_to_address'      => $response->ship_to_address,      
      'ship_to_city'         => $response->ship_to_city,         
      'ship_to_state'        => $response->ship_to_state,        
      'ship_to_zip'          => $response->ship_to_zip,          
      'ship_to_country'      => $response->ship_to_country,      
      'tax'                  => $response->tax,                  
      'duty'                 => $response->duty,                 
      'freight'              => $response->freight,              
      'tax_exempt'           => $response->tax_exempt,           
      'po_num'               => $response->purchase_order_number,               
      'MD5_Hash'             => $response->md5_hash,             
      'cavv_response'        => $response->cavv_response,        
      'test_request'         => $response->isTestRequest(),         
      'subscription_id'      => $response->subscription_id,
      'subscription_paynum'  => $response->subscription_paynum, 
    );
    
    $transaction->fromArray($values);
    
    return $transaction;
  }
  
  public static function fromAIMResponse(AuthorizeNetAIM_Response $response) 
  {
    $transaction = new AuthNetTransaction();
    $transaction->updateWithAIMResponse($response);
        
    return $transaction;                                        
  }
  
  public function updateWithAIMResponse(AuthorizeNetAIM_Response $response) 
  {
    $values = array(
      'response_code'        => $response->response_code,        #1, 2, 3, 4
      'response_subcode'     => $response->response_subcode,
      'response_reason_code' => $response->response_reason_code,
      'response_reason_text' => $response->response_reason_text,
      'auth_code'            => $response->authorization_code,
      'avs_code'             => $response->avs_response,
      'trans_id'             => $response->transaction_id,
      'invoice_num'          => $response->invoice_number,
      'description'          => $response->description,
      'amount'               => $response->amount,
      'method'               => $response->method,               # CC or ECHECK
      'type'                 => $response->transaction_type,     # AUTH_CAPTURE, AUTH_ONLY, CAPTURE_ONLY, CREDIT, PRIOR_AUTH_CAPTURE, VOID
      'cust_id'              => $response->customer_id,
      'customer_ip'          => (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null),
      'first_name'           => $response->first_name,
      'last_name'            => $response->last_name,
      'company'              => $response->company,
      'address'              => $response->address,
      'city'                 => $response->city,
      'state'                => $response->state,
      'zip'                  => $response->zip_code,
      'country'              => $response->country,
      'phone'                => $response->phone,
      'fax'                  => $response->fax,
      'email'                => $response->email_address,
      'ship_to_first_name'   => $response->ship_to_first_name,
      'ship_to_last_name'    => $response->ship_to_last_name,
      'ship_to_company'      => $response->ship_to_company,
      'ship_to_address'      => $response->ship_to_address,
      'ship_to_city'         => $response->ship_to_city,
      'ship_to_state'        => $response->ship_to_state,
      'ship_to_zip'          => $response->ship_to_zip_code,
      'ship_to_country'      => $response->ship_to_country,
      'tax'                  => $response->tax,
      'duty'                 => $response->duty,
      'freight'              => $response->freight,
      'tax_exempt'           => $response->tax_exempt,
      'po_num'               => $response->purchase_order_number,
      'MD5_Hash'             => $response->md5_hash,
      'cavv_response'        => $response->cavv_response,
      'test_request'         => null,
      'subscription_id'      => null,
      'subscription_paynum'  => null,
    );
    
    $this->fromArray($values);
  }
}