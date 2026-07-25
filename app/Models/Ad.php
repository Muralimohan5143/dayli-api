<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ad extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'image_path',
        'placement',
        'action_type',
        'action_value',
        'button_text',
        'start_at',
        'end_at',
        'priority',
        'is_active',
        'impressions_count',
        'clicks_count',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'impressions_count' => 'integer',
        'clicks_count' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        if (
            str_starts_with($this->image_path, 'http://') ||
            str_starts_with($this->image_path, 'https://')
        ) {
            return $this->image_path;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) use ($now) {
                $query
                    ->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now) {
                $query
                    ->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            });
    }
}
