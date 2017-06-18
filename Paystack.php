<?php
/* in CodeIgniter, copy this file to: ./{APPLICATION}/libraries/Paystack.php */

// Codeigniter access check, uncomment if you intend to use in Code Igniter
// if(!defined('BASEPATH') ) { exit('No direct script access allowed');
// }

/**
 *
 */
class Paystack
{

    public $secret_key;
    public $use_guzzle = false;
    public static $fallback_to_file_get_contents = false;
    const VERSION="2.1.19";


    /**
 * Creates a new Paystack object
  *
 * @param string $secret_key - Secret key for your account with Paystack
 */
    public function __construct($params_or_key)
    {
        if (is_array($params_or_key)) {
            $params =$params_or_key;
            $test_mode = array_key_exists('paystack_test_mode', $params) ? $params['paystack_test_mode'] : true;
            if ($test_mode) {
                $secret_key = array_key_exists('paystack_key_test_secret', $params) ? trim($params['paystack_key_test_secret']) : '';
            } else {
                $secret_key = array_key_exists('paystack_key_live_secret', $params) ? trim($params['paystack_key_live_secret']) : '';
            }
            if (!is_string($secret_key) || !(substr($secret_key, 0, 8)==='sk_'.($test_mode ? 'test_':'live_'))) {
            // Should never get here
                throw new \InvalidArgumentException('A Valid Paystack Secret Key must start with \'sk_\'.');

            }
        } else {
            $secret_key=trim(strval($params_or_key));
            if (!is_string($secret_key) || !(substr($secret_key, 0, 3)==='sk_')) {
            // Should never get here
                throw new \InvalidArgumentException('A Valid Paystack Secret Key must start with \'sk_\'.');

            }
        }


         $this->secret_key = $secret_key;
    }

    public function useGuzzle()
    {
        $this->use_guzzle = true;
    }

    public static function disableFileGetContentsFallback()
    {
        Paystack::$fallback_to_file_get_contents = false;
    }

    public static function enableFileGetContentsFallback()
    {
        Paystack::$fallback_to_file_get_contents = true;
    }

    public function __call($method, $args)
    {
        if ($singular_form = PaystackHelpersRouter::singularFor($method)) {
            return $this->handlePlural($singular_form, $method, $args);
        }
        return $this->handleSingular($method, $args);
    }

    private function handlePlural($singular_form, $method, $args)
    {
        if ((count($args) === 1 && is_array($args[0]))||(count($args) === 0)) {
            return $this->{$singular_form}->__call('getList', $args);
        }
        throw new \InvalidArgumentException(
            'Route "' . $method . '" can only accept an optional array of filters and '
            .'paging arguments (perPage, page).'
        );
    }

    private function handleSingular($method, $args)
    {
        if (count($args) === 1) {
            $args = [[], [ PaystackHelpersRouter::ID_KEY => $args[0] ] ];
            return $this->{$method}->__call('fetch', $args);
        }
        throw new \InvalidArgumentException(
            'Route "' . $method . '" can only accept an id or code.'
        );
    }

    public function __get($name)
    {
        return new PaystackHelpersRouter($name, $this);
    }
}

interface PaystackContractsRouteInterface
{

    const METHOD_KEY = 'method';
    const ENDPOINT_KEY = 'endpoint';
    const PARAMS_KEY = 'params';
    const ARGS_KEY = 'args';
    const REQUIRED_KEY = 'required';
    const POST_METHOD = 'post';
    const PUT_METHOD = 'put';
    const GET_METHOD = 'get';

    public static function root();
}

class PaystackEvent
{
    public $raw = '';
    protected $signature = '';
    public $obj;
    const SIGNATURE_KEY = 'HTTP_X_PAYSTACK_SIGNATURE';

    protected function __construct()
    {
    }

    public static function capture()
    {
        $evt = new Event();
        $evt->raw = @file_get_contents('php://input');
        $evt->signature = ( isset($_SERVER[self::SIGNATURE_KEY]) ? $_SERVER[self::SIGNATURE_KEY] : '' );
        $evt->loadObject();
        return $evt;
    }

