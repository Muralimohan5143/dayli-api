<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyDayFeedItem extends Model
{
    protected $fillable = [
        'user_id',
        'interest_key',
        'title',
        'subtitle',
        'body',
        'image_url',
        'source_name',
        'source_url',
        'payload_json',
        'feed_date',
        'sort_order',
        'is_read',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'feed_date'    => 'date',
        'is_read'      => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
