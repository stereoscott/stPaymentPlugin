This module integrates with the Authorize.net API to process single and recurring transactions.

*sample scenario 1*
7 day free trial, $10/mo thereafter:
- authorize $10, if failure return error
- if successful, run immediate void, set up recurring billing for $10/mo starting on 8th day

*sample scenario 2*
14 day trial offer for $1.99, $10/mo thereafter
- authorize *and capture* $1.99. if failure, return error
- if successful, set up recurring billing for $10/month starting on 15th day

*sample scenario 3*
14 day free trial offer, $69.95/year thereafter  
- authorize (do not capture) $69.95, return error on failure
- if successful, set up recurring billing for $69.95 yearly starting on 15th day.

Note for all scenarios we must ensure the credit card does not expire before trial ends / recurring begins.

General logic:

0) make sure credit card expiration is beyond trial period

1a) if free trial offer, authorize amount of first monthly payment but void it immediately

1b) if non-free trial offer, authorize and capture for trial amount, no voids necessary

2) if 1(a or b) succeeds, set up recurring billing on n+1th day for recurring amount

Questions: 

Can we run an auth only on a $0.00 amount? Are we charged an Account Verification Fee?

Can we run a $1.00 auth only and then an immediate void to verify the card?

Credit Card Expiration Issues:
 - when a customer enrolls, we could store a 'notify at' date in our database that marks when a customer has expiration date occuring soon. 
 
We may also want to consider setting up a silent post URL to handle the results of processed transactions. We can determine that a transaction was ARB by the presence of x_subscription_id and x_subscription_paynum. Sample code:

<?php
// Flag if this is an ARB transaction. Set to false by default.
$arb    = false;

// Store the posted values in an associative array
$fields = array();

foreach ($_REQUEST as $name => $value)
{
    // Create our associative array
    $fields[$name] = $value;

 
    // If we see a special field flag this as an ARB transaction
    if ($name == 'x_subscription_id')
    {
        $arb = true;
    }
}

// If it is an ARB transaction, do something with it
if ($arb == true && $fields['x_response_code'] != 1)
{
    // Suspend the user's account
    ...

    // Email the user and ask them to update their credit card information
    ...

    // Email you so you are aware of the failure
    ...
}
?>

*Credit card expires before first scheduled payment*

Currently we charge annual customers as single transactions and do not set up a recurring billing profile.

We will have to schedule email notifications to those customers asking them to login and update their billing information (essentially prompting them to set up a new recurring billing profile that starts on their 1 year anniversary of their account). 

We can build this into their dashboard (a little notification) and nightly script (to send an email), and create a new form under "manage my account" that would prompt them for new credit card information and allow us to set up a new recurring billing profile for them.

Here is an alternative thought, suggested by some reference materials online. 

For annual customers, we can charge the first transaction as we do now, and then we set up a recurring billing profile with their credit card expiration date set to two years in the future (even if it expires before then). This will allow us to establish the ARB subscription. Auth.net doesn't the verify the card information in an ARB profile until they actually try and run the transaction. 

This way we get a recurring billing profile set up in the system so that we can reference it later. 

Let's say we don't update their ARB subscription info in time, and 1 year from now the transaction fails. We are notified about it as we would with all failed transactions that might occur on a day to day basis. We can actually set up a service on our site that gets notified when they process recurring billing transactions, and if we are notified of a failed transaction, we can put their account on hold, send memberservices an email, or email the customer prompting them to login and update their billing information within their account.