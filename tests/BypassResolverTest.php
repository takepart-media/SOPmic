<?php

namespace TakepartMedia\StatamicSop\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use TakepartMedia\StatamicSop\Support\BypassResolver;

class BypassResolverTest extends TestCase
{
    private BypassResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(BypassResolver::class);

        $this->setTestRoles(['editor' => [], 'compliance' => []]);
        $this->setTestUserGroups(['staff' => [], 'auditors' => ['compliance']]);
    }

    #[Test]
    public function a_plain_user_is_not_bypassed()
    {
        $this->assertFalse($this->resolver->isBypassed($this->makeUser()));
    }

    #[Test]
    public function no_user_is_not_bypassed()
    {
        $this->assertFalse($this->resolver->isBypassed(null));
    }

    #[Test]
    public function a_super_admin_is_bypassed()
    {
        $this->assertTrue($this->resolver->isBypassed($this->makeSuper()));
    }

    #[Test]
    public function a_configured_role_is_bypassed()
    {
        config(['sop.bypass.roles' => ['compliance']]);

        $this->assertFalse($this->resolver->isBypassed($this->makeUser(['roles' => ['editor']])));
        $this->assertTrue($this->resolver->isBypassed($this->makeUser(['roles' => ['compliance']])));
    }

    #[Test]
    public function a_configured_group_is_bypassed()
    {
        config(['sop.bypass.groups' => ['auditors']]);

        $this->assertFalse($this->resolver->isBypassed($this->makeUser(['groups' => ['staff']])));
        $this->assertTrue($this->resolver->isBypassed($this->makeUser(['groups' => ['auditors']])));
    }

    #[Test]
    public function a_role_inherited_through_a_group_is_bypassed()
    {
        config(['sop.bypass.roles' => ['compliance']]);

        // The "auditors" group carries the "compliance" role.
        $this->assertTrue($this->resolver->isBypassed($this->makeUser(['groups' => ['auditors']])));
    }

    #[Test]
    public function it_never_touches_the_sop_database()
    {
        config(['sop.bypass.roles' => ['compliance'], 'sop.bypass.groups' => ['auditors']]);

        $queries = 0;
        DB::connection('sop')->listen(function () use (&$queries) {
            $queries++;
        });

        $this->resolver->isBypassed($this->makeUser());
        $this->resolver->isBypassed($this->makeSuper());
        $this->resolver->isBypassed($this->makeUser(['roles' => ['compliance']]));
        $this->resolver->isBypassed($this->makeUser(['groups' => ['auditors']]));

        $this->assertSame(0, $queries);
    }
}
