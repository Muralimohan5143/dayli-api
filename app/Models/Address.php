<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'label',
        'line1',
        'line2',
        'city',
        'state',
        'pincode',
        'lat',
        'lng',
        'nagar',
        'is_default',
        'zone_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'lat' => 'decimal:6',
        'lng' => 'decimal:6',
    ];

    public function addressable()
    {
        return $this->morphTo();
    }
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
