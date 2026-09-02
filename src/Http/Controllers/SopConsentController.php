<?php

namespace TakepartMedia\StatamicSop\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Markdown;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use TakepartMedia\StatamicSop\Exceptions\StaleConsentException;
use TakepartMedia\StatamicSop\Support\BypassResolver;
use TakepartMedia\StatamicSop\Support\ConsentRecorder;
use TakepartMedia\StatamicSop\Support\SopStatus;

/**
 * The one screen the gate lets through. It never trusts the client for which
 * SOP is next — the page always renders the first pending SOP, and the write
 * is re-verified against the database inside ConsentRecorder's transaction.
 */
class SopConsentController extends CpController
{
    public function show(Request $request, SopStatus $status, BypassResolver $bypass)
    {
        $user = User::current();

        // The gate exempts this route by name, so anyone with `access cp` can
        // land here — including bypassed users, who do have pending SOPs on
        // paper. Sending them on beats asking them to sign something the gate
        // was never going to enforce.
        if ($bypass->isBypassed($user)) {
            return redirect()->intended(cp_route('index'));
        }

        $pending = $status->pendingFor($user);

        if ($pending->isEmpty()) {
            return redirect()->intended(cp_route('index'));
        }

        $sop = $pending->first();
        $version = $sop->currentVersion;
        $total = $status->totalRequiredCount();

        return view('sop::consent', [
            'sop' => $sop,
            'version' => $version,
            // Counted from the back: pending shrinks as consents land, so the
            // position stays correct without storing progress anywhere.
            'position' => $total - $pending->count() + 1,
            'total' => $total,
            'content' => Markdown::parse((string) $version->content),
        ]);
    }

    public function store(Request $request, ConsentRecorder $recorder, SopStatus $status)
    {
        $validated = $request->validate([
            'sop_id' => ['required', 'integer'],
            'sop_version_id' => ['required', 'integer'],
            'confirmed' => ['required', 'accepted'],
        ], [], [
            'confirmed' => __('sop::messages.validation.attributes.confirmed'),
        ]);

        $user = User::current();

        try {
            $recorder->record(
                $user,
                (int) $validated['sop_id'],
                (int) $validated['sop_version_id'],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (StaleConsentException) {
            // The SOP moved on, or the payload pointed at something that was
            // never next. Same answer either way: re-render the real next one.
            $status->forget();

            return redirect(cp_route('sop.consent'))
                ->with('error', __('sop::messages.consent.stale'));
        }

        // The memo was taken before the write and is now a lie.
        $status->forget();

        if ($status->pendingFor($user)->isNotEmpty()) {
            return redirect(cp_route('sop.consent'))
                ->with('success', __('sop::messages.consent.recorded'));
        }

        return redirect()->intended(cp_route('index'));
    }
}
