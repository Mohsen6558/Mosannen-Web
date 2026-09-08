<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $model): bool
    {
        return $user->can('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function update(User $user, Payment $model): bool
    {
        return $user->can('payments.update');
    }

    public function delete(User $user, Payment $model): bool
    {
        return $user->can('payments.delete');
    }

    /** Back-dating a receipt was its own permission in the legacy app too. */
    public function changeDate(User $user): bool
    {
        return $user->can('payments.change-date');
    }

    public function applyDiscount(User $user): bool
    {
        return $user->can('payments.discount');
    }
}
