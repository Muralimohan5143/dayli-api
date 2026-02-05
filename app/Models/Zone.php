<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $table = 'zones'; // your table is singular
    protected $guarded = [];   // keep your global guarded style

    
    public function pincodes()
    {
        return $this->hasMany(ZonePincode::class);
    }

    public static function findByPinCode(string $pincode): ?self
    {
        return ZonePincode::where('pin_code', $pincode)->with('zone')->first()?->zone;
    }
}
