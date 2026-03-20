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
        if ($user->role === 'admin' || $user->role === 'account_manager') {
            return true;
        }

        if ($user->role !== 'employee' || $user->consultant_id === null) {
            return false;
        }

        return (int) $placement->consultant_id === (int) $user->consultant_id;
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
