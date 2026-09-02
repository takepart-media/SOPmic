<?php

namespace TakepartMedia\StatamicSop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property bool $active
 * @property int $sort_order
 * @property int|null $current_version_id
 */
class Sop extends Model
{
    use SoftDeletes;

    protected $connection = 'sop';

    protected $table = 'sops';

    protected $fillable = [
        'title',
        'active',
        'sort_order',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'current_version_id' => 'integer',
        ];
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SopVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SopVersion::class, 'sop_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(SopConsent::class, 'sop_id');
    }

    /**
     * SOPs the gate actually enforces: switched on and holding a published
     * version. Soft deletes are already excluded by the global scope.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->whereNotNull('current_version_id');
    }
}
