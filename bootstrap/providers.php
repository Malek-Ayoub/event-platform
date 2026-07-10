<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\OutboxServiceProvider;
use App\Providers\PaymentGatewayServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    OutboxServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    TenancyServiceProvider::class,
];