    protected function loadObject()
    {
        $this->obj = json_decode($this->raw);
    }

    public function discoverOwner(array $keys)
    {
        if (!$this->obj || !property_exists($this->obj, 'data')) {
            return;
        }
        foreach ($keys as $index => $key) {
            if ($this->validFor($key)) {
                return $index;
            }
        }
    }

    public function validFor($key)
    {
        if ($this->signature === hash_hmac('sha512', $this->raw, $key)) {
            return true;
        }
        return false;
    }

    public function package(array $additional_headers = [], $method = 'POST')
    {
        $pack = new PaystackHttpRequest();
        $pack->method = $method;
        $pack->headers = $additional_headers;
        $pack->headers["X-Paystack-Signature"] = $this->signature;
        $pack->headers["Content-Type"] = "application/json";
        $pack->body = $this->raw;
        return $pack;
    }

    public function forwardTo($url, array $additional_headers = [], $method = 'POST')
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $packed = $this->package($additional_headers, $method);
        $packed->endpoint = $url;
        return $packed->send()->wrapUp();
    }
}

class PaystackExceptionApiException extends PaystackExceptionPaystackException
{
    private $PaystackHttpResponseObject;

    public function __construct($message, $object)
    {
        parent::__construct($message);
        $this->PaystackHttpResponseObject = $object;
    }

    public function getPaystackHttpResponseObject()
    {
        return $this->PaystackHttpResponseObject;
    }
}

class PaystackExceptionBadMetaNameException extends PaystackExceptionPaystackException
{
    public $errors;
    public function __construct($message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
}

class PaystackExceptionPaystackException extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}

class PaystackExceptionValidationException extends PaystackExceptionPaystackException
{
    public $errors;
    public function __construct($message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
}


class PaystackFee
{
    const DEFAULT_PERCENTAGE = 0.015;
    const DEFAULT_ADDITIONAL_CHARGE = 10000;
    const DEFAULT_THRESHOLD = 250000;
    const DEFAULT_CAP = 200000;

    public static $default_percentage = Fee::DEFAULT_PERCENTAGE;
    public static $default_additional_charge = Fee::DEFAULT_ADDITIONAL_CHARGE;
    public static $default_threshold = Fee::DEFAULT_THRESHOLD;
    public static $default_cap = Fee::DEFAULT_CAP;

    private $percentage;
    private $additional_charge;
    private $threshold;
    private $cap;

    private $chargeDivider;
    private $crossover;
    private $flatlinePlusCharge;
    private $flatline;

    public function __construct()
    {
        $this->percentage = Fee::$default_percentage;
        $this->additional_charge = Fee::$default_additional_charge;
        $this->threshold = Fee::$default_threshold;
        $this->cap = Fee::$default_cap;
        $this->__setup();
    }

    public function withPercentage($percentage)
    {
        $this->percentage = $percentage;
        $this->__setup();
    }

    public static function resetDefaults()
    {
        Fee::$default_percentage = Fee::DEFAULT_PERCENTAGE;
        Fee::$default_additional_charge = Fee::DEFAULT_ADDITIONAL_CHARGE;
        Fee::$default_threshold = Fee::DEFAULT_THRESHOLD;
        Fee::$default_cap = Fee::DEFAULT_CAP;
    }

    public function withAdditionalCharge($additional_charge)
    {
        $this->additional_charge = $additional_charge;
        $this->__setup();
    }

    public function withThreshold($threshold)
    {
        $this->threshold = $threshold;
        $this->__setup();
    }

    public function withCap($cap)
    {
        $this->cap = $cap;
        $this->__setup();
    }

    private function __setup()
    {
        $this->chargeDivider = $this->__chargeDivider();
        $this->crossover = $this->__crossover();
        $this->flatlinePlusCharge = $this->__flatlinePlusCharge();
        $this->flatline = $this->__flatline();
    }

    private function __chargeDivider()
    {
        return 1 - $this->percentage;
    }

    private function __crossover()
    {
        return ($this->threshold * $this->chargeDivider) - $this->additional_charge;
    }

    private function __flatlinePlusCharge()
    {
        return ($this->cap - $this->additional_charge) / $this->percentage;
    }

