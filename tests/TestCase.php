<?php

namespace TakepartMedia\StatamicSop\Tests;

use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\FakesUserGroups;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use TakepartMedia\StatamicSop\Models\Sop;
use TakepartMedia\StatamicSop\ServiceProvider;
use TakepartMedia\StatamicSop\Support\SopPublisher;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles;
    use FakesUserGroups;
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    private static int $userCounter = 0;

    /**
     * The single place that decides where the test SOP database lives.
     *
     * `:memory:` is fine as long as nothing purges the connection — Laravel
     * keeps the resolved PDO for the life of the application, and every test
     * gets a fresh application. If that ever turns flaky, return a tempnam()
     * path here and delete it in tearDown(); nothing else needs to change.
     */
    protected function sopDatabasePath(): string
    {
        return ':memory:';
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // The whole array, not just the `database` key: mergeConfigFrom() is a
        // shallow array_merge, so a partial override here would drop `driver`.
        $app['config']->set('sop.database', [
            'driver' => 'sqlite',
            'database' => $this->sopDatabasePath(),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('database.connections.sop', $app['config']->get('sop.database'));

        // Statamic's CountUsers middleware throws on a second user without Pro,
        // and the gate matrix needs several users per request cycle.
        $app['config']->set('statamic.editions.pro', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateSopDatabase();
    }

    protected function migrateSopDatabase(): void
    {
        $this->artisan('migrate', [
            '--database' => 'sop',
            '--path' => __DIR__.'/../database/migrations',
            '--realpath' => true,
        ])->run();
    }

    protected function makeUser(array $data = []): UserContract
    {
        $n = ++self::$userCounter;

        $user = User::make()
            ->id($data['id'] ?? "user-{$n}")
            ->email($data['email'] ?? "user{$n}@example.com");

        if ($roles = $data['roles'] ?? null) {
            $user->explicitRoles($roles);
        }

        if ($groups = $data['groups'] ?? null) {
            $user->groups($groups);
        }

        $user->save();

        return $user;
    }

    protected function makeSuper(array $data = []): UserContract
    {
        $user = $this->makeUser($data);

        $user->makeSuper()->save();

        return $user;
    }

    /**
     * A test drives several requests through one container, where production
     * builds a fresh one per request. SopStatus is a scoped binding, so its
     * per-request memo outlives a test request unless it is dropped — call
     * this after changing SOPs between two requests.
     */
    protected function startFreshRequestScope(): void
    {
        $this->app->forgetScopedInstances();
    }

    protected function makeSop(string $title, string $content, int $sortOrder = 0, bool $active = true): Sop
    {
        return app(SopPublisher::class)->create([
            'title' => $title,
            'content' => $content,
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);
    }
}
