<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceFlowRegistry extends Model
{
    protected $table = 'service_flow_registry';

    protected $fillable = [
        'service_id',
        'flow_key',
        'title',
        'description',
        'request_schema',
        'response_schema',
        'ai_questions',
        'estimate_rules',
        'matching_rules',
        'version',
        'is_active',
    ];

    protected $casts = [
        'request_schema' => 'array',
        'response_schema' => 'array',
        'ai_questions' => 'array',
        'estimate_rules' => 'array',
        'matching_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(
            Service::class,
            'service_id',
            'service_id'
        );
    }
}
