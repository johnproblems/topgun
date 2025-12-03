<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SslCertificate extends Model
{
    protected $fillable = [
        'ssl_certificate',
        'ssl_private_key',
        'configuration_dir',
        'mount_path',
        'resource_type',
        'resource_id',
        'server_id',
        'common_name',
        'subject_alternative_names',
        'valid_until',
        'is_ca_certificate',
    ];

    protected $casts = [
        'ssl_certificate' => 'encrypted',
        'ssl_private_key' => 'encrypted',
        'subject_alternative_names' => 'array',
        'valid_until' => 'datetime',
    ];

    public function application(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('resource');
    }

    public function service(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('resource');
    }

    public function database(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('resource');
    }

    public function server(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
