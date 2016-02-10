====================
# PHP Paystack Class
A class to make Paystack API calls by including a single file or from within codeigniter.


--------
##Get it
Download [Paystack.php](Paystack.php)


--------------------
##Usage - Direct use
The main idea here is to be as simple as possible, basically you just instantiate the library and execute
any of the methods in it, all the public API methods available for the moment are implemented.

Remember to remove the CodeIgniter access check before using. 

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
	list($headers, $body) = $paystack->customer_list();
	list($headers, $body) = $paystack->customer->list(['perPage'=>5,'page'=>2]); 
	// list the second page at 5 customers per page

  list($headers, $body) = $paystack->customer->create([
                            'first_name'=>'Dafe', 
                            'last_name'=>'Aba', 
                            'email'=>"dafe@aba.c", 
                            'phone'=>'08012345678'
                          ]);
  list($headers, $body) = $paystack->transaction->initialize([
                            'reference'=>'unique_refencecode', 
                            'amount'=>'120000', 
                            'email'=>'dafe@aba.c'
                          ]);
  list($headers, $body) = $paystack->transaction->verify([
                            'reference'=>'refencecode'
                          ]);
```

That's it! Have fun.

---------------------
##Usage - Codeigniter
Paste the file as your {APPLICATION}/libraries/Paystack.php

This library is completely functional as standalone but is developed as a Codeigniter library,
to use it that way you simply create a config file in: {APPLICATION}/config/paystack.php to store the config array.

A sample config file is here: [config/paystack.php](config/paystack.php)

```php
	// Create the library object
	$this->load->library( 'paystack' );
	
	// Run the required operations
	list($headers, $body) = $this->paystack->customer_list();
	// list the second page at 5 customers per page
	list($headers, $body) = $this->paystack->transaction->initialize([
                            'reference'=>'unique_refencecode', 
                            'amount'=>'120000', 
                            'email'=>'dafe@aba.c'
                          ]);
  list($headers, $body) = $this->paystack->transaction->verify([
                            'reference'=>'refencecode'
                          ]);
```

---------
##Samples
Check [SAMPLES](SAMPLES.md) for more sample API calls