<?php

namespace TakepartMedia\StatamicSop\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Statamic\Contracts\Auth\User;
use TakepartMedia\StatamicSop\Exceptions\StaleConsentException;
use TakepartMedia\StatamicSop\Models\SopConsent;

/**
 * Writes a consent row, and refuses to write the wrong one.
 *
 * The sequence is enforced here rather than in the controller: everything the
 * client sends is a claim, and the only trustworthy check is one made against
 * the database inside the same transaction as the insert.
 *
 * The caller must call SopStatus::forget() afterwards — the memo it holds is
 * stale the moment this returns.
 */
class ConsentRecorder
{
    public function __construct(private readonly SopStatus $status) {}

    public function record(
        ?User $user,
        int $sopId,
        int $sopVersionId,
        ?string $ip = null,
        ?string $userAgent = null,
    ): bool {
        // Never from a payload. The gate only ever routes authenticated users
        // here, so a missing user is a programming error, not a stale request.
        $userId = (string) ($user?->id() ?? '');

        if ($userId === '') {
            throw new InvalidArgumentException('A consent needs an authenticated user.');
        }

        // IP and user agent are personal data (GDPR); the consent is already
        // proven by user + version + timestamp, so both are dropped here
        // unless the project explicitly opts in — regardless of the caller.
        if (! config('sop.audit.ip')) {
            $ip = null;
        }

        if (! config('sop.audit.user_agent')) {
            $userAgent = null;
        }

        return DB::connection('sop')->transaction(function () use ($userId, $sopId, $sopVersionId, $ip, $userAgent) {
            $existing = SopConsent::query()
                ->where('user_id', $userId)
                ->where('sop_version_id', $sopVersionId)
                ->exists();

            // Already on file. Reporting success keeps reloads, back buttons
            // and parallel tabs harmless — and stops the pending check below
            // from mistaking a replay for a skip attempt.
            if ($existing) {
                return true;
            }

            $pending = $this->status->freshPendingForUserId($userId)->first();

            if (! $pending || $pending->id !== $sopId) {
                throw StaleConsentException::notPending($sopId);
            }

            if ($pending->current_version_id !== $sopVersionId) {
                throw StaleConsentException::staleVersion($sopId, $sopVersionId);
            }

            try {
                SopConsent::create([
                    'user_id' => $userId,
                    'sop_id' => $sopId,
                    'sop_version_id' => $sopVersionId,
                    'ip' => $ip,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
                    'consented_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Two concurrent requests raced past the existence check above.
                // The unique index settled it; both callers are equally right.
            }

            return true;
        });
    }
}
