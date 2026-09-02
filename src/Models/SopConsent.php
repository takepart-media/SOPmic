<?php

namespace TakepartMedia\StatamicSop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $user_id
 * @property int $sop_id
 * @property int $sop_version_id
 * @property string|null $ip
 * @property string|null $user_agent
 */
class SopConsent extends Model
{
    protected $connection = 'sop';

    protected $table = 'sop_consents';

    /** Consents are append-only, so the table has no updated_at column. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'sop_id',
        'sop_version_id',
        'ip',
        'user_agent',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'sop_id' => 'integer',
            'sop_version_id' => 'integer',
            'consented_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class, 'sop_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SopVersion::class, 'sop_version_id');
    }
}