    private function __flatline()
    {
        return $this->flatlinePlusCharge - $this->cap;
    }

    public function addFor($amountinkobo)
    {
        if ($amountinkobo > $this->flatline) {
            return intval(ceil($amountinkobo + $this->cap));
        } elseif ($amountinkobo > $this->crossover) {
            return intval(ceil(($amountinkobo + $this->additional_charge) / $this->chargeDivider));
        } else {
            return intval(ceil($amountinkobo / $this->chargeDivider));
        }
    }

    public function calculateFor($amountinkobo)
    {
        $fee = $this->percentage * $amountinkobo;
        if ($amountinkobo >= $this->threshold) {
            $fee += $this->additional_charge;
        }
        if ($fee > $this->cap) {
            $fee = $this->cap;
        }
        return intval(ceil($fee));
    }
}

class PaystackHelpersCaller
{
    private $paystackObj;

    public function __construct($paystackObj)
    {
        $this->paystackObj = $paystackObj;
    }

    public function callEndpoint($interface, $payload = [ ], $sentargs = [ ])
    {
        $builder = new PaystackHttpRequestBuilder($this->paystackObj, $interface, $payload, $sentargs);
        return $builder->build()->send()->wrapUp();
    }
}

class PaystackHelpersRouter
{
    private $route;
    private $route_class;
    private $methods;
    public static $ROUTES = [
        'customer', 'page', 'plan', 'subscription', 'transaction', 'subaccount',
        'balance', 'bank', 'decision', 'integration', 'settlement',
        'transfer', 'transferrecipient'
    ];
    public static $ROUTE_SINGULAR_LOOKUP = [
        'customers'=>'customer',
        'pages'=>'page',
        'plans'=>'plan',
        'subscriptions'=>'subscription',
        'transactions'=>'transaction',
        'banks'=>'bank',
        'settlements'=>'settlement',
        'transfers'=>'transfer',
        'transferrecipients'=>'transferrecipient',
    ];

    const ID_KEY = 'id';
    const PAYSTACK_API_ROOT = 'https://api.paystack.co';

    public function __call($methd, $sentargs)
    {
        $method = ($methd === 'list' ? 'getList' : $methd );
        if (array_key_exists($method, $this->methods) && is_callable($this->methods[$method])) {
            return call_user_func_array($this->methods[$method], $sentargs);
        } else {
            throw new \Exception('Function "' . $method . '" does not exist for "' . $this->route . '".');
        }
    }

    public static function singularFor($method)
    {
        return (
            array_key_exists($method, PaystackHelpersRouter::$ROUTE_SINGULAR_LOOKUP) ?
                PaystackHelpersRouter::$ROUTE_SINGULAR_LOOKUP[$method] :
                null
            );
    }

    public function __construct($route, $paystackObj)
    {
        if (!in_array($route, PaystackHelpersRouter::$ROUTES)) {
            throw new ValidationException(
                "Route '{$route}' does not exist."
            );
        }

        $this->route = strtolower($route);
        $this->route_class = 'PaystackRoutes' . ucwords($route);

        $mets = get_class_methods($this->route_class);
        if (empty($mets)) {
            throw new \InvalidArgumentException('Class "' . $this->route . '" does not exist.');
        }
        // add methods to this object per method, except root
        foreach ($mets as $mtd) {
            if ($mtd === 'root') {
                continue;
            }
            $mtdFunc = function (
                array $params = [ ],
                array $sentargs = [ ]
            ) use (
                $mtd,
                $paystackObj
            ) {
                $interface = call_user_func($this->route_class . '::' . $mtd);
                // TODO: validate params and sentargs against definitions
                $PaystackHelpersCaller = new PaystackHelpersCaller($paystackObj);
                return $PaystackHelpersCaller->callEndpoint($interface, $params, $sentargs);
            };
            $this->methods[$mtd] = \Closure::bind($mtdFunc, $this, get_class());
        }
    }
}


class PaystackHttpRequest
{
    public $method;
    public $endpoint;
    public $body = '';
    public $headers = [];
    protected $PaystackHttpResponse;
    protected $paystackObj;

