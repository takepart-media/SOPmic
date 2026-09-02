<?php

namespace TakepartMedia\StatamicSop\Support;

use Illuminate\Support\Facades\DB;
use TakepartMedia\StatamicSop\Models\Sop;
use TakepartMedia\StatamicSop\Models\SopVersion;

/**
 * The only sanctioned way to write an SOP. Every published state becomes an
 * immutable sop_versions row; consents point at those rows, so the audit trail
 * survives later edits.
 */
class SopPublisher
{
    public function create(array $data, ?string $userId = null): Sop
    {
        $title = (string) ($data['title'] ?? '');
        $content = (string) ($data['content'] ?? '');

        return DB::connection('sop')->transaction(function () use ($data, $title, $content, $userId) {
            $sop = Sop::create([
                'title' => $title,
                'active' => (bool) ($data['active'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $version = $this->writeVersion($sop, 1, $title, $content, $userId);

            // Second write rather than one insert: the version needs the sop id,
            // and the sop needs the version id. No DB foreign key on
            // current_version_id for exactly this reason.
            $sop->current_version_id = $version->id;
            $sop->save();

            return $sop->setRelation('currentVersion', $version);
        });
    }

    public function update(Sop $sop, array $data, ?string $userId = null): Sop
    {
        return DB::connection('sop')->transaction(function () use ($sop, $data, $userId) {
            $current = $sop->currentVersion;

            $title = array_key_exists('title', $data)
                ? (string) $data['title']
                : (string) ($current->title ?? $sop->title);

            $content = array_key_exists('content', $data)
                ? (string) $data['content']
                : (string) ($current->content ?? '');

            if (array_key_exists('active', $data)) {
                $sop->active = (bool) $data['active'];
            }

            if (array_key_exists('sort_order', $data)) {
                $sop->sort_order = (int) $data['sort_order'];
            }

            $hash = SopVersion::hash($title, $content);

            // An unchanged hash means neither title nor content moved, so only
            // active/sort_order can have changed — nothing to re-consent to.
            if (! $current || $current->content_hash !== $hash) {
                $version = $this->writeVersion(
                    $sop,
                    ((int) $sop->versions()->max('version_no')) + 1,
                    $title,
                    $content,
                    $userId
                );

                $sop->current_version_id = $version->id;
                $sop->title = $title;
                $sop->setRelation('currentVersion', $version);
            }

            $sop->save();

            return $sop;
        });
    }

    private function writeVersion(Sop $sop, int $versionNo, string $title, string $content, ?string $userId): SopVersion
    {
        return $sop->versions()->create([
            'version_no' => $versionNo,
            'title' => $title,
            'content' => $content,
            'content_hash' => SopVersion::hash($title, $content),
            'created_by' => $userId,
        ]);
    }
}
