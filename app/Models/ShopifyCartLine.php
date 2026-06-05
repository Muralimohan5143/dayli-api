<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyCartLine extends Model
{
    protected $fillable = [
        'shopify_cart_id',
        'shopify_line_gid',
        'product_id',
        'variant_id',
        'shopify_product_gid',
        'shopify_variant_gid',
        'title',
        'variant_title',
        'qty',
        'unit_price',
        'line_total',
        'raw_shopify_json',
    ];

    protected $casts = [
        'raw_shopify_json' => 'array',
    ];

    public function cart()
    {
        return $this->belongsTo(ShopifyCart::class, 'shopify_cart_id');
    }
}
