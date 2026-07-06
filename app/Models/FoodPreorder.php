<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodPreorder extends Model
{
    protected $table = 'food_preorders';

    protected $fillable = [
        'food_menu_today_id',
        'customer_id',
        'qty',
        'status',
        'notes',
        'order_id',
    ];
}