    public function __construct($paystackObj = null)
    {
        $this->PaystackHttpResponse = new PaystackHttpResponse();
        $this->paystackObj = $paystackObj;
        $this->PaystackHttpResponse->forApi = !is_null($paystackObj);
        if ($this->PaystackHttpResponse->forApi) {
            $this->headers['Content-Type'] = 'application/json';
        }
    }

    public function getPaystackHttpResponse()
    {
        return $this->PaystackHttpResponse;
    }

    public function flattenedHeaders()
    {
        $_ = [];
        foreach ($this->headers as $key => $value) {
            $_[] = $key . ": " . $value;
        }
        return $_;
    }

    public function send()
    {
        $this->attemptGuzzle();
        if (!$this->PaystackHttpResponse->okay) {
            $this->attemptCurl();
        }
        if (!$this->PaystackHttpResponse->okay) {
            $this->attemptFileGetContents();
        }
        return $this->PaystackHttpResponse;
    }

    public function attemptGuzzle()
    {
        if (isset($this->paystackObj) && !$this->paystackObj->use_guzzle) {
            $this->PaystackHttpResponse->okay = false;
            return;
        }
        if (class_exists('\\GuzzleHttp\\Exception\\BadPaystackHttpResponseException')
            && class_exists('\\GuzzleHttp\\Exception\\ClientException')
            && class_exists('\\GuzzleHttp\\Exception\\ConnectException')
            && class_exists('\\GuzzleHttp\\Exception\\PaystackHttpRequestException')
            && class_exists('\\GuzzleHttp\\Exception\\ServerException')
            && class_exists('\\GuzzleHttp\\Client')
            && class_exists('\\GuzzleHttp\\Psr7\\PaystackHttpRequest')
        ) {
            $PaystackHttpRequest = new \GuzzleHttp\Psr7\PaystackHttpRequest(
                strtoupper($this->method),
                $this->endpoint,
                $this->headers,
                $this->body
            );
            $client = new \GuzzleHttp\Client();
            try {
                $psr7PaystackHttpResponse = $client->send($PaystackHttpRequest);
                $this->PaystackHttpResponse->body = $psr7PaystackHttpResponse->getBody()->getContents();
                $this->PaystackHttpResponse->okay = true;
            } catch (\Exception $e) {
                if (($e instanceof \GuzzleHttp\Exception\BadPaystackHttpResponseException
                    || $e instanceof \GuzzleHttp\Exception\ClientException
                    || $e instanceof \GuzzleHttp\Exception\ConnectException
                    || $e instanceof \GuzzleHttp\Exception\PaystackHttpRequestException
                    || $e instanceof \GuzzleHttp\Exception\ServerException)
                ) {
                    if ($e->hasPaystackHttpResponse()) {
                        $this->PaystackHttpResponse->body = $e->getPaystackHttpResponse()->getBody()->getContents();
                    }
                    $this->PaystackHttpResponse->okay = true;
                }
                $this->PaystackHttpResponse->messages[] = $e->getMessage();
            }
        }
    }

    public function attemptFileGetContents()
    {
        if (!Paystack::$fallback_to_file_get_contents) {
            return;
        }
        $context = stream_context_create(
            [
                'http'=>array(
                  'method'=>$this->method,
                  'header'=>$this->flattenedHeaders(),
                  'content'=>$this->body,
                  'ignore_errors' => true
                )
            ]
        );
        $this->PaystackHttpResponse->body = file_get_contents($this->endpoint, false, $context);
        if ($this->PaystackHttpResponse->body === false) {
            $this->PaystackHttpResponse->messages[] = 'file_get_contents failed with PaystackHttpResponse: \'' . error_get_last() . '\'.';
        } else {
            $this->PaystackHttpResponse->okay = true;
        }
    }

    public function attemptCurl()
    {
        //open connection
        $ch = \curl_init();
        \curl_setopt($ch, \CURLOPT_URL, $this->endpoint);
        ($this->method === PaystackContractsRouteInterface::POST_METHOD) && \curl_setopt($ch, \CURLOPT_POST, true);
        ($this->method === PaystackContractsRouteInterface::PUT_METHOD) && \curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'PUT');

