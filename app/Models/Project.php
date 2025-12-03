<?php

namespace App\Models;

use App\Traits\ClearsGlobalSearchCache;
use App\Traits\HasSafeStringAttribute;
use OpenApi\Attributes as OA;
use Visus\Cuid2\Cuid2;

#[OA\Schema(
    description: 'Project model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer'],
        'uuid' => ['type' => 'string'],
        'name' => ['type' => 'string'],
        'description' => ['type' => 'string'],
        'environments' => new OA\Property(
            property: 'environments',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Environment'),
            description: 'The environments of the project.'
        ),
    ]
)]
/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property int $team_id
 * @property \Illuminate\Database\Eloquent\Collection<int, Environment> $environments
 * @property \Illuminate\Database\Eloquent\Collection<int, SharedEnvironmentVariable> $environment_variables
 * @property ProjectSetting|null $settings
 * @property Team $team
 * @property \Illuminate\Database\Eloquent\Collection<int, Service> $services
 * @property \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 */
class Project extends BaseModel
{
    use ClearsGlobalSearchCache;
    use HasSafeStringAttribute;

    protected $guarded = [];

    /**
     * @param array<int, string> $select
     * @return \Illuminate\Database\Eloquent\Builder<Project>
     */
    public static function ownedByCurrentTeam(array $select = ['*']): \Illuminate\Database\Eloquent\Builder
    {
        $selectArray = collect($select)->concat(['id']);

        return Project::whereTeamId(currentTeam()->id)->select($selectArray->all())->orderByRaw('LOWER(name)');
    }

    protected static function booted()
    {
        static::created(function ($project) {
            ProjectSetting::create([
                'project_id' => $project->id,
            ]);
            Environment::create([
                'name' => 'production',
                'project_id' => $project->id,
                'uuid' => (string) new Cuid2,
            ]);
        });
        static::deleting(function ($project) {
            $project->environments()->delete();
            $project->settings()->delete();
            $shared_variables = $project->environment_variables();
            foreach ($shared_variables as $shared_variable) {
                $shared_variable->delete();
            }
        });
    }

    public function environment_variables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SharedEnvironmentVariable::class);
    }

    public function environments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Environment::class);
    }

    public function settings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProjectSetting::class);
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Service::class, Environment::class);
    }

    public function applications(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Application::class, Environment::class);
    }

    public function postgresqls(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandalonePostgresql::class, Environment::class);
    }

    public function redis(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandaloneRedis::class, Environment::class);
    }

    public function keydbs(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandaloneKeydb::class, Environment::class);
    }

    public function dragonflies(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandaloneDragonfly::class, Environment::class);
    }

    public function clickhouses(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandaloneClickhouse::class, Environment::class);
    }

    public function mongodbs(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandaloneMongodb::class, Environment::class);
    }

    public function mysqls(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandaloneMysql::class, Environment::class);
    }

    public function mariadbs(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(StandaloneMariadb::class, Environment::class);
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

    public function databases()
    {
        return $this->postgresqls()->get()->merge($this->redis()->get())->merge($this->mongodbs()->get())->merge($this->mysqls()->get())->merge($this->mariadbs()->get())->merge($this->keydbs()->get())->merge($this->dragonflies()->get())->merge($this->clickhouses()->get());
    }

    public function navigateTo()
    {
        if ($this->environments->count() === 1) {
            return route('project.resource.index', [
                'project_uuid' => $this->uuid,
                'environment_uuid' => $this->environments->first()->uuid,
            ]);
        }

        return route('project.show', ['project_uuid' => $this->uuid]);
    }
}
