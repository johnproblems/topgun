<?php

namespace App\Models;

use App\Traits\ClearsGlobalSearchCache;
use App\Traits\HasSafeStringAttribute;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Environment model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string'],
        'project_id' => ['type' => 'integer'],
        'created_at' => ['type' => 'string'],
        'updated_at' => ['type' => 'string'],
        'description' => ['type' => 'string'],
    ]
)]
/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int $project_id
 * @property Project $project
 * @property \Illuminate\Database\Eloquent\Collection<int, SharedEnvironmentVariable> $environment_variables
 * @property \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 * @property \Illuminate\Database\Eloquent\Collection<int, StandalonePostgresql> $postgresqls
 * @property \Illuminate\Database\Eloquent\Collection<int, StandaloneRedis> $redis
 * @property \Illuminate\Database\Eloquent\Collection<int, StandaloneMongodb> $mongodbs
 * @property \Illuminate\Database\Eloquent\Collection<int, StandaloneMysql> $mysqls
 * @property \Illuminate\Database\Eloquent\Collection<int, StandaloneMariadb> $mariadbs
 * @property \Illuminate\Database\Eloquent\Collection<int, StandaloneKeydb> $keydbs
 * @property \Illuminate\Database\Eloquent\Collection<int, StandaloneDragonfly> $dragonflies
 * @property \Illuminate\Database\Eloquent\Collection<int, StandaloneClickhouse> $clickhouses
 * @property \Illuminate\Database\Eloquent\Collection<int, Service> $services
 */
class Environment extends BaseModel
{
    use ClearsGlobalSearchCache;
    use HasSafeStringAttribute;

    protected $guarded = [];

    protected static function booted()
    {
        static::deleting(function ($environment) {
            $shared_variables = $environment->environment_variables();
            foreach ($shared_variables as $shared_variable) {
                $shared_variable->delete();
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Environment>
     */
    public static function ownedByCurrentTeam(): \Illuminate\Database\Eloquent\Builder
    {
        return Environment::whereRelation('project.team', 'id', currentTeam()->id)->orderBy('name');
    }

    public function isEmpty()
    {
        return $this->applications()->count() == 0 &&
            $this->redis()->count() == 0 &&
            $this->postgresqls()->count() == 0 &&
            $this->mysqls()->count() == 0 &&
            $this->keydbs()->count() == 0 &&
            $this->dragonflies()->count() == 0 &&
            $this->clickhouses()->count() == 0 &&
            $this->mariadbs()->count() == 0 &&
            $this->mongodbs()->count() == 0 &&
            $this->services()->count() == 0;
    }

    public function environment_variables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SharedEnvironmentVariable::class);
    }

    public function applications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function postgresqls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandalonePostgresql::class);
    }

    public function redis(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandaloneRedis::class);
    }

    public function mongodbs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandaloneMongodb::class);
    }

    public function mysqls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandaloneMysql::class);
    }

    public function mariadbs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandaloneMariadb::class);
    }

    public function keydbs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandaloneKeydb::class);
    }

    public function dragonflies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandaloneDragonfly::class);
    }

    public function clickhouses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StandaloneClickhouse::class);
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

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Service::class);
    }

    protected function customizeName($value)
    {
        return str($value)->lower()->trim()->replace('/', '-')->toString();
    }
}
