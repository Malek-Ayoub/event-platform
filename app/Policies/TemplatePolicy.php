<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TemplatePolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'templates.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, EmailTemplate|SmsTemplate $template): bool
    {
        return $this->canView($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, EmailTemplate|SmsTemplate $template): bool
    {
        return $this->canManage($user, $template, self::PERMISSION);
    }

    public function delete(User $user, EmailTemplate|SmsTemplate $template): bool
    {
        return $this->canManage($user, $template, self::PERMISSION);
    }

    protected function canView(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $venueId = $this->venueIdFrom($model);

        if ($venueId === null) {
            return false;
        }

        return $user->belongsToVenue($venueId);
    }
}
