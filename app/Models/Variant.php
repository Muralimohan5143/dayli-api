<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Variant extends Model
{
    use HasFactory;

    protected $primaryKey = 'variant_id';
    public $incrementing = true;
    public $table = 'variants';

    protected $keyType = 'int';

    protected $fillable = [
        'variant_id',
        'product_id',
        'title',
        'sku',
        'barcode',
        'option1',
        'option2',
        'option3',
        'position',
        'currency',
        'price',
        'compare_at_price',
        'weight',
        'weight_unit',
        'taxable',
        'requires_shipping',
        'inventory_management',
        'inventory_policy',
        'inventory_quantity',
    ];


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function getRouteKeyName(): string
    {
        return 'variant_id';
    }
}
