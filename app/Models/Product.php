<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;


    protected $keyType = 'int';

    protected $primaryKey = 'product_id';
    public $incrementing = true;
    protected $table = 'products';

    protected $fillable = ['product_id', 'title', 'vendor', 'product_type', 'handle', 'tags', 'status', 'img_src'];

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class, 'product_id', 'product_id');
    }
}
