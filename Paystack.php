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
    private $routes=['customer','plan','transaction','page','subscription'];

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

    /**
 * __call
 * Magic Method for fetch on routes
 *
 * @param $method - a string whose title case is a class in the
 *                  PaystackRoutes namespace implementing
 *                  PaystackContractsRouteInterface
 * @param $args - arguments sent to the magic method
 *
 * @return the result of calling /{route}/get on the api
 *
 * @access public
 * @see    PaystackHelpersRouter
 * @since  1.0
 */
    public function __call($method, $args)
    {
        /*
        attempt to call fetch when the route is called directly
        translates to /{root}/{get}/{id}
        */
        
        if (in_array($method, $this->routes, true) && count($args) === 1) {
            $route = new PaystackHelpersRouter($method, $this);
            // no params, just one arg... the id
            $args = [[], [ PaystackHelpersRouter::ID_KEY => $args[0] ] ];
            return $route->__call('fetch', $args);
        }

        // Not found is it plural?
        $is_plural = strripos($method, 's')===(strlen($method)-1);
        $singular_form = substr($method, 0, strlen($method)-1);

        if ($is_plural && in_array($singular_form, $this->routes, true)) {
            $route = new PaystackHelpersRouter($singular_form, $this);
            if ((count($args) === 1 && is_array($args[0]))||(count($args) === 0)) {
                return $route->__call('getList', $args);
            }
        }
                
        // Should never get here
        throw new InvalidArgumentException(
            'Route "' . $method . '" can only accept '.
            ($is_plural ?
                        'an optional array of paging arguments (perPaystackRoutesPage, page)'
                        : 'an id or code') . '.'
        );
    }

    /**
 * __get
 * Insert description here
 *
 * @param $name
 *
 * @return
 *
 * @access
 * @static
 * @see
 * @since
 */
    public function __get($name)
    {
        if (in_array($name, $this->routes, true)) {
            return new PaystackHelpersRouter($name, $this);
        }
    }
}


/**
 * A Route
 */

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

    /**
     */
    public static function root();
}




/**
 * PaystackHelpersRouter
 * Insert description here
 *
 * @category
 * @package
 * @author
 * @copyright
 * @license
 * @version
 * @link
 * @see
 * @since
 */
class PaystackHelpersRouter
{

    private $route;
    private $route_class;
    private $secret_key;
    private $methods;
    const ID_KEY = 'id';
    const PAYSTACK_API_ROOT = 'https://api.paystack.co';

    /**
 * moveArgsToSentargs
 * Insert description here
 *
 * @param $interface
 * @param $payload
 * @param $sentargs
 *
 * @return
 *
 * @access
 * @static
 * @see
 * @since
 */
    private function moveArgsToSentargs(
        $interface,
        &$payload,
        &$sentargs
    ) {



        // check if interface supports args
        if (array_key_exists(PaystackContractsRouteInterface:: ARGS_KEY, $interface)) {
            // to allow args to be specified in the payload, filter them out and put them in sentargs
            $sentargs = (!$sentargs) ? [ ] : $sentargs; // Make sure $sentargs is not null
            $args = $interface[PaystackContractsRouteInterface::ARGS_KEY];
            while (list($key, $value) = each($payload)) {
                // check that a value was specified
                // with a key that was expected as an arg
                if (in_array($key, $args)) {
                    $sentargs[$key] = $value;
                    unset($payload[$key]);
                }
            }
        }
    }

    /**
 * putArgsIntoEndpoint
 * Insert description here
 *
 * @param $endpoint
 * @param $sentargs
 *
 * @return
 *
 * @access
 * @static
 * @see
 * @since
 */
    private function putArgsIntoEndpoint(&$endpoint, $sentargs)
    {
        // substitute sentargs in endpoint
        while (list($key, $value) = each($sentargs)) {
            $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
        }
    }

