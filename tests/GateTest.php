<?php

namespace TakepartMedia\StatamicSop\Tests;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use TakepartMedia\StatamicSop\Http\Middleware\EnforceSopConsent;

class GateTest extends TestCase
{
    private UserContract $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles([
            'cp' => ['access cp'],
            'outsider' => [],
        ]);

        $this->user = $this->makeUser(['roles' => ['cp']]);
    }

    #[Test]
    public function it_redirects_every_gated_cp_screen_to_the_consent_page()
    {
        $this->makeSop('Safety', 'Read me.');

        $super = $this->makeSuper();

        foreach (['dashboard', 'collections.index', 'users.index'] as $route) {
            // The super proves the route is reachable at all, so a redirect
            // below can only be the gate's doing.
            $this->actingAs($super)->get(cp_route($route))->assertOk();

            $this->actingAs($this->user)
                ->get(cp_route($route))
                ->assertRedirect(cp_route('sop.consent'));
        }
    }

    #[Test]
    public function it_sends_the_user_back_to_where_they_were_headed()
    {
        $sop = $this->makeSop('Safety', 'Read me.');

        $this->actingAs($this->user)
            ->get(cp_route('dashboard'))
            ->assertRedirect(cp_route('sop.consent'));

        $this->actingAs($this->user)->get(cp_route('sop.consent'))->assertOk();

        $this->actingAs($this->user)
            ->post(cp_route('sop.consent.store'), [
                'sop_id' => $sop->id,
                'sop_version_id' => $sop->current_version_id,
                'confirmed' => '1',
            ])
            ->assertRedirect(cp_route('dashboard'));

        $this->actingAs($this->user)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function a_blocked_non_get_request_is_not_remembered_as_the_intended_url()
    {
        $this->makeSop('Safety', 'Read me.');

        $this->actingAs($this->user)
            ->post(cp_route('collections.store'))
            ->assertRedirect(cp_route('sop.consent'));

        $this->assertNull(session('url.intended'));
    }

    #[Test]
    public function a_json_request_is_locked_rather_than_redirected()
    {
        $this->makeSop('Safety', 'Read me.');

        $this->actingAs($this->user)
            ->getJson(cp_route('collections.index'))
            ->assertStatus(423)
            ->assertExactJson([
                'message' => __('sop::messages.gate.required'),
                'redirect' => cp_route('sop.consent'),
            ]);
    }

    #[Test]
    public function an_inertia_request_gets_a_location_header()
    {
        $this->makeSop('Safety', 'Read me.');

        $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get(cp_route('collections.index'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', cp_route('sop.consent'));
    }

    #[Test]
    public function the_consent_routes_are_never_gated()
    {
        $sop = $this->makeSop('Safety', 'Read me.');

        $this->actingAs($this->user)->get(cp_route('sop.consent'))->assertOk();

        $this->actingAs($this->user)
            ->post(cp_route('sop.consent.store'), [
                'sop_id' => $sop->id,
                'sop_version_id' => $sop->current_version_id,
                'confirmed' => '1',
            ])
            ->assertRedirect();
    }

    #[Test]
    public function a_user_with_nothing_to_read_is_not_gated()
    {
        $this->actingAs($this->user)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function inactive_sops_do_not_gate()
    {
        $this->makeSop('Draft', 'Not live yet.', active: false);

        $this->actingAs($this->user)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function soft_deleted_sops_do_not_gate()
    {
        $this->makeSop('Retired', 'Gone.')->delete();

        $this->actingAs($this->user)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function the_kill_switch_disables_the_gate()
    {
        $this->makeSop('Safety', 'Read me.');

        config(['sop.enabled' => false]);

        $this->actingAs($this->user)->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function an_anonymous_request_is_left_to_statamics_own_auth()
    {
        $this->makeSop('Safety', 'Read me.');

        $this->get(cp_route('dashboard'))->assertRedirect(cp_route('login'));
    }

    #[Test]
    public function a_user_without_cp_access_is_left_to_statamics_own_authorization()
    {
        $this->makeSop('Safety', 'Read me.');

        $outsider = $this->makeUser(['roles' => ['outsider']]);

        // Whatever Statamic does with them, it must not be our consent screen.
        $response = $this->actingAs($outsider)->get(cp_route('dashboard'));

        $this->assertNotSame(cp_route('sop.consent'), $response->headers->get('Location'));
    }

    #[Test]
    public function a_broken_database_shows_a_503_instead_of_a_stack_trace()
    {
        $this->makeSop('Safety', 'Read me.');

        $this->dropSopTables();

        $this->actingAs($this->user)
            ->get(cp_route('dashboard'))
            ->assertStatus(503)
            ->assertViewIs('sop::unavailable')
            ->assertSee(cp_route('logout'), false);
    }

    #[Test]
    public function a_broken_database_never_locks_out_a_bypassed_user()
    {
        $this->makeSop('Safety', 'Read me.');

        $this->dropSopTables();

        $this->actingAs($this->makeSuper())->get(cp_route('dashboard'))->assertOk();
    }

    #[Test]
    public function the_gate_is_the_first_middleware_in_the_authenticated_group()
    {
        $group = app('router')->getMiddlewareGroups()['statamic.cp.authenticated'];

        $this->assertSame(EnforceSopConsent::class, $group[0]);
    }

    #[Test]
    public function the_gate_runs_before_middleware_pushed_by_other_addons()
    {
        // Pushing after boot is exactly what another addon's bootMiddleware()
        // does; the group is only resolved at dispatch, so this is faithful.
        app('router')->pushMiddlewareToGroup('statamic.cp.authenticated', GateSpyMiddleware::class);

        GateSpyMiddleware::$ran = false;

        // Control: with nothing pending the spy is reached, so a miss below
        // means the gate stopped the chain, not that the spy was never wired.
        $this->actingAs($this->user)->get(cp_route('dashboard'))->assertOk();
        $this->assertTrue(GateSpyMiddleware::$ran);

        $this->makeSop('Safety', 'Read me.');
        $this->startFreshRequestScope();

        GateSpyMiddleware::$ran = false;

        $this->actingAs($this->user)
            ->get(cp_route('dashboard'))
            ->assertRedirect(cp_route('sop.consent'));

        $this->assertFalse(GateSpyMiddleware::$ran);
    }

    private function dropSopTables(): void
    {
        $schema = Schema::connection('sop');

        // Children first: the connection runs with foreign keys on.
        $schema->drop('sop_consents');
        $schema->drop('sop_versions');
        $schema->drop('sops');
    }
}

/**
 * Stands in for a middleware another addon appended to the CP group.
 */
class GateSpyMiddleware
{
    public static bool $ran = false;

    public function handle(Request $request, Closure $next)
    {
        static::$ran = true;

        return $next($request);
    }
}
