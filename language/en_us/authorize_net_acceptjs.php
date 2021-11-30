<?php
// Errors
$lang['AuthorizeNetAcceptjs.!error.auth'] = 'The gateway could not authenticate.';
$lang['AuthorizeNetAcceptjs.!error.transaction_key.empty'] = 'Please enter a Transaction Key.';
$lang['AuthorizeNetAcceptjs.!error.login_id.empty'] = 'Please enter a Login ID.';
$lang['AuthorizeNetAcceptjs.!error.login_id.valid'] = 'Unable to connect to the Authorize.net API using the given Login ID.';
$lang['AuthorizeNetAcceptjs.!error.sandbox.valid'] = 'Sandbox must be set to "true" if given.';

$lang['AuthorizeNetAcceptjs.name'] = 'Authorize.Net Accept.js';
$lang['AuthorizeNetAcceptjs.description'] = 'Send secure payment data directly to Authorize.net. Accept.js captures the payment data and submits it directly to Authorize.net.';

// Form
$lang['AuthorizeNetAcceptjs.field_number'] = 'Number';
$lang['AuthorizeNetAcceptjs.field_security'] = 'Security Code';
$lang['AuthorizeNetAcceptjs.field_expiration'] = 'Expiration Date';

// Settings
$lang['AuthorizeNetAcceptjs.login_id'] = 'Login ID';
$lang['AuthorizeNetAcceptjs.transaction_key'] = 'Transaction Key';
$lang['AuthorizeNetAcceptjs.validation_mode'] = 'Payment Account Validation Mode';
$lang['AuthorizeNetAcceptjs.validation_note'] = "This controls what type of validation is performed when a payment account is stored using CIM. 'None' performs no additional validation. 'Test' issues a test transaction that does not appear on the customer's statement but will generate an email to the merchant. 'Live' processes a $0.00 or $0.01 transaction that is immediately voided. Consult your Merchant Account Provider before setting this value to 'Live' as you may be subject to fees.";
$lang['AuthorizeNetAcceptjs.validation_modes_none'] = 'None';
$lang['AuthorizeNetAcceptjs.validation_modes_test'] = 'Test';
$lang['AuthorizeNetAcceptjs.validation_modes_live'] = 'Live';
$lang['AuthorizeNetAcceptjs.sandbox'] = 'Sandbox';
$lang['AuthorizeNetAcceptjs.sandbox_note'] = 'Enabling this option will post transactions to the Authorize.Net Sandbox environment. Only enable this option if you are testing with an Authorize.Net Sandbox account.';

// Charge description
$lang['AuthorizeNetAcceptjs.charge_description_default'] = 'Charge for specified amount';
$lang['AuthorizeNetAcceptjs.charge_description'] = 'Charge for %1$s'; // Where %1$s is a comma seperated list of invoice ID display codes
