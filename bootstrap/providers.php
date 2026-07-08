<?php

use App\Providers\AppServiceProvider;
use App\Providers\PaymentGatewayServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Providers\WebhookServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PaymentGatewayServiceProvider::class,
    WebhookServiceProvider::class,
    TenancyServiceProvider::class,
];
