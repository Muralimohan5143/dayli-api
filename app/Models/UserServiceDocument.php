<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserServiceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_service_id',
        'document_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'status',
        'remarks',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function userService()
    {
        return $this->belongsTo(UserService::class);
    }
}