    /**
 * callViaCurl
 * Insert description here
 *
 * @param $interface
 * @param $payload
 * @param $sentargs
 *
 * @return
 *
 * @access
 * @static
 * @see
 * @since
 */
    private function callViaCurl($interface, $payload = [ ], $sentargs = [ ])
    {
 

        $endpoint = PaystackHelpersRouter::PAYSTACK_API_ROOT . $interface[PaystackContractsRouteInterface::ENDPOINT_KEY];
        $method = $interface[PaystackContractsRouteInterface::METHOD_KEY];

        $this->moveArgsToSentargs($interface, $payload, $sentargs);
        $this->putArgsIntoEndpoint($endpoint, $sentargs);
 
        $headers = ["Authorization"=>"Bearer " . $this->secret_key ];
        $body = '';
        if (($method === PaystackContractsRouteInterface::POST_METHOD)
            || ($method === PaystackContractsRouteInterface::PUT_METHOD)
        ) {
            $headers["Content-Type"] = "application/json";
            $body = json_encode($payload);
        } elseif ($method === PaystackContractsRouteInterface::GET_METHOD) {
            $endpoint = $endpoint . '?' . http_build_query($payload);
        }
        
        //open connection
    
        $ch = curl_init();
        // set url
        curl_setopt($ch, CURLOPT_URL, $endpoint);

        if ($method === PaystackContractsRouteInterface::POST_METHOD || $method === PaystackContractsRouteInterface::PUT_METHOD) {
            ($method === PaystackContractsRouteInterface:: POST_METHOD) && curl_setopt($ch, CURLOPT_POST, true);
            ($method === PaystackContractsRouteInterface ::PUT_METHOD) && curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        //flatten the headers
        $flattened_headers = [];
        while (list($key, $value) = each($headers)) {
            $flattened_headers[] = $key . ": " . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $flattened_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Make sure CURL_SSLVERSION_TLSv1_2 is defined as 6
        // Curl must be able to use TLSv1.2 to connect
        // to Paystack servers
        
        if (!defined('CURL_SSLVERSION_TLSV1_2')) {
            define('CURL_SSLVERSION_TLSV1_2', 6);
        }
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSV1_2);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {   // should be 0
            // curl ended with an error
            $cerr = curl_error($ch);
            curl_close($ch);
            throw new Exception("Curl failed with response: '" . $cerr . "'.");
        }

        // Then, after your curl_exec call:
        $resp = json_decode($response);
        //close connection
        curl_close($ch);

        if (!$resp->status) {
            throw new Exception("Paystack Request failed with response: '" . $resp->message . "'.");
        }

        return $resp;
    

    }
    
    /**
 * __call
 * Insert description here
 *
 * @param $methd
 * @param $sentargs
 *
 * @return
 *
 * @access
 * @static
 * @see
 * @since
 */
    public function __call($methd, $sentargs)
    {
        $method = ($methd === 'list' ? 'getList' : $methd );
        if (array_key_exists($method, $this->methods) && is_callable($this->methods[$method])) {
            return call_user_func_array($this->methods[$method], $sentargs);
        } else {
            // User attempted to call a function that does not exist
            throw new Exception('Function "' . $method . '" does not exist for "' . $this->route . '".');
        }
    }

    /**
 * A magic resource object that can make method calls to API
 *
 * @param $route
 * @param $paystackObj - A Paystack Object
 */
    public function __construct($route, $paystackObj)
    {
        $this->route = strtolower($route);
        $this->route_class = 'PaystackRoutes' . ucwords($route);
        $this->secret_key = $paystackObj->secret_key;

        $mets = get_class_methods($this->route_class);
        if (empty($mets)) {
            throw new InvalidArgumentException('Class "' . $this->route . '" does not exist.');
        }
        // add methods to this object per method, except root
        foreach ($mets as $mtd) {
            if ($mtd === 'root') {
                // skip root method
                continue;
            }
            /**
 * array
 * Insert description here
 *
 * @param $params
 * @param array
 * @param $sentargs
 *
 * @return
 *
 * @access
 * @static
 * @see
 * @since
 */
            $mtdFunc = function (
                array $params = [ ],
                array $sentargs = [ ]
            ) use ($mtd) {
                $interface = call_user_func($this->route_class . '::' . $mtd);
                // TODO: validate params and sentargs against definitions
                return $this->callViaCurl($interface, $params, $sentargs);
            };
            $this->methods[$mtd] = Closure::bind($mtdFunc, $this, get_class());
        }
    }
}



/**
 * PaystackRoutesCustomer
 * Insert description here
 *
 * @category
 * @package
 * @author
 * @copyright
 * @license
 * @version
 * @link
 * @see
 * @since
 */
class PaystackRoutesCustomer implements PaystackContractsRouteInterface
{

    /**
      Root
     *
      @param=> first_name, last_name, email, phone
     */
    public static function root()
    {
        return '/customer';
    }

    /**
      Create customer
     *
      @param=> first_name, last_name, email, phone
     */
    public static function create()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => ['first_name',
                'last_name',
                'email',
                'phone' ],
            PaystackContractsRouteInterface::REQUIRED_KEY => [
                PaystackContractsRouteInterface::PARAMS_KEY => ['first_name',
                    'last_name',
                    'email' ]
            ]
        ];
    }

    /**
      Get customer by ID or code
     */
    public static function fetch()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ],
            PaystackContractsRouteInterface::REQUIRED_KEY => [PaystackContractsRouteInterface::ARGS_KEY => ['id' ] ]
        ];
    }

    /**
      List customers
     */
    public static function getList()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => ['perPaystackRoutesPage',
                'page' ]
        ];
    }

    /**
      Update customer
     *
      @param=> first_name, last_name, email, phone
     */
    public static function update()
    {
        return [
            PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::PUT_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesCustomer::root() . '/{id}',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['first_name',
                'last_name',
                'email',
                'phone' ],
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ],
            PaystackContractsRouteInterface::REQUIRED_KEY => [
                PaystackContractsRouteInterface::ARGS_KEY   => ['id' ],
                PaystackContractsRouteInterface::PARAMS_KEY => ['first_name',
                    'last_name' ]
            ]
        ];
    }
}



