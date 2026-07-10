<?php

use App\Providers\AppServiceProvider;
use App\Providers\PaymentGatewayServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    TenancyServiceProvider::class,
];