        if ($this->method !== PaystackContractsRouteInterface::GET_METHOD) {
            \curl_setopt($ch, \CURLOPT_POSTFIELDS, $this->body);
        }
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, $this->flattenedHeaders());
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);
        $this->PaystackHttpResponse->forApi && \curl_setopt($ch, \CURLOPT_SSLVERSION, 6);

        $this->PaystackHttpResponse->body = \curl_exec($ch);

        if (\curl_errno($ch)) {
            $cerr = \curl_error($ch);
            $this->PaystackHttpResponse->messages[] = 'Curl failed with response: \'' . $cerr . '\'.';
        } else {
            $this->PaystackHttpResponse->okay = true;
        }

        \curl_close($ch);
    }
}

class PaystackHttpRequestBuilder
{
    protected $paystackObj;
    protected $interface;
    protected $PaystackHttpRequest;

    public $payload = [ ];
    public $sentargs = [ ];

    public function __construct($paystackObj, $interface, array $payload = [ ], array $sentargs = [ ])
    {
        $this->PaystackHttpRequest = new PaystackHttpRequest($paystackObj);
        $this->paystackObj = $paystackObj;
        $this->interface = $interface;
        $this->payload = $payload;
        $this->sentargs = $sentargs;
    }

    public function build()
    {
        $this->PaystackHttpRequest->headers["Authorization"] = "Bearer " . $this->paystackObj->secret_key;
        $this->PaystackHttpRequest->headers["User-Agent"] = "Paystack/v1 PhpBindings/" . Paystack::VERSION;
        $this->PaystackHttpRequest->endpoint = PaystackHelpersRouter::PAYSTACK_API_ROOT . $this->interface[PaystackContractsRouteInterface::ENDPOINT_KEY];
        $this->PaystackHttpRequest->method = $this->interface[PaystackContractsRouteInterface::METHOD_KEY];
        $this->moveArgsToSentargs();
        $this->putArgsIntoEndpoint($this->PaystackHttpRequest->endpoint);
        $this->packagePayload();
        return $this->PaystackHttpRequest;
    }

    public function packagePayload()
    {
        if (is_array($this->payload) && count($this->payload)) {
            if ($this->PaystackHttpRequest->method === PaystackContractsRouteInterface::GET_METHOD) {
                $this->PaystackHttpRequest->endpoint = $this->PaystackHttpRequest->endpoint . '?' . http_build_query($this->payload);
            } else {
                $this->PaystackHttpRequest->body = json_encode($this->payload);
            }
        }
    }

    public function putArgsIntoEndpoint(&$endpoint)
    {
        foreach ($this->sentargs as $key => $value) {
            $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
        }
    }

    public function moveArgsToSentargs()
    {
        if (!array_key_exists(PaystackContractsRouteInterface::ARGS_KEY, $this->interface)) {
            return;
        }
        $args = $this->interface[PaystackContractsRouteInterface::ARGS_KEY];
        foreach ($this->payload as $key => $value) {
            if (in_array($key, $args)) {
                $this->sentargs[$key] = $value;
                unset($this->payload[$key]);
            }
        }
    }
}



class PaystackHttpResponse
{
    public $okay;
    public $body;
    public $forApi;
    public $messages = [];

    private function parsePaystackPaystackHttpResponse()
    {
        $resp = \json_decode($this->body);

        if ($resp === null || !property_exists($resp, 'status') || !$resp->status) {
            throw new ApiException(
                "Paystack Request failed with Response: '" .
                $this->messageFromApiJson($resp)."'",
                $resp
            );
        }

        return $resp;
    }

    private function messageFromApiJson($resp)
    {
        $message = $this->body;
        if ($resp !== null) {
            if (property_exists($resp, 'message')) {
                $message = $resp->message;
            }
            if (property_exists($resp, 'errors') && ($resp->errors instanceof \stdClass)) {
                $message .= "\nErrors:\n";
                foreach ($resp->errors as $field => $errors) {
                    $message .= "\t" . $field . ":\n";
                    foreach ($errors as $_unused => $error) {
                        $message .= "\t\t" . $error->rule . ": ";
                        $message .= $error->message . "\n";
                    }
                }
            }
        }
        return $message;
    }

