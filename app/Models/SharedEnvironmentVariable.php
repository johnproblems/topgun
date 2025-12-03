<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property int|null $team_id
 * @property int|null $project_id
 * @property int|null $environment_id
 * @property Team|null $team
 * @property Project|null $project
 * @property Environment|null $environment
 */
class SharedEnvironmentVariable extends Model
{
    protected $guarded = [];

    protected $casts = [
        'key' => 'string',
        'value' => 'encrypted',
    ];

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function environment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
