<?php

namespace TakepartMedia\StatamicSop\Support;

use Illuminate\Support\Collection;
use Statamic\Contracts\Auth\User;
use TakepartMedia\StatamicSop\Models\Sop;

/**
 * Which SOPs a user still has to read, computed statelessly from the database
 * on every request. No session flags, no cross-request cache — the answer must
 * change the moment an SOP is edited.
 *
 * Registered as a scoped binding: the gate and the consent controller both ask
 * within one request, so the query is memoised per user id for the lifetime of
 * the instance. Call forget() after writing a consent.
 */
class SopStatus
{
    /** @var array<string, Collection<int, Sop>> */
    private array $pending = [];

    private ?int $total = null;

    /**
     * @return Collection<int, Sop>
     */
    public function pendingFor(?User $user): Collection
    {
        if (! $userId = $this->userId($user)) {
            return collect();
        }

        return $this->pending[$userId] ??= $this->freshPendingForUserId($userId);
    }

    public function firstPendingFor(?User $user): ?Sop
    {
        return $this->pendingFor($user)->first();
    }

    /**
     * Every SOP the gate enforces, consented or not — the denominator of
     * "SOP x of y".
     */
    public function totalRequiredCount(): int
    {
        return $this->total ??= Sop::query()->active()->count();
    }

    /**
     * Bypasses the memo. Used inside the consent transaction, where a value
     * read before the write would defeat the point.
     *
     * @return Collection<int, Sop>
     */
    public function freshPendingForUserId(string $userId): Collection
    {
        return Sop::query()
            ->active()
            ->with('currentVersion')
            ->whereNotExists(function ($query) use ($userId) {
                $query->selectRaw('1')
                    ->from('sop_consents')
                    ->whereColumn('sop_consents.sop_version_id', 'sops.current_version_id')
                    ->where('sop_consents.user_id', $userId);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function forget(): void
    {
        $this->pending = [];
        $this->total = null;
    }

    private function userId(?User $user): ?string
    {
        $id = $user?->id();

        return ($id === null || $id === '') ? null : (string) $id;
    }
}
