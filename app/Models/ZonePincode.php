<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZonePincode extends Model
{

    protected $table = 'zone_pincodes';
    protected $fillable = ['zone_id', 'pin_code'];
    protected $guarded = [];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
