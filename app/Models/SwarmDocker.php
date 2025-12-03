<?php

namespace App\Models;

class SwarmDocker extends BaseModel
{
    protected $guarded = [];

    public function applications(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(Application::class, 'destination');
    }

    public function postgresqls(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandalonePostgresql::class, 'destination');
    }

    public function redis(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandaloneRedis::class, 'destination');
    }

    public function keydbs(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandaloneKeydb::class, 'destination');
    }

    public function dragonflies(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandaloneDragonfly::class, 'destination');
    }

    public function clickhouses(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandaloneClickhouse::class, 'destination');
    }

    public function mongodbs(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandaloneMongodb::class, 'destination');
    }

    public function mysqls(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandaloneMysql::class, 'destination');
    }

    public function mariadbs(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(StandaloneMariadb::class, 'destination');
    }

    public function server(): \Illuminate\Database\Eloquent\Relations\BelongsTo()
    {
        return $this->belongsTo(Server::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\MorphMany()
    {
        return $this->morphMany(Service::class, 'destination');
    }

    public function databases()
    {
        $postgresqls = $this->postgresqls;
        $redis = $this->redis;
        $mongodbs = $this->mongodbs;
        $mysqls = $this->mysqls;
        $mariadbs = $this->mariadbs;
        $keydbs = $this->keydbs;
        $dragonflies = $this->dragonflies;
        $clickhouses = $this->clickhouses;

        return $postgresqls->concat($redis)->concat($mongodbs)->concat($mysqls)->concat($mariadbs)->concat($keydbs)->concat($dragonflies)->concat($clickhouses);
    }

    public function attachedTo()
    {
        return $this->applications?->count() > 0 || $this->databases()->count() > 0;
    }
}
