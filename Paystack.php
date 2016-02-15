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
    private $routes=['customer','plan','transaction'];

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
                $secret_key = array_key_exists('paystack_key_test_secret', $params) ? $params['paystack_key_test_secret'] : '';
            } else {
                $secret_key = array_key_exists('paystack_key_live_secret', $params) ? $params['paystack_key_live_secret'] : '';
            }
        } else {
            $secret_key=$params_or_key;
        }
        
        if (!is_string($secret_key) || !(substr($secret_key, 0, 8)==='sk_'.($test_mode ? 'test_':'live_'))) {
            // Should never get here
            throw new \InvalidArgumentException('A Valid Paystack Secret Key must start with \'sk_\'.');
   
        }
         $this->secret_key = $secret_key;
    }


    /**
 * __call
 * Magic Method for getOne on routes
 *
 * @param $method - a string whose title case is a class in the
 *                  Paystack namespace implementing
 *                  PaystackRouteInterface
 * @param $args - arguments sent to the magic method
 *
 * @return the result of calling /{route}/get on the api
 *
 * @access public
 * @see    PaystackRouter
 * @since  1.0
 */
    public function __call($method, $args)
    {
        /*
        attempt to call getOne when the route is called directly
        translates to /{root}/{get}/{id}
        */
        if (in_array($method, $this->routes, true)) {
            $route = new PaystackRouter($method, $this);
    
            if (count($args) === 1 && is_integer($args[0])) {
                // no params, just one arg... the id
                $args = [[], [ PaystackRouter::ID_KEY => $args[0] ] ];
                return $route->__call('getOne', $args);
            } elseif (count($args) === 2 && is_integer($args[0]) && is_array($args[1])) {
                // there are params, and just one arg... the id
                $args = [$args[1], [ PaystackRouter::ID_KEY => $args[0] ] ];
                return $route->__call('getOne', $args);
            }
        }
        // Should never get here
        throw new \InvalidArgumentException(
            'Route "' .
            $method .
            '" only accepts an integer id and an optional array of paging arguments.'
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
            return new PaystackRouter($name, $this);
        }
    }
    

    /**
 * PaystackRouter
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
}

class PaystackRouter
{

    private $route;
    private $route_class;
    private $secret_key;
    private $methods;

    const ID_KEY = 'id';
    const PAYSTACK_API_ROOT = 'https://api.paystack.co';
    const HTTP_CODE_KEY = 'httpcode';
    const HEADER_KEY = 'header';
    const BODY_KEY = 'body';

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
        if (array_key_exists(PaystackRouteInterface:: ARGS_KEY, $interface)) {
            // to allow args to be specified in the payload, filter them out and put them in sentargs
            $sentargs = (!$sentargs) ? [ ] : $sentargs; // Make sure $sentargs is not null
            $args = $interface[PaystackRouteInterface::ARGS_KEY];
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
 

        $endpoint = PaystackRouter::PAYSTACK_API_ROOT . $interface[PaystackRouteInterface::ENDPOINT_KEY];
        $method = $interface[PaystackRouteInterface::METHOD_KEY];

        $this->moveArgsToSentargs($interface, $payload, $sentargs);
        $this->putArgsIntoEndpoint($endpoint, $sentargs);
 
        $headers = ["Authorization"=>"Bearer " . $this->secret_key ];
        $body = '';
        if (($method === PaystackRouteInterface::POST_METHOD)
            || ($method === PaystackRouteInterface::PUT_METHOD)
        ) {
            $headers["Content-Type"] = "application/json";
            $body = json_encode($payload);
        } elseif ($method === PaystackRouteInterface::GET_METHOD) {
            $endpoint = $endpoint . '?' . http_build_query($payload);
        }
        //
        //open connection
        
            $ch = \curl_init();
        // set url
            \curl_setopt($ch, \CURLOPT_URL, $endpoint);
 
        if ($method === PaystackRouteInterface::POST_METHOD || $method === PaystackRouteInterface::PUT_METHOD) {
            ($method === PaystackRouteInterface:: POST_METHOD) && \curl_setopt($ch, \CURLOPT_POST, true);
            ($method === PaystackRouteInterface ::PUT_METHOD) && \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

            \curl_setopt($ch, \CURLOPT_POSTFIELDS, $body);
        }
        //flatten the headers
            $flattened_headers = [];
        while (list($key, $value) = each($headers)) {
            $flattened_headers[] = $key . ": " . $value;
        }
            \curl_setopt($ch, \CURLOPT_HTTPHEADER, $flattened_headers);
            \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);
            \curl_setopt($ch, \CURLOPT_HEADER, 1);

            $response = \curl_exec($ch);

        if (\curl_errno($ch)) {   // should be 0
            // curl ended with an error
            \curl_close($ch);
            return [[],[],0];
        }

            $code = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        // Then, after your \curl_exec call:
            $header_size = \curl_getinfo($ch, \CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $header = $this->headersFromLines(explode("\n", trim($header)));
            $body = substr($response, $header_size);
            $body = json_decode($body, true);
            

        //close connection
            \curl_close($ch);

            return [
            0 => $header, 1 => $body, 2=> $code,
            PaystackRouter::HEADER_KEY => $header, PaystackRouter::BODY_KEY => $body,
            PaystackRouter::HTTP_CODE_KEY=>$code];

        
        

    }
    
    private function headersFromLines($lines)
    {
        $headers = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            $headers[trim($parts[0])][] = isset($parts[1])
            ? trim($parts[1])
            : null;
        }
        return $headers;
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
            throw new \Exception('Function "' . $method . '" does not exist for "' . $this->route . "'.");
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
        $this->route_class = 'Paystack' . ucwords($route);
        $this->secret_key = $paystackObj->secret_key;

        $mets = get_class_methods($this->route_class);
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
            $this->methods[$mtd] = \Closure::bind($mtdFunc, $this, get_class());
        }
    }
}
interface PaystackRouteInterface
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
class PaystackCustomer implements PaystackRouteInterface
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
            PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::POST_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackCustomer::root(),
            PaystackRouteInterface::PARAMS_KEY   => ['first_name',
                'last_name',
                'email',
                'phone' ],
            PaystackRouteInterface::REQUIRED_KEY => [
                PaystackRouteInterface::PARAMS_KEY => ['first_name',
                    'last_name',
                    'email' ]
            ]
        ];
    }

    /**
      Get customer
     */
    public static function getOne()
    {
        return [
            PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackCustomer::root() . '/{id}',
            PaystackRouteInterface::ARGS_KEY     => ['id' ],
            PaystackRouteInterface::REQUIRED_KEY => [PaystackRouteInterface::ARGS_KEY => ['id' ] ]
        ];
    }

    /**
      List customers
     */
    public static function getList()
    {
        return [
            PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackCustomer::root(),
            PaystackRouteInterface::PARAMS_KEY   => ['perPage',
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
            PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::PUT_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackCustomer::root() . '/{id}',
            PaystackRouteInterface::PARAMS_KEY   => ['first_name',
                'last_name',
                'email',
                'phone' ],
            PaystackRouteInterface::ARGS_KEY     => ['id' ],
            PaystackRouteInterface::REQUIRED_KEY => [
                PaystackRouteInterface::ARGS_KEY   => ['id' ],
                PaystackRouteInterface::PARAMS_KEY => ['first_name',
                    'last_name' ]
            ]
        ];
    }
}
class PaystackPlan implements PaystackRouteInterface
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::POST_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackPlan::root(),
            PaystackRouteInterface::PARAMS_KEY   => [
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
     * getOne
     * Insert description here
     *
     * @return
     *
     * @access
     * @static
     * @see
     * @since
     */
    public static function getOne()
    {
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackPlan::root() . '/{id}',
            PaystackRouteInterface::ARGS_KEY     => ['id' ] ];
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackPlan::root() ];
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::PUT_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackPlan::root() . '/{id}',
            PaystackRouteInterface::PARAMS_KEY   => [
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
            PaystackRouteInterface::ARGS_KEY     => ['id' ] ];
    }
}
class PaystackTransaction implements PaystackRouteInterface
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::POST_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackTransaction::root() . '/initialize',
            PaystackRouteInterface::PARAMS_KEY   => ['reference',
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::POST_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackTransaction::root() . '/charge_authorization',
            PaystackRouteInterface::PARAMS_KEY   => ['reference',
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::POST_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackTransaction::root() . '/charge_token',
            PaystackRouteInterface::PARAMS_KEY   => ['reference',
                'token',
                'email',
                'amount' ] ];
    }
    /**
     * Get transaction by ID
     *
     * @static
     */
    public static function getOne()
    {
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackTransaction::root() . '/{id}',
            PaystackRouteInterface::ARGS_KEY     => ['id' ] ];
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
        return [ PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackTransaction::root() ];
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackTransaction::root() . '/totals' ];
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
        return [PaystackRouteInterface::METHOD_KEY   => PaystackRouteInterface::GET_METHOD,
            PaystackRouteInterface::ENDPOINT_KEY => PaystackTransaction::root() . '/verify/{reference}',
            PaystackRouteInterface::ARGS_KEY     => ['reference' ] ];
    }
}
