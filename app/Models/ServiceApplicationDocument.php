<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceApplicationDocument extends Model
{
    use HasFactory;

    protected $table = 'service_application_documents';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
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
        'file_size' => 'integer',
        'meta' => 'array',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }
}
