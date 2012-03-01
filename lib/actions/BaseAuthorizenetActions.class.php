<?php


class BaseAuthorizenetActions extends sfActions
{
  
  // http://sbdev.identityfraud.com/authorizenet/silentPost?merchantAccount=ifi
  public function executeSilentPost(sfWebRequest $request)
  {
    
    // will need to update this with a posted parameter that is set in the silent post url as a get parameter
    $merchantAccountId = $request->getParameter('merchantAccount', 1); 
    
    $existingPurchase = null;
    $processRenewal = false;
    $membership = false;

    $response = new stAuthorizeNetSilentPostResponse();
    
    $logFile = stAuthorizeNet::getLogFilePath();
    if ($logFile) {
      file_put_contents($logFile, "----Response----\n".implode('|', $response->response)."\n\n", FILE_APPEND);
    }
    
    if ($response->isARB()) {
      
      // does this subscription exist? find the oldest one
      $existingPurchase = Doctrine::getTable('Purchase')->createQuery('p')
        ->where('p.transaction_id = ?', $response->subscription_id)
        ->orderBy('p.created_at ASC') // put oldest (smallest) at the top of the result set
        ->fetchOne();
        
      if ($existingPurchase && $existingPurchase['BusinessMembership']->exists()) {
        // we've found a membership
        $membership = $existingPurchase['BusinessMembership'];
        // when is the anniversary date? 
        /*
        $anniversaryDate = $membership['Business']['anniversary_date'];
        // does the date exist and is right now within 10 days on either side of it? if so lets process the renewal
        */
        // OR can we use the paynum value to figure out if we should renew
        // if this is annual, do it always
        // if this is monthly, do it on multiples of 12 (start at 12, because that really is the 13th payment since the first was not ARB)

        if ($membership->isYearly()) {
          $processRenewal = true;
        } elseif ($membership->isMonthly()) {
          $paymentNumber = $response->subscription_paynum;
          if (($paymentNumber % 12) == 0) { 
            $processRenewal = true;
          }
        } else {
          sfContext::getInstance()->getLogger()->crit('Unknown payment term for membership id'.$membership['id']);
        }
        
      }
    }
    
    // log the purchase
    $purchase = new Purchase();
    
    $purchase->fromArray(array(
      'transaction_id'      => $response->subscription_id,
      'product_total'       => ($response->amount - $response->tax),
      'tax_total'           => $response->tax,
      'savings_total'       => null,
      'grand_total'         => $response->amount,
      'payment_status'      => $response->getPaymentStatus(),
      'bill_first_name'     => $response->first_name,
      'bill_last_name'      => $response->last_name,
      'bill_company'        => $response->company,   
      'bill_street'         => $response->address,   
      'bill_street_2'       => null,                 
      'bill_city'           => $response->city,      
      'bill_region'         => $response->state,     
      'bill_region_other'   => $response->zip_code,  
      'bill_postal_code'    => $response->phone,     
      'bill_country'        => $response->country,   
      'bill_phone'          => $response->phone,     
      'bill_fax'            => $response->fax,       
      'coupon_code'         => ($existingPurchase ? $existingPurchase['coupon_code'] : null),
      'user_id'             => ($existingPurchase ? $existingPurchase['user_id'] : null),
      'merchant_account_id' => $merchantAccountId,
    ));
        
    /*
    if ($existingPurchase) {
      foreach ($existingPurchase['PurchaseProduct'] as $pp) {
        $purchase->PurchaseProduct[] = $pp->copy();
      }
    }
    */
    
    $purchase->save();
        
    if ($processRenewal && $membership) {

      $conn = Doctrine::getTable('Purchase')->getConnection();
      try 
      {
        $conn->beginTransaction();
      
        // create new business membership using old one as a model
        $newMembership = $membership->copy();
        $now = date('Y-m-d H:i:s', time());
        $newMembership['created_at'] = $now;
        $newMembership['updated_at'] = $now;
        $newMembership['purchase_id'] = $purchase['id'];
      
        // process this one
        $productType = $newMembership->hasRelation('Product') ? $newMembership['Product']->getProductType() : null;

        // do all the post processing with the insurance records
        // first parameter is false because cobrand remains unchanged
        $newMembership['Business']->postRegistration(false, $productType, $conn);
      
        // Relate the business membership (think of it like a subscription) to the original transaction
        $newMembership['purchase_id'] = $purchase['id'];
        $newMembership->save($conn);
      
        //send out the confirmation email
        try {
          // we need to populate the purchaes product for the email
          if ($existingPurchase) {
            foreach ($existingPurchase->PurchaseProducts as $record) {
                $purchase->PurchaseProducts[] = $record->copy(false);
            }
          }
          $this->sendSignupEmail($purchase, $membership['Business']);
        } catch (Exception $e) {
          // log it
          sfContext::getInstance()->getLogger()->crit('Could not send renewal confirmation email from ARB subscription');
        }
        
        $conn->commit();      
      } catch (Doctrine_Exception $e) {
        $conn->rollback();
        throw $e;
      }
    }
    
    sfConfig::set('sf_web_debug', false);
    
    echo "<pre>";
    print_r($response);
    echo "</pre>";
    
    $this->getResponse()->setContent('OK');
    
    return sfView::NONE;    
        
  }
  
  public function executeSilentPostTest(sfWebRequest $request)
  {
    
    $url = sfContext::getInstance()->getController()->genUrl('authorizenet/silentPost', true);
    
    $test = new stAuthorizeNetSilentPostTest($url);

    sfConfig::set('sf_web_debug', false);
    $this->setLayout(false);
  
    $test->doTest();
  
    return sfView::NONE;  
  }
 
  /**
   * Need to move this up out of the plugin
   *
   * @param string $purchase 
   * @param string $business 
   * @return void
   */
  protected function sendSignupEmail($purchase, $business)
  {
    
    if ($cobrand = $business->getCobrand()) {
      $url = $cobrand->getUrl();
    } else {
      $url = sfConfig::get('app_site_url');
    }
    
    $context = array(
      'checkoutType' => 'renewal',
      'business' => $business, 
      'purchase' => $purchase, 
      'url' => $url
    );
    
    $mailFrom = sfConfig::get('app_mail_from');
    $mailTo = $business->User->getEmailAddress();
    
    $template = 'ifi_register/emailSignup';
    
    require_once(sfConfig::get('sf_lib_dir').'/vendor/swift/lib/swift_init.php');
    // Create the mailer and message objects
    $mailer = Swift_Mailer::newInstance(Swift_MailTransport::newInstance());
    $message = Swift_Message::newInstance('Renewal Confirmation')
      ->setFrom($mailFrom)
      ->setTo($mailTo)
      ->setBcc(sfConfig::get('app_mail_bcc'))
      ->setBody($this->getPartial($template, $context), 'text/html')
      ->addPart($this->getPartial($template.'Text', $context));
    // Send
    
    if (isset($this->cobrandEmailAddress) && strlen($this->cobrandEmailAddress)) {
      $message->addBcc($cobrandEmailAddress);
    }
    
    $result = $mailer->send($message);
  }
}