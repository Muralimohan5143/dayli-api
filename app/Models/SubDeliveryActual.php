<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubDeliveryActual extends Model
{
    use HasFactory;

    protected $table = 'sub_delivery_actuals';

    protected $fillable = [
        'for_user_id',
        'by_user_id',
        'from_id',
        'product_id',
        'product_count',
        'status',
        // 'vendor_id',
        // 'draft_order_item_id',
        // 'deliver_date',
        // 'qty_actual',
        // 'unit',
        // 'notes',
        // 'order_id', // optional if you already have orders tying in later
    ];



    // protected $casts = [
    //     'deliver_date' => 'date',
    //     'qty_actual' => 'decimal:2',
    // ];




    public function customer()
    {
        return $this->belongsTo(User::class, 'for_user_id');
    }

    public function deliveryBoy()
    {
        return $this->belongsTo(User::class, 'by_user_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'from_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
