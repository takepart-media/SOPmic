<?php

namespace TakepartMedia\StatamicSop;

use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use TakepartMedia\StatamicSop\Console\InstallCommand;
use TakepartMedia\StatamicSop\Http\Middleware\EnforceSopConsent;
use TakepartMedia\StatamicSop\Support\SopDatabase;
use TakepartMedia\StatamicSop\Support\SopStatus;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $commands = [
        InstallCommand::class,
    ];

    /**
     * Without this the namespace would default to the composer package name
     * (`statamic-sop::`). The addon slug is what the config key and publish
     * tags already use, so views follow it.
     */
    protected $viewNamespace = 'sop';

    public function register()
    {
        parent::register();

        // Merged explicitly rather than relying on `bootConfig()`, which only
        // runs during boot — the connection below has to exist before anything
        // resolves it.
        $this->mergeConfigFrom(__DIR__.'/../config/sop.php', 'sop');

        // Dedicated, isolated database for SOPs and consents. Pulled from our
        // own config so projects may repoint it at their primary connection.
        config([
            'database.connections.sop' => config('sop.database'),
        ]);

        // Scoped, not a plain resolve: SopStatus memoises the pending-SOP query
        // per user for the length of a request, and the gate plus the consent
        // controller both ask for it. Scoped rather than singleton so the memo
        // cannot survive a request under Octane.
        $this->app->scoped(SopStatus::class);
    }

    public function bootAddon()
    {
        $this->ensureDatabaseExists();

        // The `sop-config` publish tag is already registered by the parent's
        // bootConfig(), which finds config/sop.php by the addon slug.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerGate();
        $this->registerPermissions();
        $this->registerNav();
    }

    private function registerGate(): void
    {
        // Prepend, not push. bootAddon() runs from Statamic::booted(), i.e.
        // after every provider has booted, so array_unshift puts the gate ahead
        // of anything other addons appended — and ahead of Statamic's own
        // Authorize, which the gate compensates for by checking `access cp`
        // itself. Laravel's $middlewarePriority cannot help here: it only
        // reorders middlewares it knows about.
        //
        // Deliberately not via the $middlewareGroups property either — that
        // path only ever pushes, and would additionally register a duplicate.
        $this->app['router']->prependMiddlewareToGroup(
            'statamic.cp.authenticated',
            EnforceSopConsent::class
        );
    }

    private function ensureDatabaseExists(): void
    {
        SopDatabase::ensureExists(config('database.connections.sop.database'));
    }

    /**
     * Super admins get every registered permission automatically, so this is
     * what actually decides whether they can reach the CRUD screens too —
     * nothing extra is needed for them.
     */
    private function registerPermissions(): void
    {
        Permission::group('sop', __('sop::messages.permissions.group'), function () {
            Permission::register('manage sops')->label(__('sop::messages.permissions.manage'));
        });
    }

    /**
     * AddonTestCase mocks Nav::extend, so this is only exercised by hand in a
     * host project — keep it trivial rather than clever.
     */
    private function registerNav(): void
    {
        Nav::extend(function ($nav) {
            $nav->create(__('sop::messages.nav.sops'))
                ->section('Tools')
                ->route('sop.index')
                ->icon('clipboard-check')
                ->can('manage sops');
        });
    }
}
