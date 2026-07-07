<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\SmsTemplate;
use App\Models\TaxRate;
use App\Models\TicketType;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\Venue;
use App\Models\WebhookLog;
use App\Policies\ActivityLogPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CouponPolicy;
use App\Policies\EventPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentTransactionPolicy;
use App\Policies\PlatformSettingPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductVariantPolicy;
use App\Policies\PromoCodePolicy;
use App\Policies\RefundPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\TaxRatePolicy;
use App\Policies\TemplatePolicy;
use App\Policies\TicketTypePolicy;
use App\Policies\UserPermissionPolicy;
use App\Policies\UserPolicy;
use App\Policies\VenuePolicy;
use App\Policies\WebhookLogPolicy;
use App\Services\Authorization\PermissionGateRegistrar;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Venue::class => VenuePolicy::class,
        UserPermission::class => UserPermissionPolicy::class,
        Category::class => CategoryPolicy::class,
        Event::class => EventPolicy::class,
        TicketType::class => TicketTypePolicy::class,
        Reservation::class => ReservationPolicy::class,
        Product::class => ProductPolicy::class,
        ProductVariant::class => ProductVariantPolicy::class,
        Coupon::class => CouponPolicy::class,
        PromoCode::class => PromoCodePolicy::class,
        TaxRate::class => TaxRatePolicy::class,
        Order::class => OrderPolicy::class,
        PaymentTransaction::class => PaymentTransactionPolicy::class,
        Refund::class => RefundPolicy::class,
        PlatformSetting::class => PlatformSettingPolicy::class,
        Notification::class => NotificationPolicy::class,
        EmailTemplate::class => TemplatePolicy::class,
        SmsTemplate::class => TemplatePolicy::class,
        ActivityLog::class => ActivityLogPolicy::class,
        WebhookLog::class => WebhookLogPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerAuthorizationGates();
    }

    protected function registerAuthorizationGates(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if ($ability === 'manageUserPermissions') {
                return null;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        Gate::define('manageUserPermissions', function (User $actor, User $target, int $venueId): bool {
            return app(UserPermissionPolicy::class)->manageUserPermissions($actor, $target, $venueId);
        });

        app(PermissionGateRegistrar::class)->registerAll();
    }
}
