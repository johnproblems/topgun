<?php

namespace App\Models;

class GitlabApp extends BaseModel
{
    protected $hidden = [
        'webhook_token',
        'app_secret',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Builder<GitlabApp>
     */
    public static function ownedByCurrentTeam(): \Illuminate\Database\Eloquent\Builder
    {
        return GitlabApp::whereTeamId(currentTeam()->id);
    }

    public function applications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Application::class, 'source');
    }

    public function privateKey(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrivateKey::class);
    }
}
