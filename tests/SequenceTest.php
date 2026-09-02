<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use TakepartMedia\StatamicSop\Models\Sop;
use TakepartMedia\StatamicSop\Models\SopConsent;

/**
 * The sequence is server-side: the consent screen always renders the user's
 * first outstanding SOP, and the write refuses anything else. Nothing in the
 * client's payload can reorder it.
 */
class SequenceTest extends TestCase
{
    private UserContract $user;

    private Sop $first;

    private Sop $second;

    private Sop $third;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles(['cp' => ['access cp']]);
        $this->user = $this->makeUser(['roles' => ['cp']]);

        $this->first = $this->makeSop('First', 'One.', 1);
        $this->second = $this->makeSop('Second', 'Two.', 2);
        $this->third = $this->makeSop('Third', 'Three.', 3);
    }

    #[Test]
    public function it_shows_the_first_outstanding_sop()
    {
        $this->actingAs($this->user)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewIs('sop::consent')
            ->assertViewHas('position', 1)
            ->assertViewHas('total', 3)
            ->assertViewHas('sop', fn ($sop) => $sop->id === $this->first->id);
    }

    #[Test]
    public function it_refuses_a_consent_for_a_later_sop()
    {
        $this->consent($this->second)
            ->assertRedirect(cp_route('sop.consent'))
            ->assertSessionHas('error');

        $this->assertSame(0, SopConsent::query()->count());
    }

    #[Test]
    public function it_advances_one_sop_at_a_time()
    {
        $this->consent($this->first)->assertRedirect(cp_route('sop.consent'));

        $this->actingAs($this->user)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewHas('position', 2)
            ->assertViewHas('sop', fn ($sop) => $sop->id === $this->second->id);

        $this->consent($this->second)->assertRedirect(cp_route('sop.consent'));

        $this->actingAs($this->user)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertViewHas('position', 3)
            ->assertViewHas('sop', fn ($sop) => $sop->id === $this->third->id);
    }

    #[Test]
    public function finishing_the_last_sop_opens_the_control_panel()
    {
        $this->actingAs($this->user)
            ->get(cp_route('dashboard'))
            ->assertRedirect(cp_route('sop.consent'));

        $this->consent($this->first);
        $this->consent($this->second);

        $this->consent($this->third)->assertRedirect(cp_route('dashboard'));

        $this->actingAs($this->user)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function the_consent_screen_does_not_trap_a_user_who_is_done()
    {
        $this->consent($this->first);
        $this->consent($this->second);
        $this->consent($this->third);

        $this->actingAs($this->user)
            ->get(cp_route('sop.consent'))
            ->assertRedirect(cp_route('index'));
    }

    #[Test]
    public function resubmitting_a_finished_consent_is_harmless()
    {
        $this->consent($this->first);
        $this->consent($this->second);
        $this->consent($this->third);

        // Back button, double submit, a stale tab — all land here.
        $this->consent($this->first)->assertRedirect(cp_route('index'));

        $this->assertSame(1, SopConsent::query()
            ->where('user_id', $this->user->id())
            ->where('sop_version_id', $this->first->current_version_id)
            ->count());

        $this->assertSame(3, SopConsent::query()->count());
    }

    private function consent(Sop $sop)
    {
        return $this->actingAs($this->user)->post(cp_route('sop.consent.store'), [
            'sop_id' => $sop->id,
            'sop_version_id' => $sop->current_version_id,
            'confirmed' => '1',
        ]);
    }
}
