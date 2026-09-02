<?php

namespace TakepartMedia\StatamicSop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An immutable snapshot of an SOP's text. Never updated, never deleted —
 * consents point here so the audit trail can reproduce the exact wording that
 * was agreed to.
 *
 * @property int $id
 * @property int $sop_id
 * @property int $version_no
 * @property string $title
 * @property string $content
 * @property string $content_hash
 * @property string|null $created_by
 */
class SopVersion extends Model
{
    protected $connection = 'sop';

    protected $table = 'sop_versions';

    /** Rows are immutable, so the table has no updated_at column. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'sop_id',
        'version_no',
        'title',
        'content',
        'content_hash',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sop_id' => 'integer',
            'version_no' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class, 'sop_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(SopConsent::class, 'sop_version_id');
    }

    /**
     * The change detector. A title change counts as a content change on
     * purpose: it is the simplest rule to defend when someone asks what
     * exactly a user agreed to.
     */
    public static function hash(string $title, string $content): string
    {
        return hash('sha256', $title."\0".$content);
    }
}
