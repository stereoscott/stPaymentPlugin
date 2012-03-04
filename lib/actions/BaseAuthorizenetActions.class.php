<?php


class BaseAuthorizenetActions extends sfActions
{
  
  // http://sbdev.identityfraud.com/authorizenet/silentPost?merchantAccount=ifi
  public function executeSilentPost(sfWebRequest $request)
  {

    $response = new stAuthorizeNetSilentPostResponse();
      
    // log the transaction
    $transaction = $response->getAuthNetTransaction();
    $transaction->save();
        
    $logFile = stAuthorizeNet::getLogFilePath();
    if ($logFile) {
      file_put_contents($logFile, "----Response----\n".implode('|', $response->response)."\n\n", FILE_APPEND);
    }
    
    if ($response->isARB()) {
      if ($response->approved) {
        // typically you want to send an email recipt here
      } elseif ($response->declined || $response->error) {
        // email an admin about this
      }
    }
    
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
  
}