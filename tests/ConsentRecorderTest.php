<?php

namespace TakepartMedia\StatamicSop\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use TakepartMedia\StatamicSop\Exceptions\StaleConsentException;
use TakepartMedia\StatamicSop\Models\SopConsent;
use TakepartMedia\StatamicSop\Support\ConsentRecorder;
use TakepartMedia\StatamicSop\Support\SopPublisher;
use TakepartMedia\StatamicSop\Support\SopStatus;

class ConsentRecorderTest extends TestCase
{
    private ConsentRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = app(ConsentRecorder::class);
    }

    #[Test]
    public function it_records_a_consent_for_the_first_pending_sop()
    {
        config(['sop.audit.ip' => true, 'sop.audit.user_agent' => true]);

        $sop = $this->makeSop('First', 'Body', 10);
        $user = $this->makeUser();

        $result = $this->recorder->record(
            $user,
            $sop->id,
            $sop->current_version_id,
            '203.0.113.7',
            'Mozilla/5.0'
        );

        $this->assertTrue($result);
        $this->assertSame(1, SopConsent::count());

        $consent = SopConsent::first();

        $this->assertSame($user->id(), $consent->user_id);
        $this->assertSame($sop->id, $consent->sop_id);
        $this->assertSame($sop->current_version_id, $consent->sop_version_id);
        $this->assertSame('203.0.113.7', $consent->ip);
        $this->assertSame('Mozilla/5.0', $consent->user_agent);
        $this->assertNotNull($consent->consented_at);
        $this->assertNotNull($consent->created_at);
    }

    #[Test]
    public function consenting_clears_the_sop_from_the_pending_list()
    {
        $sop = $this->makeSop('First', 'Body');
        $user = $this->makeUser();

        $this->recorder->record($user, $sop->id, $sop->current_version_id);

        $status = app(SopStatus::class);
        $status->forget();

        $this->assertCount(0, $status->pendingFor($user));
    }

    #[Test]
    public function it_rejects_a_consent_for_an_sop_that_is_not_next_in_line()
    {
        $this->makeSop('First', 'a', 10);
        $second = $this->makeSop('Second', 'b', 20);

        $user = $this->makeUser();

        $this->expectException(StaleConsentException::class);

        try {
            $this->recorder->record($user, $second->id, $second->current_version_id);
        } finally {
            $this->assertSame(0, SopConsent::count());
        }
    }

    #[Test]
    public function it_rejects_a_stale_version_id()
    {
        $sop = $this->makeSop('First', 'Original');
        $staleVersionId = $sop->current_version_id;

        app(SopPublisher::class)->update($sop, ['content' => 'Rewritten']);

        $user = $this->makeUser();

        $this->expectException(StaleConsentException::class);

        try {
            $this->recorder->record($user, $sop->id, $staleVersionId);
        } finally {
            $this->assertSame(0, SopConsent::count());
        }
    }

    #[Test]
    public function it_rejects_an_sop_id_that_does_not_exist()
    {
        $sop = $this->makeSop('First', 'Body');
        $user = $this->makeUser();

        $this->expectException(StaleConsentException::class);

        try {
            $this->recorder->record($user, 99999, $sop->current_version_id);
        } finally {
            $this->assertSame(0, SopConsent::count());
        }
    }

    #[Test]
    public function it_rejects_an_inactive_sop()
    {
        $sop = $this->makeSop('Inactive', 'Body', 0, active: false);
        $user = $this->makeUser();

        $this->expectException(StaleConsentException::class);

        try {
            $this->recorder->record($user, $sop->id, $sop->current_version_id);
        } finally {
            $this->assertSame(0, SopConsent::count());
        }
    }

    #[Test]
    public function recording_an_existing_consent_is_a_successful_no_op()
    {
        $sop = $this->makeSop('First', 'Body');
        $user = $this->makeUser();

        SopConsent::create([
            'user_id' => $user->id(),
            'sop_id' => $sop->id,
            'sop_version_id' => $sop->current_version_id,
            'consented_at' => now()->subMinute(),
        ]);

        $this->assertTrue($this->recorder->record($user, $sop->id, $sop->current_version_id));

        $this->assertSame(1, SopConsent::count());
    }

    #[Test]
    public function recording_twice_leaves_exactly_one_row()
    {
        $sop = $this->makeSop('First', 'Body');
        $user = $this->makeUser();

        $this->assertTrue($this->recorder->record($user, $sop->id, $sop->current_version_id));
        $this->assertTrue($this->recorder->record($user, $sop->id, $sop->current_version_id));

        $this->assertSame(1, SopConsent::count());
    }

    #[Test]
    public function it_walks_through_the_sequence()
    {
        $first = $this->makeSop('First', 'a', 10);
        $second = $this->makeSop('Second', 'b', 20);

        $user = $this->makeUser();

        $this->recorder->record($user, $first->id, $first->current_version_id);
        $this->recorder->record($user, $second->id, $second->current_version_id);

        $this->assertSame(2, SopConsent::count());

        $status = app(SopStatus::class);
        $status->forget();

        $this->assertCount(0, $status->pendingFor($user));
    }

    #[Test]
    public function the_user_id_comes_from_the_user_object_only()
    {
        $sop = $this->makeSop('First', 'Body');

        $this->expectException(InvalidArgumentException::class);

        $this->recorder->record(null, $sop->id, $sop->current_version_id);
    }

    #[Test]
    public function an_over_long_user_agent_is_truncated_to_fit()
    {
        config(['sop.audit.user_agent' => true]);

        $sop = $this->makeSop('First', 'Body');
        $user = $this->makeUser();

        $this->recorder->record($user, $sop->id, $sop->current_version_id, null, str_repeat('x', 900));

        $this->assertSame(512, mb_strlen(SopConsent::first()->user_agent));
    }

    #[Test]
    public function ip_and_user_agent_are_not_stored_by_default()
    {
        $sop = $this->makeSop('First', 'Body');
        $user = $this->makeUser();

        $this->recorder->record($user, $sop->id, $sop->current_version_id, '203.0.113.7', 'Mozilla/5.0');

        $consent = SopConsent::first();

        $this->assertNull($consent->ip);
        $this->assertNull($consent->user_agent);
    }
}
