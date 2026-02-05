<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
    * Determine whether the authenticate user can create users.
    *
    * @return boolean
    */
    public function create(User $user)
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the authenticate user can update the user.
     *
     * @param  \App\User  $user
     * @param  \App\User  $model
     * @return boolean
     */
    public function update(User $user, User $model)
    {
        if (config('app.is_demo')) {
            return ($user->isAdmin() || $model->id == $user->id)  && ($model->id !== 1 && $model->id !== 2 && $model->id !== 3);
        } else {
            return $user->isAdmin();
        }
    }

    /**
     * Determine whether the authenticate user can delete the user.
     *
     * @param  \App\User  $user
     * @param  \App\User  $model
     * @return boolean
     */
    public function delete(User $user, User $model) {
        if (config('app.is_demo')) {
            return $user->isAdmin() && $user->id != $model->id && $model->id !== 1 && $model->id !== 2 && $model->id !== 3;
        } else {
            return $user->isAdmin() && $user->id != $model->id;
        }
    }

    /**
     * Determine whether the authenticate user can manage other users.
     *
     * @param  \App\User  $user
     * @return boolean
     */
    public function manageUsers(User $user)
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the authenticate user can manage items and other related entities(tags, categories).
     *
     * @param  \App\User  $user
     * @return boolean
     */
    public function manageItems(User $user)
    {
        return $user->isAdmin() || $user->isCreator();
    }

    public function manageSubscrTypes(User $user)
    {
        return $user->isAdmin() || $user->isCreator();
    }

    public function manageUserAttrs(User $user)
    {
        return $user->isAdmin() || $user->isCreator();
    }

    public function managePermissions(User $user)
    {
        return $user->isAdmin() || $user->isCreator();
    }
}
