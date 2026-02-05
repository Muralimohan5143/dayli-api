<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAttrType;

class UserAttrTypePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAny(User $user): bool
    {
        return ($user->isAdmin() || $user->isCreator());
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserAttrType $userAttrType): bool
    {
        return ($user->isAdmin() || $user->isCreator());
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ($user->isAdmin() || $user->isCreator());
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserAttrType $userAttrType): bool
    {
        return ($user->isAdmin() || $user->isCreator());
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserAttrType $userAttrType): bool
    {
        return ($user->isAdmin() || $user->isCreator());
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserAttrType $userAttrType): bool
    {
      return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserAttrType $userAttrType): bool
    {
        return false;
    }
}
