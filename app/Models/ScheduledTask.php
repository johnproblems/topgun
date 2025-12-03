<?php

namespace App\Models;

use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $frequency
 * @property string $command
 * @property bool $enabled
 * @property int|null $timeout
 * @property int|null $application_id
 * @property int|null $service_id
 * @property int|null $database_id
 * @property Application|null $application
 * @property Service|null $service
 * @property StandalonePostgresql|StandaloneRedis|StandaloneMongodb|StandaloneMysql|StandaloneMariadb|null $database
 * @property ScheduledTaskExecution|null $latest_log
 * @property \Illuminate\Database\Eloquent\Collection<int, ScheduledTaskExecution> $executions
 */
class ScheduledTask extends BaseModel
{
    use HasSafeStringAttribute;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'timeout' => 'integer',
        ];
    }

    public function service(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function application(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function latest_log(): HasOne
    {
        return $this->hasOne(ScheduledTaskExecution::class)->latest();
    }

    public function executions(): HasMany
    {
        // Last execution first
        return $this->hasMany(ScheduledTaskExecution::class)->orderBy('created_at', 'desc');
    }

    public function server()
    {
        if ($this->application) {
            if ($this->application->destination && $this->application->destination->server) {
                return $this->application->destination->server;
            }
        } elseif ($this->service) {
            if ($this->service->destination && $this->service->destination->server) {
                return $this->service->destination->server;
            }
        } elseif ($this->database) {
            if ($this->database->destination && $this->database->destination->server) {
                return $this->database->destination->server;
            }
        }

        return null;
    }
}
