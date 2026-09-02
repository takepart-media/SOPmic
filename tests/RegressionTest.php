<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use TakepartMedia\StatamicSop\Models\Sop;

/**
 * The point of the whole addon: once a user is done with the SOPs, the
 * control panel behaves exactly like it would without this addon installed.
 * Statamic's own authorization is neither loosened nor tightened by anything
 * here — `manage sops` and the gate are additive.
 */
class RegressionTest extends TestCase
{
    #[Test]
    public function a_fully_consented_user_gets_a_normal_cp_with_untouched_core_permissions()
    {
        $this->setTestRoles([
            // 'configure collections' rather than nothing: CollectionPolicy
            // denies `collections.index` to a user who can see zero
            // collections, which a bare `access cp` role would be in a fresh
            // test app — that would make the assertion below about core
            // permissions accidentally true for the wrong reason.
            'cp' => ['access cp', 'configure collections'],
        ]);

        $user = $this->makeUser(['roles' => ['cp']]);

        $first = $this->makeSop('First', 'One.', 1);
        $second = $this->makeSop('Second', 'Two.', 2);

        $this->consent($user, $first);
        $this->consent($user, $second);

        // Ordinary CP screens, wide open now that nothing is pending.
        $this->actingAs($user)->get(cp_route('dashboard'))->assertOk();
        $this->actingAs($user)->get(cp_route('collections.index'))->assertOk();

        // Core authorization is exactly as strict as it always was: this
        // user was never granted `view users`, so Statamic's own UserPolicy
        // denies them — our gate has nothing to do with this, and must not
        // accidentally let it through or hide the denial as something else.
        $this->actingAs($user)->getJson(cp_route('users.index'))->assertForbidden();

        // Nothing left to confirm — the consent screen sends them straight
        // back into the CP rather than re-rendering.
        $this->actingAs($user)
            ->get(cp_route('sop.consent'))
            ->assertRedirect(cp_route('index'));
    }

    #[Test]
    public function a_new_active_sop_re_gates_a_user_who_had_already_finished()
    {
        $this->setTestRoles(['cp' => ['access cp']]);
        $user = $this->makeUser(['roles' => ['cp']]);

        $first = $this->makeSop('First', 'One.', 1);
        $this->consent($user, $first);

        $this->actingAs($user)->get(cp_route('dashboard'))->assertOk();

        // A second, brand new SOP shows up after the fact — the pending
        // query is stateless, so it must notice on the very next request.
        $this->makeSop('Second', 'A brand new rule.', 2);
        $this->startFreshRequestScope();

        $this->actingAs($user)
            ->get(cp_route('dashboard'))
            ->assertRedirect(cp_route('sop.consent'));

        $this->actingAs($user)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewHas('sop', fn ($sop) => $sop->title === 'Second');
    }

    private function consent(UserContract $user, Sop $sop): void
    {
        $this->actingAs($user)
            ->post(cp_route('sop.consent.store'), [
                'sop_id' => $sop->id,
                'sop_version_id' => $sop->current_version_id,
                'confirmed' => '1',
            ])
            ->assertRedirect();
    }
}
