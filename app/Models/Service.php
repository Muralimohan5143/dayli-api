<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $primaryKey = 'service_id';

    protected $fillable = [
        'title',
        'service_type',
        'handle',
        'description',
        'category',
        'tags',
        'requires_booking',
        'is_active',
        'img_src',
        'meta',
    ];

    protected $casts = [
        'tags' => 'array',
        'meta' => 'array',
        'requires_booking' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function variants()
    {
        return $this->hasMany(ServiceVariant::class, 'service_id', 'service_id');
    }
}
