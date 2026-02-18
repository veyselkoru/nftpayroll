<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'integration_connection_id',
        'triggered_by_user_id',
        'status',
        'endpoint',
        'payload',
        'response_body',
        'http_status',
        'error_message',
    ];
}
