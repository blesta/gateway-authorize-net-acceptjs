<?php
/**
 * Authorize.net Accept.js Credit Card processing gateway. Supports both
 * onsite and offsite payment processing for Credit Cards payments.
 *
 * A list of all Authorize.net API can be found at: https://developer.authorize.net/api/reference/features/acceptjs.html
 *
 * @package blesta
 * @subpackage blesta.components.gateways.authorize_net_acceptjs
 * @copyright Copyright (c) 2021, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AuthorizeNetAcceptjs extends MerchantGateway implements MerchantCc, MerchantCcOffsite, MerchantCcForm
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this module
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this module
        Language::loadLang('authorize_net_acceptjs', null, dirname(__FILE__) . DS . 'language' . DS);

        // Load product configuration required by this module
        Configure::load('authorize_net_acceptjs', dirname(__FILE__) . DS . 'config' . DS);
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('settings', 'default');
        $this->view->setDefaultView('components' . DS . 'gateways' . DS . 'merchant' . DS . 'authorize_net_acceptjs' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);
        Loader::loadModels($this, ['GatewayManager']);

        // Set the APIs available through this gateway
        $this->view->set('apis', [
            'aim' => Language::_('AuthorizeNetAcceptjs.apis_aim', true),
            'cim' => Language::_('AuthorizeNetAcceptjs.apis_cim', true)
        ]);

        // Set the validation modes for CIM
        $this->view->set('validation_modes', [
            'none' => Language::_('AuthorizeNetAcceptjs.validation_modes_none', true),
            'testMode' => Language::_('AuthorizeNetAcceptjs.validation_modes_test', true),
            'liveMode' => Language::_('AuthorizeNetAcceptjs.validation_modes_live', true)
        ]);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Validate the given meta data to ensure it meets the requirements
        $rules = [
            'transaction_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('AuthorizeNetAcceptjs.!error.transaction_key.empty', true)
                ]
            ],
            'login_id' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('AuthorizeNetAcceptjs.!error.login_id.empty', true)
                ],
                'valid' => [
                    'rule' => [[$this, 'validateConnection']],
                    'message' => Language::_('AuthorizeNetAcceptjs.!error.login_id.valid', true)
                ]
            ],
            'sandbox' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', ['true', 'false']],
                    'message' => Language::_('AuthorizeNetAcceptjs.!error.sandbox.valid', true)
                ]
            ]
        ];

        // Set checkbox if not set
        if (!isset($meta['sandbox'])) {
            $meta['sandbox'] = 'false';
        }

        $this->Input->setRules($rules);

        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['login_id', 'transaction_key'];
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Used to determine whether this gateway can be configured for autodebiting accounts
     *
     * @return bool True if the customer must be present (e.g. in the case of credit card
     *  customer must enter security code), false otherwise
     */
    public function requiresCustomerPresent()
    {
        return false;
    }

    /**
     * Informs the system of whether or not this gateway is configured for offsite customer
     * information storage for credit card payments
     *
     * @return bool True if the gateway expects the offset methods to be called for credit card payments,
     *  false to process the normal methods instead
     */
    public function requiresCcStorage()
    {
        return isset($this->meta['api']) && $this->meta['api'] == 'cim';
    }

    /**
     * Returns HTML markup used to render a custom credit card form for a merchant gateway
     *
     * @return string Custom cc form HTML from the merchant
     */
    public function buildCcForm()
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = $this->makeView('cc_form', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Date']);

        // Set available credit card expiration dates
        $expiration = [
            // Get months with full name (e.g. "January")
            'months' => $this->Date->getMonths(1, 12, 'm', 'F'),
            // Sets years from the current year to 10 years in the future
            'years' => $this->Date->getYears(date('Y'), date('Y') + 10, 'Y', 'Y')
        ];

        // Get Client Key
        $this->loadApi('CIM');
        $merchant = $this->AuthorizeNetCim->getMerchantDetailsRequest();
        $client_key = $merchant['publicClientKey'] ?? '';

        $this->view->set('meta', $this->meta);
        $this->view->set('expiration', $expiration);
        $this->view->set('client_key', $client_key);

        return $this->view->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function buildPaymentConfirmation($reference_id, $transaction_id, $amount)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = $this->makeView(
            'payment_confirmation',
            'default',
            str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS)
        );

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $this->meta);

        return $this->view->fetch();
    }

    /**
     * Charge a credit card
     *
     * @param array $card_info An array of credit card info including:
     *  - first_name The first name on the card
     *  - last_name The last name on the card
     *  - card_number The card number
     *  - card_exp The card expiration date
     *  - card_security_code The 3 or 4 digit security code of the card (if available)
     *  - address1 The address 1 line of the card holder
     *  - address2 The address 2 line of the card holder
     *  - city The city of the card holder
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-character country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the card holder
     * @param float $amount The amount to charge this card
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function processCc(array $card_info, $amount, array $invoice_amounts = null)
    {
        return $this->processStoredCc(null, $card_info['reference_id'], $amount, $invoice_amounts);
    }

    /**
     * Authorize a credit card
     *
     * @param array $card_info An array of credit card info including:
     *  - first_name The first name on the card
     *  - last_name The last name on the card
     *  - card_number The card number
     *  - card_exp The card expidation date
     *  - card_security_code The 3 or 4 digit security code of the card (if available)
     *  - address1 The address 1 line of the card holder
     *  - address2 The address 2 line of the card holder
     *  - city The city of the card holder
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the card holder
     * @param float $amount The amount to charge this card
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function authorizeCc(array $card_info, $amount, array $invoice_amounts = null)
    {
        // Load api
        $this->loadApi('ACCEPT');

        // Unserialize reference id
        $data = $this->unserializeReference($card_info['reference_id']);

        // Create the payment object for a payment nonce
        $opaque_data = new net\authorize\api\contract\v1\OpaqueDataType();
        $opaque_data->setDataDescriptor($data['data_descriptor']);
        $opaque_data->setDataValue($data['data_value']);

        // Create customer profile
        $payment_type = new net\authorize\api\contract\v1\PaymentType();
        $payment_type->setOpaqueData($opaque_data);

        $customer_address = new net\authorize\api\contract\v1\CustomerAddressType();
        $customer_address->setFirstName($card_info['first_name'] ?? '');
        $customer_address->setLastName($card_info['last_name'] ?? '');
        $customer_address->setAddress($card_info['address1'] ?? '');
        $customer_address->setCity($card_info['city'] ?? '');
        $customer_address->setState($card_info['state']['code'] ?? '');
        $customer_address->setZip($card_info['zip'] ?? '00000');
        $customer_address->setCountry($card_info['country']['alpha3'] ?? '');

        $payment_profile = new net\authorize\api\contract\v1\CustomerPaymentProfileType();
        $payment_profile->setCustomerType('individual');
        $payment_profile->setBillTo($customer_address);
        $payment_profile->setPayment($payment_type);

        $customer_profile = new net\authorize\api\contract\v1\CustomerProfileType();
        $customer_profile->setDescription($card_info['first_name'] . ' ' . $card_info['last_name']);
        $customer_profile->setMerchantCustomerId("M_" . time());
        $customer_profile->setpaymentProfiles([$payment_profile]);
        $customer_profile->setShipToList([$customer_address]);

        // Assemble the complete transaction request
        $profile_reference = substr(md5($card_info['reference_id'] ?? ''), 0 ,18);
        $request = new net\authorize\api\contract\v1\CreateCustomerProfileRequest();
        $request->setMerchantAuthentication($this->AuthorizeNetAccept);
        $request->setRefId($profile_reference);
        $request->setProfile($customer_profile);

        // Create the controller and get the response
        try {
            $controller = new net\authorize\api\controller\CreateCustomerProfileController($request);
            $response = $controller->executeWithApiResponse(
                $this->meta['sandbox'] == 'true'
                    ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                    : \net\authorize\api\constants\ANetEnvironment::PRODUCTION
            );
        } catch (Throwable $e) {
            $this->Input->setErrors(['authnet_error' => ['authorize' => $e->getMessage()]]);

            return;
        }

        $reference_id = [
            $profile_reference,
            $response->getCustomerProfileId(),
            $response->getCustomerPaymentProfileIdList()[0] ?? ''
        ];

        return [
            'status' => $response->getMessages()->getResultCode() == 'Ok' ? 'pending' : 'declined',
            'reference_id' => base64_encode(implode('|', $reference_id)),
            'transaction_id' => null,
            'message' => $response->getMessages()->getMessage()[0]->getText() ?? null
        ];
    }

    /**
     * Capture the funds of a previously authorized credit card
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction
     * @param float $amount The amount to capture on this card
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function captureCc($reference_id, $transaction_id, $amount, array $invoice_amounts = null)
    {
        // Load api
        $this->loadApi('ACCEPT');

        // Unserialize data
        $data = explode('|', base64_decode($reference_id));

        // Charge payment profile
        $payment_profile = new net\authorize\api\contract\v1\PaymentProfileType();
        $payment_profile->setPaymentProfileId($data[2] ?? '');

        $profile = new net\authorize\api\contract\v1\CustomerProfilePaymentType();
        $profile->setCustomerProfileId($data[1] ?? '');
        $profile->setPaymentProfile($payment_profile);

        $transaction = new net\authorize\api\contract\v1\TransactionRequestType();
        $transaction->setTransactionType('authCaptureTransaction');
        $transaction->setAmount($amount);
        $transaction->setProfile($profile);

        // Send request
        $request = new net\authorize\api\contract\v1\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->AuthorizeNetAccept);
        $request->setRefId($data[0] ?? '');
        $request->setTransactionRequest($transaction);

        try {
            $controller = new net\authorize\api\controller\CreateTransactionController($request);
            $response = $controller->executeWithApiResponse(
                $this->meta['sandbox'] == 'true'
                    ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                    : \net\authorize\api\constants\ANetEnvironment::PRODUCTION
            );
        } catch (Throwable $e) {
            $this->Input->setErrors(['authnet_error' => ['capture' => $e->getMessage()]]);

            return;
        }

        return [
            'status' => $response->getMessages()->getResultCode() == 'Ok' ? 'approved' : 'declined',
            'reference_id' => $data[0] ?? '',
            'transaction_id' => $response->getTransactionResponse()->getTransId() ?? '',
            'message' => $response->getMessages()->getMessage()[0]->getText() ?? null
        ];
    }

    /**
     * Void a credit card charge
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function voidCc($reference_id, $transaction_id)
    {
        // Load api
        /*$this->loadApi('ACCEPT');

        // Create a transaction
        $transaction = new net\authorize\api\contract\v1\TransactionRequestType();
        $transaction->setTransactionType('voidTransaction');
        $transaction->setRefTransId($transaction_id);

        // Void transaction
        $request = new net\authorize\api\contract\v1\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->AuthorizeNetAccept);
        $request->setRefId($reference_id);
        $request->setTransactionRequest($transaction);

        try {
            $controller = new net\authorize\api\controller\CreateTransactionController($request);
            $response = $controller->executeWithApiResponse(
                $this->meta['sandbox'] == 'true'
                    ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                    : \net\authorize\api\constants\ANetEnvironment::PRODUCTION
            );
        } catch (Throwable $e) {
            $this->Input->setErrors(['authnet_error' => ['void' => $e->getMessage()]]);

            return;
        }

        return [
            'status' => $response->getMessages()->getResultCode() == 'Ok' ? 'void' : 'error',
            'reference_id' => $reference_id,
            'transaction_id' => $transaction_id,
            'message' => $response->getMessages()->getMessage()[0]->getText() ?? null
        ];*/

        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Refund a credit card charge
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction
     * @param float $amount The amount to refund this card
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function refundCc($reference_id, $transaction_id, $amount)
    {
        // Load api
        $this->loadApi('ACCEPT');

        // Get transaction
        $request = new net\authorize\api\contract\v1\GetTransactionDetailsRequest();
        $request->setMerchantAuthentication($this->AuthorizeNetAccept);
        $request->setTransId($transaction_id);
        $controller = new net\authorize\api\controller\GetTransactionDetailsController($request);

        $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
        $masked_credit_card = $response->getTransaction()->getPayment()->getCreditCard();

        // Create a void transaction
        $credit_card = new net\authorize\api\contract\v1\CreditCardType();
        $credit_card->setCardNumber($masked_credit_card->getCardNumber());
        $credit_card->setExpirationDate($masked_credit_card->getExpirationDate());

        $payment = new net\authorize\api\contract\v1\PaymentType();
        $payment->setCreditCard($credit_card);

        $transaction = new net\authorize\api\contract\v1\TransactionRequestType();
        $transaction->setTransactionType('refundTransaction');
        $transaction->setAmount($amount);
        $transaction->setPayment($payment);
        $transaction->setRefTransId($transaction_id);

        // Refund transaction
        $request = new net\authorize\api\contract\v1\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->AuthorizeNetAccept);
        $request->setRefId($reference_id);
        $request->setTransactionRequest($transaction);

        try {
            $controller = new net\authorize\api\controller\CreateTransactionController($request);
            $response = $controller->executeWithApiResponse(
                $this->meta['sandbox'] == 'true'
                    ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                    : \net\authorize\api\constants\ANetEnvironment::PRODUCTION
            );
        } catch (Throwable $e) {
            $this->Input->setErrors(['authnet_error' => ['void' => $e->getMessage()]]);

            return;
        }

        return [
            'status' => $response->getMessages()->getResultCode() == 'Ok' ? 'refunded' : 'error',
            'reference_id' => $reference_id,
            'transaction_id' => $transaction_id,
            'message' => $response->getMessages()->getMessage()[0]->getText() ?? null
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function storeCc(array $card_info, array $contact, $client_reference_id = null)
    {
        // Load api
        $this->loadApi('ACCEPT');

        /*// Create the payment object for a payment nonce
        $opaque_data = new net\authorize\api\contract\v1\OpaqueDataType();
        $opaque_data->setDataDescriptor($data['data_descriptor']);
        $opaque_data->setDataValue($data['data_value']);

        // Create customer profile
        $payment_type = new net\authorize\api\contract\v1\PaymentType();
        $payment_type->setOpaqueData($opaque_data);

        $customer_address = new net\authorize\api\contract\v1\CustomerAddressType();
        $customer_address->setFirstName($card_info['first_name'] ?? '');
        $customer_address->setLastName($card_info['last_name'] ?? '');
        $customer_address->setAddress($card_info['address1'] ?? '');
        $customer_address->setCity($card_info['city'] ?? '');
        $customer_address->setState($card_info['state']['code'] ?? '');
        $customer_address->setZip($card_info['zip'] ?? '00000');
        $customer_address->setCountry($card_info['country']['alpha3'] ?? '');

        // Set the customer's identifying information
        $profile_reference = substr(md5($card_info['reference_id'] ?? ''), 0 ,18);
        $customer_data = new net\authorize\api\contract\v1\CustomerDataType();
        $customer_data->setType('individual');
        $customer_data->setId($profile_reference);

        // Create order information
        $order = new net\authorize\api\contract\v1\OrderType();
        $order->setInvoiceNumber(count($invoice_amounts) == 1 ? $invoice_amounts[0] : time());
        $order->setDescription($this->getChargeDescription($invoice_amounts));

        // Create a TransactionRequestType object and add the previous objects to it
        $transaction_request = new net\authorize\api\contract\v1\TransactionRequestType();
        $transaction_request->setTransactionType("authCaptureTransaction");
        $transaction_request->setAmount($amount);
        $transaction_request->setOrder($order);
        $transaction_request->setPayment($payment_type);
        $transaction_request->setBillTo($customer_address);
        $transaction_request->setCustomer($customer_data);

        // Assemble the complete transaction request
        $request = new net\authorize\api\contract\v1\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->AuthorizeNetAccept);
        $request->setRefId($profile_reference);
        $request->setTransactionRequest($transaction_request);

        // Create the controller and get the response
        try {
            $controller = new net\authorize\api\controller\CreateTransactionController($request);
            $response = $controller->executeWithApiResponse(
                $this->meta['sandbox'] == 'true'
                    ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                    : \net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        } catch (Throwable $e) {
            $this->Input->setErrors(['authnet_error' => ['authorize' => $e->getMessage()]]);

            return;
        }

        return [
            'status' => $response->getMessages()->getResultCode() == 'Ok' ? 'approved' : 'declined',
            'reference_id' => str_replace('X', '', $response->getTransactionResponse()->getAccountNumber()),
            'transaction_id' => $response->getTransactionResponse()->getTransId(),
            'message' => $response->getMessages()->getMessage() ?? null
        ];*/

        //$this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * {@inheritdoc}
     */
    public function updateCc(array $card_info, array $contact, $client_reference_id, $account_reference_id)
    {
        return $this->storeCc($card_info, $contact, $client_reference_id);
    }

    /**
     * {@inheritdoc}
     */
    public function removeCc($client_reference_id, $account_reference_id)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * {@inheritdoc}
     */
    public function processStoredCc($client_reference_id, $account_reference_id, $amount, array $invoice_amounts = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * {@inheritdoc}
     */
    public function authorizeStoredCc(
        $client_reference_id,
        $account_reference_id,
        $amount,
        array $invoice_amounts = null
    ) {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * {@inheritdoc}
     */
    public function captureStoredCc(
        $client_reference_id,
        $account_reference_id,
        $transaction_reference_id,
        $transaction_id,
        $amount,
        array $invoice_amounts = null
    ) {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * {@inheritdoc}
     */
    public function voidStoredCc(
        $client_reference_id,
        $account_reference_id,
        $transaction_reference_id,
        $transaction_id
    ) {
        return $this->voidCc($transaction_reference_id, $transaction_id);
    }

    /**
     * {@inheritdoc}
     */
    public function refundStoredCc(
        $client_reference_id,
        $account_reference_id,
        $transaction_reference_id,
        $transaction_id,
        $amount
    ) {
        return $this->refundCc($transaction_reference_id, $transaction_id);
    }

    /**
     * Loads the given API if not already loaded
     *
     * @param string $type The type of API to load (AIM or CIM)
     */
    private function loadApi($type)
    {
        $type = strtolower($type);
        switch ($type) {
            case 'aim':
                if (!isset($this->AuthorizeNetAim)) {
                    Loader::load(dirname(__FILE__) . DS . 'apis' . DS . 'authorize_net_aim.php');
                    $this->AuthorizeNetAim = new AuthorizeNetAim(
                        $this->meta['login_id'],
                        $this->meta['transaction_key'],
                        $this->meta['sandbox'] == 'true',
                        $this->meta['sandbox'] == 'true'
                    );
                }

                $this->AuthorizeNetAim->setCurrency($this->currency);
                break;
            case 'cim':
                if (!isset($this->AuthorizeNetCim)) {
                    Loader::load(dirname(__FILE__) . DS . 'apis' . DS . 'authorize_net_cim.php');
                    $this->AuthorizeNetCim = new AuthorizeNetCim(
                        $this->meta['login_id'],
                        $this->meta['transaction_key'],
                        $this->meta['sandbox'] == 'true',
                        $this->meta['validation_mode']
                    );
                }

                $this->AuthorizeNetCim->setCurrency($this->currency);
                break;
            case 'accept':
                if (!isset($this->AuthorizeNetAccept)) {
                    $this->AuthorizeNetAccept = new net\authorize\api\contract\v1\MerchantAuthenticationType();
                    $this->AuthorizeNetAccept->setName($this->meta['login_id']);
                    $this->AuthorizeNetAccept->setTransactionKey($this->meta['transaction_key']);
                }

                break;
        }
    }

    /**
     * Log the request
     *
     * @param string $url The URL of the API request to log
     * @param array The input parameters sent to the gateway
     * @param array The response from the gateway
     */
    private function logRequest($url, array $params, array $response)
    {
        // Define all fields to mask when logging
        $mask_fields = [
            'number', // CC number
            'exp_month',
            'exp_year',
            'cvc'
        ];

        // Determine success or failure for the response
        $success = false;
        if (!(($errors = $this->Input->errors()) || isset($response['error']))) {
            $success = true;
        }

        // Log data sent to the gateway
        $this->log(
            $url,
            serialize($params),
            'input',
            (isset($params['error']) ? false : true)
        );

        // Log response from the gateway
        $this->log($url, serialize($this->maskDataRecursive($response, $mask_fields)), 'output', $success);
    }

    /**
     * Unserialize the reference id
     *
     * @param string $reference_id The reference id returned by Accept.js
     * @return array A list containing the data value and data descriptor
     */
    private function unserializeReference($reference_id)
    {
        $parts = explode("|", $reference_id, 2);

        return [
            'data_value' => $parts[0] ?? '',
            'data_descriptor' => $parts[1] ?? ''
        ];
    }

    /**
     * Retrieves the description for CC charges
     *
     * @param array|null $invoice_amounts An array of invoice amounts (optional)
     * @return string The charge description
     */
    private function getChargeDescription(array $invoice_amounts = null)
    {
        // No invoice amounts, set a default description
        if (empty($invoice_amounts)) {
            return Language::_('AuthorizeNetAcceptjs.charge_description_default', true);
        }

        Loader::loadModels($this, ['Invoices']);
        Loader::loadComponents($this, ['DataStructure']);
        $string = $this->DataStructure->create('string');

        // Create a list of invoices being paid
        $id_codes = [];
        foreach ($invoice_amounts as $invoice_amount) {
            if (($invoice = $this->Invoices->get($invoice_amount['invoice_id']))) {
                $id_codes[] = $invoice->id_code;
            }
        }

        // Use the default description if there are no valid invoices
        if (empty($id_codes)) {
            return Language::_('AuthorizeNetAcceptjs.charge_description_default', true);
        }

        // Truncate the description to a max of 1000 characters since that is Stripe's limit for the description field
        $description = Language::_('AuthorizeNetAcceptjs.charge_description', true, implode(', ', $id_codes));
        if (strlen($description) > 1000) {
            $description = $string->truncate($description, ['length' => 997]) . '...';
        }

        return $description;
    }
}
