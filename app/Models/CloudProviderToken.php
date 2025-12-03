<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudProviderToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'token' => 'encrypted',
    ];

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo()
    {
        return $this->belongsTo(Team::class);
    }

    public function servers(): \Illuminate\Database\Eloquent\Relations\HasMany()
    {
        return $this->hasMany(Server::class);
    }

    public function hasServers(): bool
    {
        return $this->servers()->exists();
    }

    /**
     * @param array<int, string> $select
     * @return \Illuminate\Database\Eloquent\Builder<CloudProviderToken>
     */
    public static function ownedByCurrentTeam(array $select = ['*']): \Illuminate\Database\Eloquent\Builder
    {
        $selectArray = collect($select)->concat(['id']);

        return self::whereTeamId(currentTeam()->id)->select($selectArray->all());
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
