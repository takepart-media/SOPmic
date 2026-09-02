<?php

namespace TakepartMedia\StatamicSop\Exceptions;

use RuntimeException;

/**
 * Thrown when a consent write does not match the user's actual next open SOP:
 * a skipped SOP, a superseded version, or an id that was never pending.
 */
class StaleConsentException extends RuntimeException
{
    public static function notPending(int $sopId): self
    {
        return new self("SOP [{$sopId}] is not the next SOP awaiting this user's consent.");
    }

    public static function staleVersion(int $sopId, int $sopVersionId): self
    {
        return new self("Version [{$sopVersionId}] is no longer the current version of SOP [{$sopId}].");
    }
}