/**
 * PaystackRoutesPage
 * Insert description here
 *
 * @category
 * @package
 * @author
 * @copyright
 * @license
 * @version
 * @link
 * @see
 * @since
 */
class PaystackRoutesPage implements PaystackContractsRouteInterface
{

    /**
      Root
     */
    public static function root()
    {
        return '/page';
    }
    /*
      Create page
     */

    /**
     * create
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function create()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root(),
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'name',
                'description',
                'amount' ]
        ];
    }
    /*
      Get page
     */

    /**
     * fetch
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }

    /*
      List page
     */

    /**
     * getList
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function getList()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root() ];
    }
    /*
      Update page
     */

    /**
     * update
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function update()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::PUT_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPage::root() . '/{id}',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'name',
                'description' ],
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }
}



/**
 * PaystackRoutesPlan
 * Insert description here
 *
 * @category
 * @package
 * @author
 * @copyright
 * @license
 * @version
 * @link
 * @see
 * @since
 */
class PaystackRoutesPlan implements PaystackContractsRouteInterface
{

    /**
      Root
     */
    public static function root()
    {
        return '/plan';
    }
    /*
      Create plan
     */

    /**
     * create
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
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
    /*
      Get plan
     */

    /**
     * fetch
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPlan::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }

    /*
      List plan
     */

    /**
     * getList
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function getList()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesPlan::root() ];
    }
    /*
      Update plan
     */

    /**
     * update
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
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




/**
 * PaystackRoutesSubscription
 * Insert description here
 *
 * @category
 * @package
 * @author
 * @copyright
 * @license
 * @version
 * @link
 * @see
 * @since
 */
class PaystackRoutesSubscription implements PaystackContractsRouteInterface
{

    /**
      Root
     */
    public static function root()
    {
        return '/subscription';
    }
    /*
      Create subscription
     */

    /**
     * create
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
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
    /*
      Get subscription
     */

    /**
     * fetch
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }

    /*
      List subscription
     */

    /**
     * getList
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function getList()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root() ];
    }
    /*
      Disable subscription
     */

    /**
     * disable
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function disable()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root(). '/disable',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'code',
                'token'] ];
    }
    
    /*
      Enable subscription
     */

    /**
     * enable
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function enable()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesSubscription::root() . '/enable',
            PaystackContractsRouteInterface::PARAMS_KEY   => [
                'code',
                'token'] ];
    }
}




/**
 * PaystackRoutesTransaction
 * Insert description here
 *
 * @category
 * @package
 * @author
 * @copyright
 * @license
 * @version
 * @link
 * @see
 * @since
 */
class PaystackRoutesTransaction implements PaystackContractsRouteInterface
{

    /**
      Root
     */
    public static function root()
    {
        return '/transaction';
    }
    /**
     * Initialize transaction
     *
     * @return array - definition for this route
     *
     * @static
     */
    public static function initialize()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/initialize',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['reference',
                'amount',
                'email',
                'plan' ]
        ];
    }
    /**
     * Charge authorization
     *
     * @static
     */
    public static function charge()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/charge_authorization',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['reference',
                'authorization_code',
                'email',
                'amount' ] ];
    }
    /**
     * Charge token
     *
     * @static
     */
    public static function chargeToken()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::POST_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/charge_token',
            PaystackContractsRouteInterface::PARAMS_KEY   => ['reference',
                'token',
                'email',
                'amount' ] ];
    }
    /**
     * Get transaction by ID
     *
     * @static
     */
    public static function fetch()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/{id}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['id' ] ];
    }
    
    /**
     * List transactions
     *
     * @static
     * @see
     * @since
     */
    public static function getList()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() ];
    }
    /**
     * Export transactions
     *
     * @static
     * @see
     * @since
     */
    public static function export()
    {
        return [ PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/export' ];
    }
    /*
      Get totals
     */

    /**
     * totals
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function totals()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/totals' ];
    }
    /*
      Verify transaction
     */

    /**
     * verify
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function verify()
    {
        return [PaystackContractsRouteInterface::METHOD_KEY   => PaystackContractsRouteInterface::GET_METHOD,
            PaystackContractsRouteInterface::ENDPOINT_KEY => PaystackRoutesTransaction::root() . '/verify/{reference}',
            PaystackContractsRouteInterface::ARGS_KEY     => ['reference' ] ];
    }
}
