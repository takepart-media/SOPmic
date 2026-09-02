<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use TakepartMedia\StatamicSop\Models\SopConsent;
use TakepartMedia\StatamicSop\Support\SopPublisher;
use TakepartMedia\StatamicSop\Support\SopStatus;

class SopStatusTest extends TestCase
{
    private SopStatus $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->status = app(SopStatus::class);
    }

    private function consent($user, $sop): void
    {
        SopConsent::create([
            'user_id' => $user->id(),
            'sop_id' => $sop->id,
            'sop_version_id' => $sop->current_version_id,
            'consented_at' => now(),
        ]);
    }

    #[Test]
    public function pending_sops_come_back_in_sort_order()
    {
        $this->makeSop('Third', 'c', 30);
        $this->makeSop('First', 'a', 10);
        $this->makeSop('Second', 'b', 20);

        $user = $this->makeUser();

        $this->assertSame(
            ['First', 'Second', 'Third'],
            $this->status->pendingFor($user)->pluck('title')->all()
        );

        $this->assertSame('First', $this->status->firstPendingFor($user)->title);
        $this->assertSame(3, $this->status->totalRequiredCount());
    }

    #[Test]
    public function ties_on_sort_order_fall_back_to_id()
    {
        $a = $this->makeSop('A', 'a', 0);
        $b = $this->makeSop('B', 'b', 0);

        $user = $this->makeUser();

        $this->assertSame([$a->id, $b->id], $this->status->pendingFor($user)->pluck('id')->all());
    }

    #[Test]
    public function a_consented_current_version_is_excluded()
    {
        $first = $this->makeSop('First', 'a', 10);
        $this->makeSop('Second', 'b', 20);

        $user = $this->makeUser();
        $this->consent($user, $first);

        $this->assertSame(['Second'], $this->status->pendingFor($user)->pluck('title')->all());

        // Still part of the denominator.
        $this->assertSame(2, $this->status->totalRequiredCount());
    }

    #[Test]
    public function a_consent_by_another_user_does_not_count()
    {
        $sop = $this->makeSop('First', 'a');

        $other = $this->makeUser();
        $this->consent($other, $sop);

        $user = $this->makeUser();

        $this->assertSame(['First'], $this->status->pendingFor($user)->pluck('title')->all());
    }

    #[Test]
    public function a_consent_to_an_old_version_leaves_the_sop_pending()
    {
        $sop = $this->makeSop('First', 'Original');

        $user = $this->makeUser();
        $this->consent($user, $sop);

        $this->assertCount(0, $this->status->pendingFor($user));

        app(SopPublisher::class)->update($sop, ['content' => 'Rewritten']);

        $this->status->forget();

        $pending = $this->status->pendingFor($user);

        $this->assertCount(1, $pending);
        $this->assertSame('Rewritten', $pending->first()->currentVersion->content);
    }

    #[Test]
    public function inactive_soft_deleted_and_versionless_sops_are_ignored()
    {
        $this->makeSop('Active', 'a', 10);
        $this->makeSop('Inactive', 'b', 20, active: false);

        $deleted = $this->makeSop('Deleted', 'c', 30);
        $deleted->delete();

        // A row that was never published through SopPublisher.
        \TakepartMedia\StatamicSop\Models\Sop::create([
            'title' => 'Versionless',
            'active' => true,
            'sort_order' => 40,
        ]);

        $user = $this->makeUser();

        $this->assertSame(['Active'], $this->status->pendingFor($user)->pluck('title')->all());
        $this->assertSame(1, $this->status->totalRequiredCount());
    }

    #[Test]
    public function the_result_is_memoised_until_forgotten()
    {
        $this->makeSop('First', 'a');

        $user = $this->makeUser();

        $this->assertCount(1, $this->status->pendingFor($user));
        $this->assertSame(1, $this->status->totalRequiredCount());

        $this->makeSop('Second', 'b');

        // Memo still holds the old answer.
        $this->assertCount(1, $this->status->pendingFor($user));
        $this->assertSame(1, $this->status->totalRequiredCount());

        $this->status->forget();

        $this->assertCount(2, $this->status->pendingFor($user));
        $this->assertSame(2, $this->status->totalRequiredCount());
    }

    #[Test]
    public function the_memo_is_keyed_per_user()
    {
        $sop = $this->makeSop('First', 'a');

        $consented = $this->makeUser();
        $this->consent($consented, $sop);

        $fresh = $this->makeUser();

        $this->assertCount(0, $this->status->pendingFor($consented));
        $this->assertCount(1, $this->status->pendingFor($fresh));
    }

    #[Test]
    public function there_is_nothing_pending_without_a_user()
    {
        $this->makeSop('First', 'a');

        $this->assertCount(0, $this->status->pendingFor(null));
        $this->assertNull($this->status->firstPendingFor(null));
    }

    #[Test]
    public function the_current_version_is_eager_loaded()
    {
        $this->makeSop('First', 'Body');

        $sop = $this->status->firstPendingFor($this->makeUser());

        $this->assertTrue($sop->relationLoaded('currentVersion'));
        $this->assertSame('Body', $sop->currentVersion->content);
    }
}
