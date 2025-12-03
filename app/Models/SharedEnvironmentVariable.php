<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedEnvironmentVariable extends Model
{
    protected $guarded = [];

    protected $casts = [
        'key' => 'string',
        'value' => 'encrypted',
    ];

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo()
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo()
    {
        return $this->belongsTo(Project::class);
    }

    public function environment(): \Illuminate\Database\Eloquent\Relations\BelongsTo()
    {
        return $this->belongsTo(Environment::class);
    }
}
