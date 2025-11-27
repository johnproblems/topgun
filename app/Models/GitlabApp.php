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

    public function applications()
    {
        return $this->morphMany(Application::class, 'source');
    }

    public function privateKey()
    {
        return $this->belongsTo(PrivateKey::class);
    }
}
