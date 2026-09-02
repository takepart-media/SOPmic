<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;

/**
 * The bypass matrix at the HTTP level. BypassResolverTest already covers the
 * decision itself; this covers the gate honouring it.
 */
class BypassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles([
            'cp' => ['access cp'],
            'compliance' => ['access cp'],
            'editor' => ['access cp'],
        ]);

        $this->setTestUserGroups([
            'auditors' => ['compliance'],
            'staff' => ['editor'],
        ]);

        $this->makeSop('Safety', 'Read me.');
    }

    #[Test]
    public function a_super_admin_bypasses_the_gate()
    {
        $this->assertPasses($this->makeSuper());
    }

    #[Test]
    public function a_plain_user_is_gated()
    {
        $this->assertGated($this->makeUser(['roles' => ['cp']]));
    }

    #[Test]
    public function a_configured_role_bypasses_the_gate()
    {
        config(['sop.bypass.roles' => ['compliance']]);

        $this->assertPasses($this->makeUser(['roles' => ['compliance']]));
    }

    #[Test]
    public function a_configured_group_bypasses_the_gate()
    {
        config(['sop.bypass.groups' => ['auditors']]);

        $this->assertPasses($this->makeUser(['groups' => ['auditors']]));
    }

    #[Test]
    public function a_configured_role_held_only_through_a_group_bypasses_the_gate()
    {
        // The config names a role, the user has it only via a group. Statamic's
        // hasRole() folds group roles in, so this counts — worth pinning down,
        // because the alternative reading would silently gate compliance staff.
        config(['sop.bypass.roles' => ['compliance']]);

        $user = $this->makeUser(['groups' => ['auditors']]);

        $this->assertTrue($user->hasRole('compliance'));
        $this->assertPasses($user);
    }

    #[Test]
    public function an_unconfigured_role_is_gated()
    {
        config(['sop.bypass.roles' => ['compliance']]);

        $this->assertGated($this->makeUser(['roles' => ['editor']]));
    }

    #[Test]
    public function an_unconfigured_group_is_gated()
    {
        config(['sop.bypass.groups' => ['auditors']]);

        $this->assertGated($this->makeUser(['groups' => ['staff']]));
    }

    #[Test]
    public function a_bypassed_user_is_not_trapped_on_the_consent_screen()
    {
        config(['sop.bypass.roles' => ['compliance']]);

        $user = $this->makeUser(['roles' => ['compliance']]);

        // They have pending SOPs on paper, so the consent screen would happily
        // render for them. It must send them on instead.
        $this->actingAs($user)
            ->get(cp_route('sop.consent'))
            ->assertRedirect(cp_route('index'));
    }

    private function assertPasses(UserContract $user): void
    {
        $this->actingAs($user)->get(cp_route('dashboard'))->assertOk();
    }

    private function assertGated(UserContract $user): void
    {
        $this->actingAs($user)
            ->get(cp_route('dashboard'))
            ->assertRedirect(cp_route('sop.consent'));
    }
}
