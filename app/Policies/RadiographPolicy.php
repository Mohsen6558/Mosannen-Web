<?php

namespace App\Policies;

use App\Models\Radiograph;
use App\Models\User;

class RadiographPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('images.view');
    }

    public function view(User $user, Radiograph $radiograph): bool
    {
        return $user->can('images.view');
    }

    public function create(User $user): bool
    {
        return $user->can('images.upload');
    }

    public function update(User $user, Radiograph $radiograph): bool
    {
        return $user->can('images.upload');
    }

    public function delete(User $user, Radiograph $radiograph): bool
    {
        return $user->can('images.delete');
    }
}
