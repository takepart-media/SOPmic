<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use TakepartMedia\StatamicSop\Models\Sop;
use TakepartMedia\StatamicSop\Models\SopConsent;
use TakepartMedia\StatamicSop\Support\SopPublisher;

/**
 * What the consent endpoint refuses. Everything in the payload is a claim; the
 * only fields that survive are the two ids, and both are re-checked against
 * the database before a row is written.
 */
class ConsentSecurityTest extends TestCase
{
    private UserContract $user;

    private Sop $sop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles(['cp' => ['access cp']]);
        $this->user = $this->makeUser(['roles' => ['cp']]);

        $this->sop = $this->makeSop('Safety', 'Version one.', 1);
    }

    #[Test]
    public function a_superseded_version_is_refused()
    {
        $staleVersionId = $this->sop->current_version_id;

        // The user is looking at v1 while an editor publishes v2.
        app(SopPublisher::class)->update($this->sop, ['content' => 'Version two.']);
        $this->startFreshRequestScope();

        $this->consentPost($staleVersionId)
            ->assertRedirect(cp_route('sop.consent'))
            ->assertSessionHas('error');

        $this->assertSame(0, SopConsent::query()->where('sop_version_id', $staleVersionId)->count());
        $this->assertSame(0, SopConsent::query()->count());
    }

    #[Test]
    public function the_new_version_can_be_consented_to_right_after_a_bump()
    {
        app(SopPublisher::class)->update($this->sop, ['content' => 'Version two.']);
        $this->startFreshRequestScope();

        $current = $this->sop->fresh()->current_version_id;

        $this->consentPost($current)->assertRedirect(cp_route('index'));

        $this->assertSame(1, SopConsent::query()->where('sop_version_id', $current)->count());
    }

    #[Test]
    public function fabricated_ids_are_refused()
    {
        $this->consentPost(9999, sopId: 9999)
            ->assertRedirect(cp_route('sop.consent'))
            ->assertSessionHas('error');

        // A real SOP paired with someone else's version id is no better.
        $this->consentPost(9999, sopId: $this->sop->id)
            ->assertRedirect(cp_route('sop.consent'))
            ->assertSessionHas('error');

        $this->assertSame(0, SopConsent::query()->count());
    }

    #[Test]
    public function the_checkbox_is_mandatory()
    {
        $this->actingAs($this->user)
            ->post(cp_route('sop.consent.store'), [
                'sop_id' => $this->sop->id,
                'sop_version_id' => $this->sop->current_version_id,
            ])
            ->assertSessionHasErrors('confirmed');

        $this->assertSame(0, SopConsent::query()->count());
    }

    #[Test]
    public function the_ids_are_mandatory()
    {
        $this->actingAs($this->user)
            ->post(cp_route('sop.consent.store'), ['confirmed' => '1'])
            ->assertSessionHasErrors(['sop_id', 'sop_version_id']);

        $this->assertSame(0, SopConsent::query()->count());
    }

    #[Test]
    public function the_row_is_always_attributed_to_the_authenticated_user()
    {
        $other = $this->makeUser();

        // user_id is not an accepted field — this proves it is also not quietly
        // picked up from the request anywhere down the line.
        $this->actingAs($this->user)
            ->post(cp_route('sop.consent.store'), [
                'sop_id' => $this->sop->id,
                'sop_version_id' => $this->sop->current_version_id,
                'user_id' => $other->id(),
                'confirmed' => '1',
            ])
            ->assertRedirect(cp_route('index'));

        $consent = SopConsent::query()->sole();

        $this->assertSame((string) $this->user->id(), $consent->user_id);
        $this->assertSame(0, SopConsent::query()->where('user_id', $other->id())->count());
    }

    #[Test]
    public function a_double_submit_writes_one_row()
    {
        $this->consentPost($this->sop->current_version_id)->assertRedirect(cp_route('index'));
        $this->consentPost($this->sop->current_version_id)->assertRedirect(cp_route('index'));

        $this->assertSame(1, SopConsent::query()->count());
    }

    #[Test]
    public function it_records_the_audit_fields()
    {
        $this->actingAs($this->user)
            ->withHeader('User-Agent', 'PhpUnit/1.0')
            ->post(cp_route('sop.consent.store'), [
                'sop_id' => $this->sop->id,
                'sop_version_id' => $this->sop->current_version_id,
                'confirmed' => '1',
            ]);

        $consent = SopConsent::query()->sole();

        $this->assertSame('127.0.0.1', $consent->ip);
        $this->assertSame('PhpUnit/1.0', $consent->user_agent);
        $this->assertNotNull($consent->consented_at);
    }

    private function consentPost(int $versionId, ?int $sopId = null)
    {
        return $this->actingAs($this->user)->post(cp_route('sop.consent.store'), [
            'sop_id' => $sopId ?? $this->sop->id,
            'sop_version_id' => $versionId,
            'confirmed' => '1',
        ]);
    }
}