    private function implodedMessages()
    {
        return implode("\n\n", $this->messages);
    }

    public function wrapUp()
    {
        if ($this->okay && $this->forApi) {
            return $this->parsePaystackPaystackHttpResponse();
        }
        if (!$this->okay && $this->forApi) {
            throw new \Exception($this->implodedMessages());
        }
        if ($this->okay) {
            return $this->body;
        }
        error_log($this->implodedMessages());
        return false;
    }
}


class PaystackMetadataBuilder
{
    private $meta;
    public static $auto_snake_case = true;

    public function __construct()
    {
        $this->meta = [];
    }

    private function with($name, $value)
    {
        if ($name === 'custom_fields') {
            throw new PaystackExceptionBadMetaNameException('Please use the withCustomField method to add custom fields');
        }
        $this->meta[$name] = $value;
        return $this;
    }

    private function toSnakeCase($input)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    public function __call($method, $args)
    {
        if ((strpos($method, 'with') === 0) && ($method !== 'with')) {
            $name = substr($method, 4);
            if (PaystackMetadataBuilder::$auto_snake_case) {
                $name = $this->toSnakeCase($name);
            }
            return $this->with($name, $args[0]);
        }
        throw new \BadMethodCallException('Call to undefined function: ' . get_class($this) . '::' . $method);
    }

    public function withCustomField($title, $value)
    {
        if (!array_key_exists('custom_fields', $this->meta)) {
            $this->meta['custom_fields'] = [];
        }
        $this->meta['custom_fields'][] = [
            'display_name' => strval($title),
            'variable_name' => strval($title),
            'value' => strval($value),
        ];
        return $this;
    }

    public function build()
    {
        return json_encode($this->meta);
    }
}

class PaystackRoutesBalance implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/balance';
    }

    public static function getList()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesBalance::root() ];
    }
}


class PaystackRoutesBank implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/bank';
    }
    public static function getList()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesBank::root() ];
    }

    public static function resolveBvn()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesBank::root() . '/resolve_bvn/{bvn}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['bvn'] ];
    }

    public static function resolve()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesBank::root() . '/resolve',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['account_number',
                'bank_code' ] ];
    }
}


class PaystackRoutesCustomer implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/customer';
    }

    public static function create()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => ['first_name',
                'last_name',
                'email',
                'metadata',
                'phone' ],
            PaystackContractsRouteInterface::REQUIRED_KEY => [
                PaystackContractsRouteInterface::PARAMS_KEY => ['first_name',
                    'last_name',
                    'email' ]
            ]
        ];
    }

    public static function fetch()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ],
            PaystackContractsRouteInterface::REQUIRED_KEY => [PaystackContractsRouteInterface::ARGS_KEY => ['id' ] ]
        ];
    }

    public static function getList()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => ['perPage',
                'page' ]
        ];
    }

    public static function update()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::PUT_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root() . '/{id}',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['first_name',
                'last_name',
                'email',
                'metadata',
                'phone' ],
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ]
        ];
    }
}


class PaystackRoutesDecision implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/decision';
    }

    public static function bin()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesDecision::root() . '/bin/{bin}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['bin' ] ];
    }
}


class PaystackRoutesIntegration implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/integration';
    }

    public static function paymentSessionTimeout()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesIntegration::root() . '/payment_session_timeout',
            PaystackContractsRouteInterface::PARAMS_KEY   => [] ];
    }

    public static function updatePaymentSessionTimeout()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::PUT_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root() . '/payment_session_timeout',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['timeout'],
        ];
    }
}


class PaystackRoutesPage implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/page';
    }

    public static function create()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'name', 'description',
                'amount' ]
        ];
    }

    public static function fetch()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ]
        ];
    }

    public static function getList()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root() ];
    }

    public static function update()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::PUT_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root() . '/{id}',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'name',
                'description',
                'amount',
                'active' ],
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ]
        ];
    }
}


