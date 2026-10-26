<?php

namespace TakepartMedia\StatamicSop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Statamic\Contracts\Auth\User;

/**
 * @property int $id
 * @property string $title
 * @property bool $active
 * @property int $sort_order
 * @property int|null $current_version_id
 * @property array<int, string>|null $excluded_users
 * @property array<int, string>|null $excluded_roles
 * @property array<int, string>|null $excluded_groups
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
        'excluded_users',
        'excluded_roles',
        'excluded_groups',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'current_version_id' => 'integer',
            'excluded_users' => 'array',
            'excluded_roles' => 'array',
            'excluded_groups' => 'array',
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

    /**
     * Whether this SOP is waived for the user. Touches nothing but the row
     * already loaded and the user's flat-file roles/groups, like
     * BypassResolver — hasRole() also covers roles inherited via a group.
     */
    public function isExcludedFor(User $user): bool
    {
        if (in_array((string) $user->id(), array_map('strval', $this->excluded_users ?? []), true)) {
            return true;
        }

        foreach ($this->excluded_roles ?? [] as $handle) {
            if ($user->hasRole($handle)) {
                return true;
            }
        }

        foreach ($this->excluded_groups ?? [] as $handle) {
            if ($user->isInGroup($handle)) {
                return true;
            }
        }

        return false;
    }
}
