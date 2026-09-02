<?php

namespace TakepartMedia\StatamicSop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;
use TakepartMedia\StatamicSop\Support\BypassResolver;
use TakepartMedia\StatamicSop\Support\SopStatus;
use Throwable;

/**
 * The gate. Prepended to `statamic.cp.authenticated`, so it runs before every
 * other middleware in that group — including Statamic's own Authorize and
 * anything other addons push.
 *
 * The order of the checks below is the contract, not a style choice: steps 1–4
 * touch nothing but config and the user's flat-file roles, so the SOP database
 * is only ever consulted for users who could actually be blocked by it. A
 * broken database therefore cannot lock out a super admin.
 */
class EnforceSopConsent
{
    public function __construct(
        private readonly BypassResolver $bypass,
        private readonly SopStatus $status,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sop.enabled')) {
            return $next($request);
        }

        $user = User::current();

        if (! $user) {
            return $next($request);
        }

        // Running before Statamic's Authorize means we see requests it would
        // reject. Waving those through keeps its 403 authoritative instead of
        // answering an unauthorised request with our consent screen.
        if ($user->cant('access cp')) {
            return $next($request);
        }

        if ($this->bypass->isBypassed($user)) {
            return $next($request);
        }

        if ($this->isConsentRoute($request)) {
            return $next($request);
        }

        try {
            $pending = $this->status->pendingFor($user)->isNotEmpty();
        } catch (Throwable $e) {
            report($e);

            return $this->unavailable();
        }

        if (! $pending) {
            return $next($request);
        }

        return $this->block($request);
    }

    private function isConsentRoute(Request $request): bool
    {
        return $request->routeIs('statamic.cp.sop.consent', 'statamic.cp.sop.consent.store');
    }

    private function block(Request $request): Response
    {
        $url = cp_route('sop.consent');

        // Inertia drives every core CP screen. A plain redirect would be
        // followed by XHR and swallowed, so Inertia gets its own 409 +
        // X-Inertia-Location, which the client turns into a real visit.
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('sop::messages.gate.required'),
                'redirect' => $url,
            ], 423);
        }

        // Only a GET is worth coming back to; replaying a POST after the
        // consent flow would be a surprise, not a convenience.
        if ($request->isMethod('GET')) {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return redirect($url);
    }

    /**
     * The database is down and this user has no bypass. The view is standalone
     * on purpose — the CP layout pulls in preferences, sites and permissions,
     * any of which may be the thing that just failed.
     */
    private function unavailable(): Response
    {
        return response()->view('sop::unavailable', [], 503);
    }
}
