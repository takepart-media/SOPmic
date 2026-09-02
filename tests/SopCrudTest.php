<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use TakepartMedia\StatamicSop\Models\Sop;
use TakepartMedia\StatamicSop\Models\SopConsent;
use TakepartMedia\StatamicSop\Models\SopVersion;

/**
 * The management CRUD, exercised through HTTP so SopPublisher's versioning is
 * proven end to end rather than just at the service layer (that's
 * VersioningTest's job).
 *
 * SOPs used to test the CRUD mechanics themselves are created inactive: an
 * active SOP would gate the very manager driving the test, since `manage
 * sops` is a permission, not a bypass (see the dedicated interplay tests at
 * the bottom of this file).
 */
class SopCrudTest extends TestCase
{
    private UserContract $manager;

    private UserContract $plainUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles([
            'sop_admin' => ['access cp', 'manage sops'],
            'cp_only' => ['access cp'],
        ]);

        $this->manager = $this->makeUser(['roles' => ['sop_admin']]);
        $this->plainUser = $this->makeUser(['roles' => ['cp_only']]);
    }

    #[Test]
    public function a_manager_can_list_sops()
    {
        $this->makeSop('First', 'One.', 1, active: false);
        $this->makeSop('Second', 'Two.', 2, active: false);

        $this->actingAs($this->manager)
            ->get(cp_route('sop.index'))
            ->assertOk()
            ->assertViewIs('sop::index')
            ->assertSee('First')
            ->assertSee('Second');
    }

    #[Test]
    public function a_manager_can_see_the_create_form()
    {
        $this->actingAs($this->manager)
            ->get(cp_route('sop.create'))
            ->assertOk()
            ->assertViewIs('sop::create');
    }

    #[Test]
    public function storing_an_sop_writes_version_one()
    {
        $response = $this->actingAs($this->manager)->post(cp_route('sop.store'), [
            'title' => 'Fire drill',
            'content' => 'Walk, do not run.',
            'sort_order' => 5,
        ]);

        $response->assertRedirect(cp_route('sop.index'));

        $sop = Sop::query()->where('title', 'Fire drill')->firstOrFail();

        $this->assertFalse($sop->active);
        $this->assertSame(5, $sop->sort_order);
        $this->assertSame(1, $sop->versions()->count());
        $this->assertSame(1, $sop->currentVersion->version_no);
        $this->assertSame('Walk, do not run.', $sop->currentVersion->content);
        $this->assertSame($this->manager->id(), $sop->currentVersion->created_by);
    }

    #[Test]
    public function the_active_checkbox_is_read_explicitly_so_unchecking_it_actually_deactivates()
    {
        $sop = $this->makeSop('Toggle me', 'Body', active: false);

        // A super admin, not the manager: toggling this SOP live would gate
        // the manager's own very next request (they would not yet have
        // consented to it) before it ever reached the controller — a
        // self-inflicted trap that has nothing to do with what this test
        // checks, which is the checkbox handling in SopController.
        $super = $this->makeSuper();

        $this->actingAs($super)->patch(cp_route('sop.update', $sop), [
            'title' => 'Toggle me',
            'content' => 'Body',
            'active' => '1',
        ])->assertRedirect(cp_route('sop.index'));

        $this->assertTrue($sop->fresh()->active);

        // Omitting the checkbox entirely (as an unchecked <input> does) must
        // turn it back off, not leave the previous value untouched.
        $this->actingAs($super)->patch(cp_route('sop.update', $sop), [
            'title' => 'Toggle me',
            'content' => 'Body',
        ])->assertRedirect(cp_route('sop.index'));

        $this->assertFalse($sop->fresh()->active);
    }

    #[Test]
    public function a_manager_can_view_an_sop_with_its_history_and_audit_trail()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false);

        $this->actingAs($this->manager)
            ->get(cp_route('sop.show', $sop))
            ->assertOk()
            ->assertViewIs('sop::show')
            ->assertSee('Safety')
            ->assertSee('Read me.', false);
    }

    #[Test]
    public function a_manager_can_see_the_edit_form_prefilled()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false);

        $this->actingAs($this->manager)
            ->get(cp_route('sop.edit', $sop))
            ->assertOk()
            ->assertViewIs('sop::edit')
            ->assertSee('Safety');
    }

    #[Test]
    public function updating_the_content_bumps_the_version_and_keeps_the_old_one_intact()
    {
        $sop = $this->makeSop('Safety', 'Old body.', active: false);
        $firstVersionId = $sop->current_version_id;

        $this->actingAs($this->manager)->patch(cp_route('sop.update', $sop), [
            'title' => 'Safety',
            'content' => 'New body.',
        ])->assertRedirect(cp_route('sop.index'));

        $sop->refresh();

        $this->assertSame(2, $sop->versions()->count());
        $this->assertSame(2, $sop->currentVersion->version_no);
        $this->assertSame('New body.', $sop->currentVersion->content);
        $this->assertNotSame($firstVersionId, $sop->current_version_id);

        $old = SopVersion::query()->findOrFail($firstVersionId);
        $this->assertSame('Old body.', $old->content);
    }

    #[Test]
    public function updating_without_a_content_change_does_not_bump_the_version()
    {
        $sop = $this->makeSop('Safety', 'Body.', active: false);
        $firstVersionId = $sop->current_version_id;

        $this->actingAs($this->manager)->patch(cp_route('sop.update', $sop), [
            'title' => 'Safety',
            'content' => 'Body.',
            'sort_order' => 3,
        ]);

        $sop->refresh();

        $this->assertSame(1, $sop->versions()->count());
        $this->assertSame($firstVersionId, $sop->current_version_id);
        $this->assertSame(3, $sop->sort_order);
    }

    #[Test]
    public function destroying_an_sop_soft_deletes_it_and_keeps_the_audit_trail()
    {
        $sop = $this->makeSop('Retire me', 'Body.', active: true);

        // A real user consents while it's still active, so there is
        // something in the audit trail worth preserving.
        $consenter = $this->makeUser(['roles' => ['cp_only']]);
        $this->actingAs($consenter)->post(cp_route('sop.consent.store'), [
            'sop_id' => $sop->id,
            'sop_version_id' => $sop->current_version_id,
            'confirmed' => '1',
        ]);

        $this->assertSame(1, SopConsent::query()->where('sop_id', $sop->id)->count());

        // The manager themself must not be gated by the very SOP they are
        // deleting, so bypass them for this one call via a super account
        // instead — deletion authorization is `manage sops` either way.
        $super = $this->makeSuper();

        $this->actingAs($super)
            ->delete(cp_route('sop.destroy', $sop))
            ->assertRedirect(cp_route('sop.index'));

        $this->assertNull(Sop::query()->find($sop->id));
        $this->assertNotNull(Sop::withTrashed()->find($sop->id));

        // Gone from the listing.
        $this->actingAs($super)
            ->get(cp_route('sop.index'))
            ->assertOk()
            ->assertDontSee('Retire me');

        // 404 on show/edit rather than resurrecting it.
        $this->actingAs($super)->get(cp_route('sop.show', $sop->id))->assertNotFound();
        $this->actingAs($super)->get(cp_route('sop.edit', $sop->id))->assertNotFound();

        // Versions and consents survive untouched.
        $this->assertSame(1, SopVersion::query()->where('sop_id', $sop->id)->count());
        $this->assertSame(1, SopConsent::query()->where('sop_id', $sop->id)->count());

        // And it no longer gates anyone — a brand new user sails through.
        $freshUser = $this->makeUser(['roles' => ['cp_only']]);
        $this->actingAs($freshUser)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function missing_required_fields_are_rejected_with_redirect_and_errors()
    {
        $this->actingAs($this->manager)
            ->post(cp_route('sop.store'), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['title', 'content']);

        $this->assertSame(0, Sop::query()->count());
    }

    #[Test]
    public function missing_required_fields_are_rejected_with_422_for_json_requests()
    {
        $this->actingAs($this->manager)
            ->postJson(cp_route('sop.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);
    }

    /**
     * Statamic's CP exception rendering (RendersControlPanelExceptions)
     * intercepts Laravel's AuthorizationException for non-JSON requests and
     * turns it into a redirect-with-flash instead of a bare 403 — the same
     * treatment core's own `can:manage preferences` routes get. A JSON
     * request skips that branch and gets the real 403, which is the
     * unambiguous way to assert "denied" here.
     */
    #[Test]
    public function a_user_without_manage_sops_is_forbidden_on_every_management_route()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false);

        $this->actingAs($this->plainUser)->getJson(cp_route('sop.index'))->assertForbidden();
        $this->actingAs($this->plainUser)->getJson(cp_route('sop.create'))->assertForbidden();
        $this->actingAs($this->plainUser)->postJson(cp_route('sop.store'), [])->assertForbidden();
        $this->actingAs($this->plainUser)->getJson(cp_route('sop.show', $sop))->assertForbidden();
        $this->actingAs($this->plainUser)->getJson(cp_route('sop.edit', $sop))->assertForbidden();
        $this->actingAs($this->plainUser)->patchJson(cp_route('sop.update', $sop), [])->assertForbidden();
        $this->actingAs($this->plainUser)->deleteJson(cp_route('sop.destroy', $sop))->assertForbidden();
    }

    #[Test]
    public function a_user_without_manage_sops_is_redirected_with_a_flash_on_a_plain_request()
    {
        $this->actingAs($this->plainUser)
            ->get(cp_route('sop.index'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    #[Test]
    public function the_consent_flow_stays_reachable_without_manage_sops()
    {
        $this->makeSop('Safety', 'Read me.', active: true);

        $this->actingAs($this->plainUser)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewIs('sop::consent');
    }

    #[Test]
    public function a_manager_with_their_own_pending_sops_is_gated_before_reaching_management_routes()
    {
        // The gate is prepended ahead of the `can:manage sops` route
        // middleware, so it is checked first regardless of permissions.
        $this->makeSop('Safety', 'Read me.', active: true);

        $this->actingAs($this->manager)
            ->get(cp_route('sop.index'))
            ->assertRedirect(cp_route('sop.consent'));
    }

    #[Test]
    public function a_super_admin_manages_sops_without_ever_consenting()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: true);
        $super = $this->makeSuper();

        $this->actingAs($super)->get(cp_route('sop.index'))->assertOk();
        $this->actingAs($super)->get(cp_route('sop.create'))->assertOk();
        $this->actingAs($super)->get(cp_route('sop.show', $sop))->assertOk();
        $this->actingAs($super)->get(cp_route('sop.edit', $sop))->assertOk();

        $this->actingAs($super)->patch(cp_route('sop.update', $sop), [
            'title' => 'Safety',
            'content' => 'Read me, updated.',
            'active' => '1',
        ])->assertRedirect(cp_route('sop.index'));

        $this->assertSame('Read me, updated.', $sop->fresh()->currentVersion->content);

        $this->assertSame(0, SopConsent::query()->where('user_id', $super->id())->count());
    }
}
