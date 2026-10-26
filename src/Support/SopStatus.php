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

    /** @var array<string, int> */
    private array $required = [];

    /**
     * @return Collection<int, Sop>
     */
    public function pendingFor(?User $user): Collection
    {
        if (! $userId = $this->userId($user)) {
            return collect();
        }

        return $this->pending[$userId] ??= $this->freshPendingFor($user);
    }

    public function firstPendingFor(?User $user): ?Sop
    {
        return $this->pendingFor($user)->first();
    }

    /**
     * Every SOP the gate enforces for this user, consented or not — the
     * denominator of "SOP x of y". Per user, because exclusions make the set
     * differ from one user to the next.
     */
    public function requiredCountFor(?User $user): int
    {
        if (! $userId = $this->userId($user)) {
            return 0;
        }

        return $this->required[$userId] ??= Sop::query()
            ->active()
            ->get()
            ->reject(fn (Sop $sop) => $sop->isExcludedFor($user))
            ->count();
    }

    /**
     * Bypasses the memo. Used inside the consent transaction, where a value
     * read before the write would defeat the point.
     *
     * Exclusions are applied in PHP: roles and groups live in Statamic's flat
     * files, out of reach of the query.
     *
     * @return Collection<int, Sop>
     */
    public function freshPendingFor(User $user): Collection
    {
        $userId = (string) $user->id();

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
            ->get()
            ->reject(fn (Sop $sop) => $sop->isExcludedFor($user))
            ->values();
    }

    public function forget(): void
    {
        $this->pending = [];
        $this->required = [];
    }

    private function userId(?User $user): ?string
    {
        $id = $user?->id();

        return ($id === null || $id === '') ? null : (string) $id;
    }
}
