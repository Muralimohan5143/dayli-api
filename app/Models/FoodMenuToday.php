<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodMenuToday extends Model
{
    protected $table = 'food_menu_today';

    protected $fillable = [
        'chef_id',
        'zone_id',
        'menu_date',
        'meal_type',
        'food_menu_id',
        'product_id',
        'variant_id',
        'planned_qty',
        'available_qty',
        'cutoff_time',
        'broadcast_status',
        'status',
        'special_note',
        'is_active',
    ];
}
