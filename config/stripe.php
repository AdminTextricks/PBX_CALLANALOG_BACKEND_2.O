<?php 

return [

    'stripe' => [
          'secret_test' => env('STRIPE_TEST_SECRET'),
          'secret_live' => env('STRIPE_SECRET') 
    ],

];


