<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'alternate_phone',
        'email',
        'lang_locale',
        'address1',
        'address2',
        'city',
        'state',
        'pincode',
        'lead_type',
        'zone',
        'source',
        'collected_by',
        'notes',
        'status',
        'follow_up_date',
        'collected_lat',
        'collected_lng',
    ];
}
