====================
# PHP Paystack Class
A class to make Paystack API calls by including a single file or from within codeigniter.

## Requirements
- Curl 7.34.0 or more recent
- PHP 5.4.0 or more recent
- OpenSSL v1.0.1 or more recent

##Get it
Download [Paystack.php](Paystack.php)

##Usage - Direct use
The main idea here is to be as simple as possible, basically you just instantiate the library and execute
any of the methods in it, all the public API methods available for the moment are implemented.

```php
  // Require the paystack class
  require_once 'Paystack.php';

	// Configuration options
	$config['paystack_key_test_secret']         = 'sk_test_xxxx';
	$config['paystack_key_live_secret']         = 'sk_live_xxxx';
	$config['paystack_test_mode']               = TRUE; // set to false when you are ready to go live

	// Create the library object
	$paystack = new Paystack( $config );

	// Run the required operations
	$response = $paystack->customer_list();
	$response = $paystack->customer->list(['perPage'=>5,'page'=>2]);
	// list the second page at 5 customers per page

  $response = $paystack->customer->create([
                            'first_name'=>'Dafe',
                            'last_name'=>'Aba',
                            'email'=>"dafe@aba.c",
                            'phone'=>'08012345678'
                          ]);
  $response = $paystack->transaction->initialize([
                            'reference'=>'unique_refencecode',
                            'amount'=>'120000',
                            'email'=>'dafe@aba.c'
                          ]);
  $response = $paystack->transaction->verify([
                            'reference'=>'refencecode'
                          ]);
```

That's it! Have fun.

##Usage - Codeigniter
Paste the file as your {APPLICATION}/libraries/Paystack.php

This library is completely functional as standalone but is developed as a Codeigniter library,
to use it that way you simply create a config file in: {APPLICATION}/config/paystack.php to store the config array.

Remember to uncomment the CodeIgniter access check before using.

A sample config file is here: [config/paystack.php](config/paystack.php)

```php
	// Create the library object
	$this->load->library( 'paystack' );

	// Run the required operations
	$response = $this->paystack->customer_list();
	// list the second page at 5 customers per page
	$response = $this->paystack->transaction->initialize([
                            'reference'=>'unique_refencecode',
                            'amount'=>'120000',
                            'email'=>'dafe@aba.c'
                          ]);
  $response = $this->paystack->transaction->verify([
                            'reference'=>'refencecode'
                          ]);
```

---------
##Samples
Check [SAMPLES](SAMPLES.md) for more sample API calls