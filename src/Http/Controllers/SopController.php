<?php

namespace TakepartMedia\StatamicSop\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Markdown;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use TakepartMedia\StatamicSop\Models\Sop;
use TakepartMedia\StatamicSop\Models\SopConsent;
use TakepartMedia\StatamicSop\Support\SopPublisher;

/**
 * SOP management CRUD. The routes are wrapped in `can:manage sops`, so
 * authorization is already settled before any of this runs. Every write goes
 * through SopPublisher — nothing here touches sop_versions directly, which is
 * what keeps the audit trail honest.
 */
class SopController extends CpController
{
    public function index()
    {
        // withCount rather than a per-row query: one extra correlated
        // subquery gets every row's consent count for its *current* version in
        // the same round trip as the listing itself.
        $sops = Sop::query()
            ->with('currentVersion')
            ->withCount(['consents as consent_count' => function ($query) {
                $query->whereColumn('sop_consents.sop_version_id', 'sops.current_version_id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('sop::index', ['sops' => $sops]);
    }

    public function create()
    {
        return view('sop::create');
    }

    public function store(Request $request, SopPublisher $publisher)
    {
        $publisher->create($this->validated($request), User::current()?->id());

        return redirect(cp_route('sop.index'))
            ->with('success', __('sop::messages.crud.created'));
    }

    public function show(Sop $sop)
    {
        $sop->load('currentVersion');

        $versions = $sop->versions()->orderByDesc('version_no')->get();

        $consentsByVersion = SopConsent::query()
            ->where('sop_id', $sop->id)
            ->orderByDesc('consented_at')
            ->get()
            ->groupBy('sop_version_id');

        // Resolved once for every user who shows up in the audit trail —
        // consenting users and version authors alike — rather than per row.
        $emails = SopConsent::query()
            ->where('sop_id', $sop->id)
            ->pluck('user_id')
            ->merge($versions->pluck('created_by')->filter())
            ->unique()
            ->mapWithKeys(fn (string $userId) => [$userId => User::find($userId)?->email() ?? $userId]);

        $content = $sop->currentVersion
            ? Markdown::parse((string) $sop->currentVersion->content)
            : null;

        return view('sop::show', [
            'sop' => $sop,
            'versions' => $versions,
            'consentsByVersion' => $consentsByVersion,
            'emails' => $emails,
            'content' => $content,
        ]);
    }

    public function edit(Sop $sop)
    {
        $sop->load('currentVersion');

        return view('sop::edit', ['sop' => $sop]);
    }

    public function update(Request $request, Sop $sop, SopPublisher $publisher)
    {
        $publisher->update($sop, $this->validated($request), User::current()?->id());

        return redirect(cp_route('sop.index'))
            ->with('success', __('sop::messages.crud.updated'));
    }

    public function destroy(Sop $sop)
    {
        // Soft delete only — versions and consents are the audit trail and are
        // never touched here.
        $sop->delete();

        return redirect(cp_route('sop.index'))
            ->with('success', __('sop::messages.crud.deleted'));
    }

    /**
     * `active` is a checkbox: an unchecked box sends no key at all, so
     * `$request->validate()` would silently omit it and SopPublisher::update()
     * would then leave whatever the flag already was untouched. Reading it via
     * `boolean()` instead guarantees every save states it explicitly.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'title' => __('sop::messages.validation.attributes.title'),
            'content' => __('sop::messages.validation.attributes.content'),
            'sort_order' => __('sop::messages.validation.attributes.sort_order'),
        ]);

        $data['active'] = $request->boolean('active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
