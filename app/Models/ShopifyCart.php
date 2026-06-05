<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyCart extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'shopify_cart_gid',
        'checkout_url',
        'currency_code',
        'subtotal',
        'total',
        'total_tax',
        'status',
        'raw_shopify_json',
    ];

    protected $casts = [
        'raw_shopify_json' => 'array',
    ];

    public function lines()
    {
        return $this->hasMany(ShopifyCartLine::class);
    }
}
