<?php

namespace TakepartMedia\StatamicSop\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Markdown;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
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
        return view('sop::create', $this->exclusionChoices());
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

        // Stored values are IDs/handles; resolve them for display and fall
        // back to the raw value when the user, role or group no longer exists.
        $excludedUsers = collect($sop->excluded_users ?? [])
            ->map(fn (string $userId) => User::find($userId)?->email() ?? $userId)
            ->all();
        $excludedRoles = collect($sop->excluded_roles ?? [])
            ->map(fn (string $handle) => Role::find($handle)?->title() ?? $handle)
            ->all();
        $excludedGroups = collect($sop->excluded_groups ?? [])
            ->map(fn (string $handle) => UserGroup::find($handle)?->title() ?? $handle)
            ->all();

        return view('sop::show', [
            'sop' => $sop,
            'versions' => $versions,
            'consentsByVersion' => $consentsByVersion,
            'emails' => $emails,
            'content' => $content,
            'excludedUsers' => $excludedUsers,
            'excludedRoles' => $excludedRoles,
            'excludedGroups' => $excludedGroups,
        ]);
    }

    public function edit(Sop $sop)
    {
        $sop->load('currentVersion');

        return view('sop::edit', ['sop' => $sop, ...$this->exclusionChoices()]);
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
            'excluded_users' => ['nullable', 'array'],
            'excluded_users.*' => ['string'],
            'excluded_roles' => ['nullable', 'array'],
            'excluded_roles.*' => ['string'],
            'excluded_groups' => ['nullable', 'array'],
            'excluded_groups.*' => ['string'],
        ], [], [
            'title' => __('sop::messages.validation.attributes.title'),
            'content' => __('sop::messages.validation.attributes.content'),
            'sort_order' => __('sop::messages.validation.attributes.sort_order'),
            'excluded_users' => __('sop::messages.validation.attributes.excluded_users'),
            'excluded_users.*' => __('sop::messages.validation.attributes.excluded_users'),
            'excluded_roles' => __('sop::messages.validation.attributes.excluded_roles'),
            'excluded_roles.*' => __('sop::messages.validation.attributes.excluded_roles'),
            'excluded_groups' => __('sop::messages.validation.attributes.excluded_groups'),
            'excluded_groups.*' => __('sop::messages.validation.attributes.excluded_groups'),
        ]);

        $data['active'] = $request->boolean('active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // Same trap as the checkbox: a multi-select with nothing selected sends
        // no key at all, which must mean "cleared", not "leave untouched".
        // Anything that is not a currently existing user/role/group is dropped
        // silently — a stale or tampered value must not end up stored.
        $choices = $this->exclusionChoices();

        $data['excluded_users'] = array_values(array_intersect(
            (array) $request->input('excluded_users', []),
            array_map('strval', array_keys($choices['users']))
        ));
        $data['excluded_roles'] = array_values(array_intersect(
            (array) $request->input('excluded_roles', []),
            array_map('strval', array_keys($choices['roles']))
        ));
        $data['excluded_groups'] = array_values(array_intersect(
            (array) $request->input('excluded_groups', []),
            array_map('strval', array_keys($choices['groups']))
        ));

        return $data;
    }

    /**
     * What the exclusion multi-selects offer (and, equally, the only values the
     * server accepts): users as id => email, roles and groups as handle => title.
     *
     * @return array{users: array<string, string>, roles: array<string, string>, groups: array<string, string>}
     */
    private function exclusionChoices(): array
    {
        return [
            'users' => User::all()
                ->mapWithKeys(fn ($user) => [(string) $user->id() => (string) $user->email()])
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->all(),
            'roles' => Role::all()
                ->mapWithKeys(fn ($role) => [(string) $role->handle() => (string) $role->title()])
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->all(),
            'groups' => UserGroup::all()
                ->mapWithKeys(fn ($group) => [(string) $group->handle() => (string) $group->title()])
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->all(),
        ];
    }
}
