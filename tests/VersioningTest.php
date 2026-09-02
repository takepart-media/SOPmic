<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use TakepartMedia\StatamicSop\Models\SopVersion;
use TakepartMedia\StatamicSop\Support\SopPublisher;

class VersioningTest extends TestCase
{
    private SopPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publisher = app(SopPublisher::class);
    }

    #[Test]
    public function creating_an_sop_writes_version_one_and_points_at_it()
    {
        $sop = $this->publisher->create([
            'title' => 'Fire drill',
            'content' => 'Walk, do not run.',
            'active' => true,
            'sort_order' => 3,
        ], 'user-abc');

        $this->assertSame('Fire drill', $sop->title);
        $this->assertTrue($sop->active);
        $this->assertSame(3, $sop->sort_order);

        $this->assertSame(1, $sop->versions()->count());

        $version = $sop->versions()->first();

        $this->assertSame(1, $version->version_no);
        $this->assertSame('Fire drill', $version->title);
        $this->assertSame('Walk, do not run.', $version->content);
        $this->assertSame('user-abc', $version->created_by);
        $this->assertNotNull($version->created_at);

        $this->assertSame($version->id, $sop->current_version_id);
        $this->assertSame(
            hash('sha256', "Fire drill\0Walk, do not run."),
            $version->content_hash
        );
        $this->assertSame(SopVersion::hash('Fire drill', 'Walk, do not run.'), $version->content_hash);
    }

    #[Test]
    public function updating_without_content_changes_does_not_bump_the_version()
    {
        $sop = $this->publisher->create(['title' => 'A', 'content' => 'Body'], 'author');

        $originalVersionId = $sop->current_version_id;

        $this->publisher->update($sop, [
            'title' => 'A',
            'content' => 'Body',
            'active' => true,
            'sort_order' => 9,
        ], 'editor');

        $sop->refresh();

        $this->assertSame(1, $sop->versions()->count());
        $this->assertSame($originalVersionId, $sop->current_version_id);

        // The non-versioned fields still moved.
        $this->assertTrue($sop->active);
        $this->assertSame(9, $sop->sort_order);
    }

    #[Test]
    public function changing_the_content_creates_a_new_version_and_repoints()
    {
        $sop = $this->publisher->create(['title' => 'A', 'content' => 'Old body'], 'author');

        $first = $sop->currentVersion;

        $this->publisher->update($sop, ['content' => 'New body'], 'editor');

        $sop->refresh();

        $this->assertSame(2, $sop->versions()->count());

        $second = $sop->currentVersion;

        $this->assertSame(2, $second->version_no);
        $this->assertSame('New body', $second->content);
        $this->assertSame('A', $second->title);
        $this->assertSame('editor', $second->created_by);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($second->id, $sop->current_version_id);

        // The old row is untouched — consents pointing at it must still read
        // the text that was agreed to.
        $first->refresh();
        $this->assertSame('Old body', $first->content);
        $this->assertSame(1, $first->version_no);
        $this->assertSame('author', $first->created_by);
    }

    #[Test]
    public function changing_only_the_title_also_bumps_the_version()
    {
        $sop = $this->publisher->create(['title' => 'Old title', 'content' => 'Body'], 'author');

        $this->publisher->update($sop, ['title' => 'New title'], 'editor');

        $sop->refresh();

        $this->assertSame(2, $sop->versions()->count());
        $this->assertSame('New title', $sop->currentVersion->title);
        $this->assertSame('Body', $sop->currentVersion->content);

        // Denormalised copy on the sops row follows along.
        $this->assertSame('New title', $sop->title);
    }

    #[Test]
    public function version_numbers_keep_climbing()
    {
        $sop = $this->publisher->create(['title' => 'A', 'content' => 'v1']);

        foreach (['v2', 'v3', 'v4'] as $content) {
            $this->publisher->update($sop, ['content' => $content]);
        }

        $sop->refresh();

        $this->assertSame(4, $sop->versions()->count());
        $this->assertSame(4, $sop->currentVersion->version_no);
        $this->assertSame(
            [1, 2, 3, 4],
            $sop->versions()->orderBy('version_no')->pluck('version_no')->all()
        );
    }

    #[Test]
    public function an_update_that_omits_content_keeps_the_current_text()
    {
        $sop = $this->publisher->create(['title' => 'A', 'content' => 'Body']);

        $this->publisher->update($sop, ['active' => true]);

        $sop->refresh();

        $this->assertSame(1, $sop->versions()->count());
        $this->assertSame('Body', $sop->currentVersion->content);
    }
}
