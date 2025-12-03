<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudInitScript extends Model
{
    protected $fillable = [
        'team_id',
        'name',
        'script',
    ];

    protected function casts(): array
    {
        return [
            'script' => 'encrypted',
        ];
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @param array<int, string> $select
     * @return \Illuminate\Database\Eloquent\Builder<CloudInitScript>
     */
    public static function ownedByCurrentTeam(array $select = ['*']): \Illuminate\Database\Eloquent\Builder
    {
        $selectArray = collect($select)->concat(['id']);

        return self::whereTeamId(currentTeam()->id)->select($selectArray->all());
    }
}