class PaystackRoutesPlan implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/plan';
    }

    public static function create()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPlan::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'name',
                'description',
                'amount',
                'interval',
                'send_invoices',
                'send_sms',
                'hosted_page',
                'hosted_page_url',
                'hosted_page_summary',
                'currency' ]
        ];
    }

    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPlan::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }

    public static function getList()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPlan::root() ];
    }

    public static function update()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::PUT_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPlan::root() . '/{id}',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'name',
                'description',
                'amount',
                'interval',
                'send_invoices',
                'send_sms',
                'hosted_page',
                'hosted_page_url',
                'hosted_page_summary',
                'currency' ],
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }
}


class PaystackRoutesSettlement implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/settlement';
    }

    public static function getList()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSettlement::root() ];
    }
}


class PaystackRoutesSubaccount implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/subaccount';
    }

    public static function create()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubaccount::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'business_name', 'settlement_bank',
                'account_number','percentage_charge',
                'primary_contact_email','primary_contact_name',
                'primary_contact_phone',
                'metadata','settlement_schedule',
            ],
        ];
    }

    public static function fetch()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubaccount::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id']
        ];
    }

    public static function getList()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY      => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY    => PaystackRoutesSubaccount::root()
        ];
    }

    public static function update()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::PUT_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubaccount::root() . '/{id}',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'business_name', 'settlement_bank',
                'account_number','percentage_charge',
                'primary_contact_email','primary_contact_name',
                'primary_contact_phone',
                'metadata','settlement_schedule'
            ],
            PaystackContractsRouteInterface::ARGS_KEY     => ['id']
        ];
    }
}


class PaystackRoutesSubscription implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/subscription';
    }

    public static function create()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'customer',
                'plan',
                'authorization' ]
        ];
    }

    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }

    public static function getList()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root() ];
    }

    public static function disable()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root(). '/disable',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'code',
                'token'] ];
    }

    public static function enable()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root() . '/enable',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'code',
                'token'] ];
    }
}


class PaystackRoutesTransaction implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/transaction';
    }

    public static function initialize()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/initialize',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['reference',
                'callback_url',
                'amount',
                'email',
                'plan' ]
        ];
    }

    public static function charge()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/charge_authorization',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['reference',
                'authorization_code',
                'email',
                'amount' ] ];
    }

    public static function chargeToken()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/charge_token',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['reference',
                'token',
                'email',
                'amount' ] ];
    }

    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }

    public static function getList()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() ];
    }

    public static function export()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/export',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['from',
                'to',
                'settled',
                'payment_page' ] ];
    }

    public static function totals()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/totals' ];
    }

    public static function verify()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/verify/{reference}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['reference' ] ];
    }

    public static function verifyAccessCode()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/verify_access_code/{access_code}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['access_code' ] ];
    }
}


class PaystackRoutesTransfer implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/transfer';
    }

    public static function initiate()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => ['source',
                'amount',
                'currency',
                'reason',
                'recipient' ]
        ];
    }

    public static function finalizeTransfer()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root() . '/finalize_transfer',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['reference',
                'transfer_code',
                'otp' ] ];
    }

    public static function resendOtp()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root() . '/resend_otp',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['transfer_code',
                'reason'] ];
    }

    public static function disableOtp()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root() . '/disable_otp' ];
    }

    public static function enableOtp()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root() . '/enable_otp' ];
    }

    public static function disableOtpFinalize()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root() . '/disable_otp_finalize',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['otp'] ];
    }

    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }

    public static function getList()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransfer::root() ];
    }
}


class PaystackRoutesTransferrecipient implements PaystackContractsRouteInterface
{

    public static function root()
    {
        return '/transferrecipient';
    }

    public static function create()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransferrecipient::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => ['type',
                'name',
                'description',
                'metadata',
                'bank_code',
                'currency',
                'account_number' ],
            PaystackContractsRouteInterface::REQUIRED_KEY => [
                PaystackContractsRouteInterface::PARAMS_KEY => ['type',
                    'name',
                    'bank_code',
                    'account_number' ]
            ]
        ];
    }

    public static function getList()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransferrecipient::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => ['perPage',
                'page' ]
        ];
    }
}
