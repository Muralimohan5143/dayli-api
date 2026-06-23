<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\Variant;
use App\Models\User;

class FoodMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'chef_id',
        'zone_id',
        'menu_date',
        'meal_type',

        // NEW
        'product_id',
        'variant_id',

        'item_name',
        'description',
        'price',
        'available_qty',
        'is_veg',
        'cutoff_time',
        'is_active',
    ];

    protected $casts = [
        'menu_date' => 'date',
        'is_veg' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function chef()
    {
        return $this->belongsTo(User::class, 'chef_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class, 'variant_id', 'variant_id');
    }
}
