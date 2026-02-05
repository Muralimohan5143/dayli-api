<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserAttrType extends Model
{
    protected $table = 'user_attr_types';

    protected $fillable = ['name', 'description', 'status', 'decommissioned_date'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_attr', 'attr_id', 'user_id');
    }
}
