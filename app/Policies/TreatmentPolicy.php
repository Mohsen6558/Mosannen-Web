<?php

namespace App\Policies;

use App\Models\Treatment;
use App\Models\User;

class TreatmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('treatments.view');
    }

    public function view(User $user, Treatment $model): bool
    {
        return $user->can('treatments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('treatments.create');
    }

    public function update(User $user, Treatment $model): bool
    {
        return $user->can('treatments.update');
    }

    public function delete(User $user, Treatment $model): bool
    {
        return $user->can('treatments.delete');
    }
}
