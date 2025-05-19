<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Paths
    |--------------------------------------------------------------------------
    |
    | Here you may specify the paths that should be exposed to CORS requests.
    | You can also enable CORS for all your routes by setting a wildcard '*'.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | These are the HTTP methods that are allowed for CORS requests.
    | The '*' wildcard allows all methods.
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | The origins that are allowed to make CORS requests. Wildcard '*' allows
    | all origins.
    |
    */

    'allowed_origins' => ['*'],

    // old
    // 'allowed_origins' => ['http://127.0.0.1:5500', 'http://localhost:5500', 'https://sanoh-scm.vercel.app', 'https://api.edutrashgo.com','http://ssp.ns1.sanoh.co.id','http://be-ssp.ns1.sanoh.co.id'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | The headers that are allowed in a CORS request. Wildcard '*' allows all
    | headers.
    |
    */

    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | The headers that should be exposed in the CORS response.
    |
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | The number of seconds that the browser should cache the preflight request
    | response.
    |
    */

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Whether or not the response can be shared when requests include credentials
    | such as cookies, HTTP authentication, or client-side SSL certificates.
    |
    */

    'supports_credentials' => true,

];
