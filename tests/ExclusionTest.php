<?php

namespace TakepartMedia\StatamicSop\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use TakepartMedia\StatamicSop\Exceptions\StaleConsentException;
use TakepartMedia\StatamicSop\Models\Sop;
use TakepartMedia\StatamicSop\Models\SopConsent;
use TakepartMedia\StatamicSop\Models\SopVersion;
use TakepartMedia\StatamicSop\Support\ConsentRecorder;
use TakepartMedia\StatamicSop\Support\SopPublisher;
use TakepartMedia\StatamicSop\Support\SopStatus;

/**
 * Per-SOP exclusions take a single SOP out of the gate and the consent flow
 * for matching users, without touching versions or existing consents.
 */
class ExclusionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles([
            'cp' => ['access cp'],
            'editors' => ['access cp'],
            'reviewers' => ['access cp'],
        ]);

        // "staff" carries the "editors" role, "contractors" carries none.
        $this->setTestUserGroups([
            'staff' => ['editors'],
            'contractors' => [],
        ]);
    }

    // --- Sop::isExcludedFor ----------------------------------------------

    #[Test]
    public function a_listed_user_id_is_excluded()
    {
        $user = $this->makeUser(['roles' => ['cp']]);
        $other = $this->makeUser(['roles' => ['cp']]);

        $sop = $this->makeSop('A', 'a', extra: ['excluded_users' => [$user->id()]]);

        $this->assertTrue($sop->isExcludedFor($user));
        $this->assertFalse($sop->isExcludedFor($other));
    }

    #[Test]
    public function a_listed_role_is_excluded()
    {
        $sop = $this->makeSop('A', 'a', extra: ['excluded_roles' => ['editors']]);

        $this->assertTrue($sop->isExcludedFor($this->makeUser(['roles' => ['editors']])));
        $this->assertFalse($sop->isExcludedFor($this->makeUser(['roles' => ['reviewers']])));
    }

    #[Test]
    public function a_listed_group_is_excluded()
    {
        $sop = $this->makeSop('A', 'a', extra: ['excluded_groups' => ['contractors']]);

        $this->assertTrue($sop->isExcludedFor($this->makeUser(['groups' => ['contractors']])));
        $this->assertFalse($sop->isExcludedFor($this->makeUser(['groups' => ['staff']])));
    }

    #[Test]
    public function a_role_inherited_through_a_group_is_excluded()
    {
        $sop = $this->makeSop('A', 'a', extra: ['excluded_roles' => ['editors']]);

        $user = $this->makeUser(['groups' => ['staff']]);

        $this->assertTrue($user->hasRole('editors'));
        $this->assertTrue($sop->isExcludedFor($user));
    }

    #[Test]
    public function a_user_matching_nothing_is_not_excluded()
    {
        $sop = $this->makeSop('A', 'a', extra: [
            'excluded_users' => ['someone-else'],
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['contractors'],
        ]);

        $this->assertFalse($sop->isExcludedFor($this->makeUser(['roles' => ['reviewers']])));
        $this->assertFalse($sop->isExcludedFor($this->makeUser(['roles' => ['cp']])));
        $this->assertFalse($sop->isExcludedFor($this->makeUser()));
    }

    #[Test]
    public function empty_or_missing_exclusions_exclude_nobody()
    {
        $user = $this->makeUser(['roles' => ['editors'], 'groups' => ['staff']]);

        $none = $this->makeSop('None', 'a');
        $empty = $this->makeSop('Empty', 'b', extra: [
            'excluded_users' => [],
            'excluded_roles' => [],
            'excluded_groups' => [],
        ]);

        $this->assertNull($none->fresh()->excluded_roles);
        $this->assertNull($empty->fresh()->excluded_roles);

        $this->assertFalse($none->fresh()->isExcludedFor($user));
        $this->assertFalse($empty->fresh()->isExcludedFor($user));

        // A raw row written outside the publisher, with literal empty arrays.
        $raw = new Sop(['title' => 'Raw', 'excluded_users' => [], 'excluded_roles' => [], 'excluded_groups' => []]);
        $this->assertFalse($raw->isExcludedFor($user));
    }

    #[Test]
    public function the_exclusion_check_never_touches_the_sop_database()
    {
        $sop = $this->makeSop('A', 'a', extra: [
            'excluded_users' => ['x'],
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['contractors'],
        ])->fresh();

        $users = [
            $this->makeUser(),
            $this->makeUser(['roles' => ['editors']]),
            $this->makeUser(['groups' => ['contractors']]),
        ];

        $queries = 0;
        DB::connection('sop')->listen(function () use (&$queries) {
            $queries++;
        });

        foreach ($users as $user) {
            $sop->isExcludedFor($user);
        }

        $this->assertSame(0, $queries);
    }

    // --- Gate + consent flow ---------------------------------------------

    #[Test]
    public function an_excluded_role_is_only_gated_by_the_remaining_sops()
    {
        $a = $this->makeSop('A', 'a', 1, extra: ['excluded_roles' => ['editors']]);
        $b = $this->makeSop('B', 'b', 2);

        $editor = $this->makeUser(['roles' => ['editors']]);

        $this->actingAs($editor)
            ->get(cp_route('dashboard'))
            ->assertRedirect(cp_route('sop.consent'));

        $this->actingAs($editor)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewHas('sop', fn ($sop) => $sop->id === $b->id)
            ->assertViewHas('position', 1)
            ->assertViewHas('total', 1);

        $this->consent($editor, $b)->assertRedirect(cp_route('dashboard'));

        $this->actingAs($editor)->get(cp_route('dashboard'))->assertOk();

        $this->assertSame(0, SopConsent::query()->where('sop_id', $a->id)->count());
    }

    #[Test]
    public function a_user_without_the_excluded_role_is_gated_by_every_sop()
    {
        $a = $this->makeSop('A', 'a', 1, extra: ['excluded_roles' => ['editors']]);
        $b = $this->makeSop('B', 'b', 2);

        $reviewer = $this->makeUser(['roles' => ['reviewers']]);

        $this->actingAs($reviewer)
            ->get(cp_route('dashboard'))
            ->assertRedirect(cp_route('sop.consent'));

        $this->actingAs($reviewer)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewHas('sop', fn ($sop) => $sop->id === $a->id)
            ->assertViewHas('position', 1)
            ->assertViewHas('total', 2);

        $this->consent($reviewer, $a)->assertRedirect(cp_route('sop.consent'));

        $this->startFreshRequestScope();

        $this->actingAs($reviewer)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewHas('sop', fn ($sop) => $sop->id === $b->id)
            ->assertViewHas('position', 2)
            ->assertViewHas('total', 2);
    }

    #[Test]
    public function a_user_excluded_from_every_sop_is_never_gated()
    {
        $user = $this->makeUser(['roles' => ['cp'], 'groups' => ['contractors']]);

        $this->makeSop('A', 'a', 1, extra: ['excluded_users' => [$user->id()]]);
        $this->makeSop('B', 'b', 2, extra: ['excluded_groups' => ['contractors']]);

        $this->actingAs($user)->get(cp_route('dashboard'))->assertOk();

        // No trap on the consent screen either.
        $this->actingAs($user)
            ->get(cp_route('sop.consent'))
            ->assertRedirect(cp_route('index'));
    }

    // --- ConsentRecorder -------------------------------------------------

    #[Test]
    public function recording_a_consent_for_an_excluded_sop_is_refused()
    {
        $user = $this->makeUser(['roles' => ['editors']]);

        $excluded = $this->makeSop('Excluded', 'a', 1, extra: ['excluded_roles' => ['editors']]);
        $required = $this->makeSop('Required', 'b', 2);

        $recorder = app(ConsentRecorder::class);

        try {
            $recorder->record($user, $excluded->id, $excluded->current_version_id);
            $this->fail('Expected a StaleConsentException.');
        } catch (StaleConsentException $e) {
            $this->assertStringContainsString("SOP [{$excluded->id}] is not the next", $e->getMessage());
        }

        $this->assertSame(0, SopConsent::count());

        $this->assertTrue($recorder->record($user, $required->id, $required->current_version_id));

        $this->assertSame(1, SopConsent::count());
        $this->assertSame($required->id, SopConsent::first()->sop_id);
    }

    #[Test]
    public function posting_a_consent_for_an_excluded_sop_is_refused_over_http()
    {
        $editor = $this->makeUser(['roles' => ['editors']]);

        $excluded = $this->makeSop('Excluded', 'a', 1, extra: ['excluded_roles' => ['editors']]);
        $this->makeSop('Required', 'b', 2);

        $this->consent($editor, $excluded)
            ->assertRedirect(cp_route('sop.consent'))
            ->assertSessionHas('error');

        $this->assertSame(0, SopConsent::count());
    }

    // --- Versioning ------------------------------------------------------

    #[Test]
    public function changing_only_exclusions_never_creates_a_version()
    {
        $sop = $this->makeSop('A', 'Body');
        $user = $this->makeUser(['roles' => ['cp']]);

        $this->consent($user, $sop)->assertRedirect(cp_route('index'));

        $versionId = $sop->current_version_id;

        app(SopPublisher::class)->update($sop->fresh(), [
            'excluded_users' => ['someone-else'],
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['contractors'],
        ]);

        $sop = $sop->fresh();

        $this->assertSame(1, SopVersion::query()->where('sop_id', $sop->id)->count());
        $this->assertSame($versionId, $sop->current_version_id);
        $this->assertSame(['someone-else'], $sop->excluded_users);
        $this->assertSame(['editors'], $sop->excluded_roles);
        $this->assertSame(['contractors'], $sop->excluded_groups);

        $this->startFreshRequestScope();

        $this->actingAs($user)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function a_partial_update_leaves_exclusions_alone_and_an_empty_list_clears_them()
    {
        $sop = $this->makeSop('A', 'Body', extra: [
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['contractors'],
        ]);

        app(SopPublisher::class)->update($sop->fresh(), ['sort_order' => 5]);

        $this->assertSame(['editors'], $sop->fresh()->excluded_roles);
        $this->assertSame(['contractors'], $sop->fresh()->excluded_groups);

        app(SopPublisher::class)->update($sop->fresh(), ['excluded_roles' => []]);

        $this->assertNull($sop->fresh()->excluded_roles);
        $this->assertSame(['contractors'], $sop->fresh()->excluded_groups);
        $this->assertSame(1, SopVersion::query()->where('sop_id', $sop->id)->count());
    }

    #[Test]
    public function a_consent_survives_a_later_exclusion_and_its_removal()
    {
        $sop = $this->makeSop('A', 'Body');
        $user = $this->makeUser(['roles' => ['editors']]);

        $this->consent($user, $sop)->assertRedirect(cp_route('index'));
        $this->assertSame(1, SopConsent::count());

        app(SopPublisher::class)->update($sop->fresh(), ['excluded_roles' => ['editors']]);
        $this->startFreshRequestScope();

        $this->actingAs($user)->get(cp_route('dashboard'))->assertOk();

        // The audit row stays even while the SOP no longer applies.
        $this->assertSame(1, SopConsent::count());

        app(SopPublisher::class)->update($sop->fresh(), ['excluded_roles' => []]);
        $this->startFreshRequestScope();

        // Same version as before, so the original consent still counts.
        $this->actingAs($user)->get(cp_route('dashboard'))->assertOk();
        $this->assertSame($sop->current_version_id, $sop->fresh()->current_version_id);
    }

    // --- SopStatus -------------------------------------------------------

    #[Test]
    public function the_required_count_is_memoised_per_user_until_forgotten()
    {
        $status = app(SopStatus::class);

        $this->makeSop('A', 'a', 1, extra: ['excluded_roles' => ['editors']]);
        $this->makeSop('B', 'b', 2);
        $this->makeSop('C', 'c', 3, extra: ['excluded_groups' => ['contractors']]);

        $editor = $this->makeUser(['roles' => ['editors']]);
        $contractor = $this->makeUser(['roles' => ['editors'], 'groups' => ['contractors']]);
        $reviewer = $this->makeUser(['roles' => ['reviewers']]);

        $this->assertSame(2, $status->requiredCountFor($editor));
        $this->assertSame(1, $status->requiredCountFor($contractor));
        $this->assertSame(3, $status->requiredCountFor($reviewer));
        $this->assertSame(0, $status->requiredCountFor(null));

        $this->makeSop('D', 'd', 4);

        // Memo still holds the old answers.
        $this->assertSame(2, $status->requiredCountFor($editor));
        $this->assertSame(3, $status->requiredCountFor($reviewer));

        $status->forget();

        $this->assertSame(3, $status->requiredCountFor($editor));
        $this->assertSame(2, $status->requiredCountFor($contractor));
        $this->assertSame(4, $status->requiredCountFor($reviewer));
    }

    #[Test]
    public function fresh_pending_skips_excluded_sops_and_stays_in_order()
    {
        $status = app(SopStatus::class);

        $this->makeSop('First', 'a', 1);
        $this->makeSop('Second', 'b', 2, extra: ['excluded_roles' => ['editors']]);
        $this->makeSop('Third', 'c', 3);

        $editor = $this->makeUser(['roles' => ['editors']]);

        $pending = $status->freshPendingFor($editor);

        $this->assertSame(['First', 'Third'], $pending->pluck('title')->all());
        $this->assertSame([0, 1], $pending->keys()->all());
        $this->assertSame(['First', 'Third'], $status->pendingFor($editor)->pluck('title')->all());
    }

    private function consent(UserContract $user, Sop $sop)
    {
        return $this->actingAs($user)->post(cp_route('sop.consent.store'), [
            'sop_id' => $sop->id,
            'sop_version_id' => $sop->current_version_id,
            'confirmed' => '1',
        ]);
    }
}
