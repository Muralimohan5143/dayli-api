<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionSubType extends Model
{
    protected $table = 'subscription_sub_types';
    protected $fillable = ['subscription_type_id', 'name', 'slug', 'status'];

    public function type()
    {
        return $this->belongsTo(SubscriptionType::class, 'subscription_type_id');
    }
}
