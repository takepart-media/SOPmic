<?php

namespace TakepartMedia\StatamicSop\Support;

use Statamic\Contracts\Auth\User;

/**
 * Decides who never sees the gate.
 *
 * Deliberately touches nothing but config and the user's own roles/groups,
 * which come from Statamic's flat files. Never the SOP database — the gate
 * calls this first so a broken database cannot lock out the people who would
 * have to fix it.
 */
class BypassResolver
{
    public function isBypassed(?User $user): bool
    {
        // A missing user is not "bypassed" — the gate lets anonymous requests
        // through on its own, and Statamic's auth middleware owns that case.
        if (! $user) {
            return false;
        }

        if ($user->isSuper()) {
            return true;
        }

        foreach ((array) config('sop.bypass.roles', []) as $handle) {
            if ($user->hasRole($handle)) {
                return true;
            }
        }

        foreach ((array) config('sop.bypass.groups', []) as $handle) {
            if ($user->isInGroup($handle)) {
                return true;
            }
        }

        return false;
    }
}
