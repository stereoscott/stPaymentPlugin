<?php

/**
 * Base actions for the stPaymentPlugin authNetSubscriptionAdmin module.
 * 
 * @package     stPaymentPlugin
 * @subpackage  authNetSubscriptionAdmin
 * @author      Scott Meves <scott@stereointeractive.com>
 * @version     SVN: $Id$
 */
abstract class BaseAuthNetSubscriptionAdminActions extends autoauthNetSubscriptionAdminActions
{
  public function executeSync(sfWebRequest $request)
  {
    $authNetSubscription = $this->getRoute()->getObject();
    $result = $authNetSubscription->updateStatusUsingAuthNet($error);
    if ($result) {
      $this->getUser()->setFlash('notice', 'The selected subscription has been synchronized with Authorize.net\'s servers.');
    } else {
      $this->getUser()->setFlash('error', 'The selected subscription could not be synchronized. '.$error);
    }

    $this->redirect('authNetSubscriptionAdmin');
  }
  
  
  protected function processForm(sfWebRequest $request, sfForm $form)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers('I18N');
    $form->bind($request->getParameter($form->getName()), $request->getFiles($form->getName()));
    if ($form->isValid())
    {
      $form->updateObject(); // we pulled this out of the save() method and put it here before doApiUpdate
      
      $error = false;
      if (!$form->getObject()->isNew()) {
        $result = $form->doApiUpdate($error);
        if ($result === -1) {
          $this->getUser()->setFlash('error', 'The subscription has not updated due to an API error. '.$error);
          return $this->redirect('@authNetSubscriptionAdmin_edit?id='.$form->getObject()->getId());
        } elseif ($result === 0) {
          $this->getUser()->setFlash('notice', 'No changes were made');
          return $this->redirect('@authNetSubscriptionAdmin_edit?id='.$form->getObject()->getId());
        }
      }

      $authNetSubscription = $form->save();

      $this->dispatcher->notify(new sfEvent($this, 'admin.save_object', array('object' => $authNetSubscription)));

      if ($request->hasParameter('_save_and_add'))
      {
        $this->getUser()->setFlash('notice', $this->getUser()->getFlash('notice').' ' . $this->__('You can add another one below.', null, 'apostrophe'));

        $this->redirect('@authNetSubscriptionAdmin_new');
      }
      elseif ($request->hasParameter('_save'))
      {
        $this->redirect('@authNetSubscriptionAdmin_edit?id='.$authNetSubscription->getId());
      }
      // The default is _save_and_list
      else
      {
        $this->getUser()->setFlash('notice', $this->getUser()->getFlash('notice'));

        $this->redirect('@authNetSubscriptionAdmin');
      }
    }
    else
    {
      $this->getUser()->setFlash('error', $this->__('The item has not been saved due to some errors.', null, 'apostrophe'));
    }
  }
  
}