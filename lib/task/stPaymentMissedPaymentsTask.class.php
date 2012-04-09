<?php

class stPaymentMissedPaymentsTask extends ifiSiteTask
{
  protected $go = false;
  
  protected function configure()
  {
    // add your own arguments here
    // $this->addArguments(array(
    //   new sfCommandArgument('class-names', sfCommandArgument::OPTIONAL, 'A comma separated list of classes to generate. Used to specify the classes to generate if the user doesn\'t want to generate all.'),
    // ));

    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'frontend'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('date', null, sfCommandOption::PARAMETER_OPTIONAL, 'The date of the transaction error Y-m-d, defaults to -7 days'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The database connection', 'doctrine'),
      new sfCommandOption('go', null, sfCommandOption::PARAMETER_NONE, 'Do the deployment'),      
      // add your own options here
    ));

    $this->namespace        = 'payment';
    $this->name             = 'send-missed-payment-emails';
    $this->briefDescription = 'Send an email to users who have missed a payment 7 days ago and not paid it.';
    $this->detailedDescription = <<<EOF
The [ifiSummaryEmails|INFO] sends an email to users with missed payments 7 days ago and did not resolve them.
Call it with:

  [php symfony payment:send-missed-payment-emails|INFO]
EOF;
  }

    
  protected function execute($arguments = array(), $options = array())
  {
    $loggingStateBefore = sfConfig::set('sf_logging_enabled',false);
    sfConfig::set('sf_logging_enabled', false);
   
    // initialize the database connection
    $databaseManager = new sfDatabaseManager($this->configuration);
    $connection = $databaseManager->getDatabase($options['connection'] ? $options['connection'] : null)->getConnection();
    // So we can play with app.yml settings from the application
    $context = sfContext::createInstance($this->configuration);
    
    sfConfig::set('sf_logging_enabled', $loggingStateBefore);
    
    error_reporting(E_ALL | E_STRICT);
    
    $this->configuration->loadHelpers('Partial');
    
    $this->go = $options['go'];
    
    if (!$this->go) {
      echo "**** TEST MODE: NO EMAILS WILL ACTUALLY BE SENT! ****\n";
    }
    
    echo "Processing transaction errors... ";
    
    if ($options['date']) {
      $missedDate = $options['date'];
    } else {
      echo "(today: ".date('Y-m-d').") ";
      $missedDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
    }

    echo "...from errors on ".$missedDate."\n";
        
    // get customer id, name, email address, hydrates as an array
    $transactionErrors = Doctrine::getTable('AuthNetTransactionError')
      ->createQuery('e')
      ->innerJoin('e.Customer c')
      ->andWhere('e.is_processed = 0')
      ->andWhere('DATE(e.created_at) = ?', $missedDate);
    
    echo "{$transactionErrors->getSqlQuery()}";
    
    $currentSiteId = null;
    
    $notificationCount = 0;
    
    echo "Found ".$transactionErrors->count()." errors.\n";
        
    foreach ($transactionErrors as $error) {
      $customer = $error['Customer'];
      
      echo "Sending missed payment notice for error # ".$error['id']." and customer ".$error['local_user_id']."...";
      
      $emailAddress = $customer['User']['email_address'];
      
      // make sure the person has a valid email address with a simple check for the @ to avoid imported customers
      if (strpos($emailAddress, '@') === false) {
        echo "skipped. No valid email: ".$emailAddress."\n";
        continue;
      } else {
        echo "(".substr($emailAddress, 0, 5)."***) ";
      }
      
      if ($currentSiteId != $customer['site_id']) {
        // set up configuration values, used in emails
        $this->initSite($customer['site_id']);
        $currentSiteId = $customer['site_id'];
      }
      
      $this->sendNotificationEmail($error, $notificationCount);
      
    }    
    echo "*** END ***\n";
    echo $notificationCount." notifications sent".(!$this->go ? ' (TEST MODE)' : '')."\n\n";
  }
  
  /*
    TODO Look into spool strategy
    http://www.symfony-project.org/more-with-symfony/1_4/en/04-Emails#chapter_04_sub_the_spool_strategy
  */
  protected function sendNotificationEmail($error, &$count)
  {    
    
    // Create the mailer and message object
    try {
      
      $emailContext = array(
        'url' => 'https://'.sfConfig::get('app_site_url', 'my.identityfraud.com').'/member-section',
        'error' => $error,
        'customer' => $error['Customer'],
      );
      
      $bcc = sfConfig::get('app_mail_bcc');
      
      $message = $this->getMailer()
        ->compose(sfConfig::get('app_mail_from'), $error['Customer']['User']['email_address'], 'Term Renewal Email from '.sfConfig::get('app_site_name'))
        ->setBcc($bcc)
        ->setBody(get_partial('authorizenet/emailPaymentError', $emailContext), 'text/html')
        ->addPart(get_partial('authorizenet/emailPaymentErrorText', $emailContext));
      
      if ($this->go) {
        if ($result = $this->getMailer()->send($message)) {
          echo "sent.\n";
          $count++;
        } else {
          echo "...not sent (mailer returned false)\n";
        }
      } else {
        $count++;
        echo "not really sent (TEST MODE).\n========\n";
        echo get_partial('authorizenet/emailMissedPaymentText', $emailContext);
        echo "=======\n";
      }
    } catch (Exception $e) {
      echo "There was an exception thrown creating an email message: ".$e->getMessage().".\n";
    }
  }
  
}
