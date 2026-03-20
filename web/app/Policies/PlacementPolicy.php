<?php

namespace App\Policies;

use App\Models\Placement;
use App\Models\User;

class PlacementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Placement $placement): bool
    {
        return in_array($user->role, ['admin', 'account_manager'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'account_manager'], true);
    }

    public function update(User $user, Placement $placement): bool
    {
        return in_array($user->role, ['admin', 'account_manager'], true);
    }

    public function delete(User $user, Placement $placement): bool
    {
        return $user->role === 'admin';
    }
}
