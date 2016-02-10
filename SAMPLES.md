#Sample calls 

Assumes that you already copied and configured the Paystack class. And that you have created and 
configured the $paystack object as you want. Check [README](README.md) for details.

``` php
// Make a call to the resource/method
// $this->paystack->{resource}->{method}(); 
// for gets, use $this->paystack->{resource}(id)

// customer
$this->paystack->customer(12);
$this->paystack->customer->list();
$this->paystack->customer->create([
                'first_name'=>'name',
                'last_name'=>'name',
                'email'=>'email',
                'phone'=>'phone'
              ]);
$this->paystack->customer->update([
                'id'=>233,
                'first_name'=>'name',
                'last_name'=>'name',
                'email'=>'email',
                'phone'=>'phone'
              ]);
$this->paystack->customer->list(['perPage'=>5,'page'=>2]); // list the second page at 5 customers per page

// plan
$this->paystack->plan(12);
$this->paystack->plan->list();
$this->paystack->plan->create([
                'name'=>'name',
                'description'=>'Describe at length',
                'amount'=>1000, // in kobo
                'interval'=>7,
                'send_invoices'=>true,
                'send_sms'=>true,
                'hosted_page'=>'url',
                'hosted_page_url'=>'url',
                'hosted_page_summary'=>'details',
                'currency'=>'NGN'
              ]);
$this->paystack->plan->update([
                'name'=>'name',
                'description'=>'Describe at length',
                'amount'=>1000, // in kobo
                'interval'=>7,
                'send_invoices'=>true,
                'send_sms'=>true,
                'hosted_page'=>'url',
                'hosted_page_url'=>'url',
                'hosted_page_summary'=>'details',
                'currency'=>'NGN'
              ],['id'=>233]);
$this->paystack->plan->list(['perPage'=>5,'page'=>2]); // list the second page at 5 plans per page

// transaction
$this->paystack->transaction(12);
$this->paystack->transaction->list();
$this->paystack->transaction->initialize([
                'reference'=>'unique',
                'amount'=>19000, // in kobo
                'email'=>'e@ma.il', 
                'plan'=>1 // optional, don't include unless it has a value
              ]);
$this->paystack->transaction->charge([
                'reference'=>'unique',
                'authorization_code'=>'auth_code',
                'email'=>'e@ma.il',
                'amount'=>1000 // in kobo
              ]);
$this->paystack->transaction->chargeToken([
                'reference'=>'unique',
                'token'=>'pstk_token',
                'email'=>'e@ma.il',
                'amount'=>1000 // in kobo
              ]);
$this->paystack->transaction->list(['perPage'=>5,'page'=>2]); // list the second page at 5 transactions per page

$this->paystack->transaction->verify([
                'reference'=>'unique_refencecode'
                ]);
$this->paystack->transaction->totals();


```