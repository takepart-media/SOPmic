<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\Role;
use Statamic\Facades\UserGroup;
use TakepartMedia\StatamicSop\Models\Sop;

/**
 * The exclusion fields on the SOP form, driven through HTTP: persistence,
 * server-side sanitizing, the "empty multi-select sends no key" semantics, and
 * the rendering of the comboboxes and the show card. Whether an exclusion
 * actually waives an SOP is ExclusionTest's job.
 *
 * SOPs are inactive so they never gate the manager driving the requests.
 */
class SopExclusionCrudTest extends TestCase
{
    private UserContract $manager;

    private UserContract $alice;

    private UserContract $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles([
            'sop_admin' => ['access cp', 'manage sops'],
            'editors' => ['access cp'],
            'reviewers' => ['access cp'],
        ]);

        $this->setTestUserGroups([
            'staff' => ['editors'],
            'contractors' => [],
        ]);

        $this->manager = $this->makeUser(['roles' => ['sop_admin'], 'email' => 'manager@example.com']);
        $this->alice = $this->makeUser(['email' => 'alice@example.com']);
        $this->bob = $this->makeUser(['email' => 'bob@example.com']);
    }

    private function form(array $extra = []): array
    {
        return [
            'title' => 'Safety',
            'content' => 'Read me.',
            ...$extra,
        ];
    }

    #[Test]
    public function storing_persists_the_selected_exclusions()
    {
        $this->actingAs($this->manager)->post(cp_route('sop.store'), $this->form([
            'excluded_users' => [$this->alice->id(), $this->bob->id()],
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['contractors'],
        ]))->assertRedirect(cp_route('sop.index'));

        $sop = Sop::query()->where('title', 'Safety')->firstOrFail();

        $this->assertSame([$this->alice->id(), $this->bob->id()], $sop->excluded_users);
        $this->assertSame(['editors'], $sop->excluded_roles);
        $this->assertSame(['contractors'], $sop->excluded_groups);
        $this->assertSame(1, $sop->versions()->count());
    }

    #[Test]
    public function storing_without_exclusions_applies_to_everyone()
    {
        $this->actingAs($this->manager)->post(cp_route('sop.store'), $this->form())
            ->assertRedirect(cp_route('sop.index'));

        $sop = Sop::query()->where('title', 'Safety')->firstOrFail();

        $this->assertNull($sop->excluded_users);
        $this->assertNull($sop->excluded_roles);
        $this->assertNull($sop->excluded_groups);
    }

    #[Test]
    public function updating_changes_the_exclusions_without_a_new_version()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false, extra: [
            'excluded_roles' => ['editors'],
        ]);

        $this->actingAs($this->manager)->patch(cp_route('sop.update', $sop), $this->form([
            'excluded_users' => [$this->alice->id()],
            'excluded_roles' => ['reviewers'],
            'excluded_groups' => ['staff'],
        ]))->assertRedirect(cp_route('sop.index'));

        $sop->refresh();

        $this->assertSame([$this->alice->id()], $sop->excluded_users);
        $this->assertSame(['reviewers'], $sop->excluded_roles);
        $this->assertSame(['staff'], $sop->excluded_groups);
        $this->assertSame(1, $sop->versions()->count());
    }

    #[Test]
    public function a_full_form_submit_without_selections_clears_the_exclusions()
    {
        // An empty multi-select posts no key at all — like the unchecked
        // `active` box, that has to mean "cleared", not "leave untouched".
        $sop = $this->makeSop('Safety', 'Read me.', active: false, extra: [
            'excluded_users' => [$this->alice->id()],
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['staff'],
        ]);

        $this->actingAs($this->manager)->patch(cp_route('sop.update', $sop), $this->form())
            ->assertRedirect(cp_route('sop.index'));

        $sop->refresh();

        $this->assertNull($sop->excluded_users);
        $this->assertNull($sop->excluded_roles);
        $this->assertNull($sop->excluded_groups);
    }

    #[Test]
    public function an_explicitly_empty_exclusion_field_clears_the_exclusions()
    {
        // The combobox's empty state posts `excluded_x=""` (a string, not an
        // array) so that "cleared" is distinguishable from "not submitted".
        $sop = $this->makeSop('Safety', 'Read me.', active: false, extra: [
            'excluded_users' => [$this->alice->id()],
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['staff'],
        ]);

        $this->actingAs($this->manager)->patch(cp_route('sop.update', $sop), $this->form([
            'excluded_users' => '',
            'excluded_roles' => '',
            'excluded_groups' => '',
        ]))->assertRedirect(cp_route('sop.index'))->assertSessionHasNoErrors();

        $sop->refresh();

        $this->assertNull($sop->excluded_users);
        $this->assertNull($sop->excluded_roles);
        $this->assertNull($sop->excluded_groups);
    }

    #[Test]
    public function empty_and_filled_exclusion_fields_can_be_mixed()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false, extra: [
            'excluded_users' => [$this->alice->id()],
            'excluded_roles' => ['editors'],
        ]);

        $this->actingAs($this->manager)->patch(cp_route('sop.update', $sop), $this->form([
            'excluded_users' => '',
            'excluded_roles' => ['reviewers'],
            'excluded_groups' => ['staff', 'contractors'],
        ]))->assertRedirect(cp_route('sop.index'))->assertSessionHasNoErrors();

        $sop->refresh();

        $this->assertNull($sop->excluded_users);
        $this->assertSame(['reviewers'], $sop->excluded_roles);
        $this->assertSame(['staff', 'contractors'], $sop->excluded_groups);
    }

    #[Test]
    public function unknown_users_roles_and_groups_are_dropped_silently()
    {
        $this->actingAs($this->manager)->post(cp_route('sop.store'), $this->form([
            'excluded_users' => [$this->alice->id(), 'ghost-user'],
            'excluded_roles' => ['editors', 'no_such_role'],
            'excluded_groups' => ['no_such_group'],
        ]))->assertRedirect(cp_route('sop.index'));

        $sop = Sop::query()->where('title', 'Safety')->firstOrFail();

        $this->assertSame([$this->alice->id()], $sop->excluded_users);
        $this->assertSame(['editors'], $sop->excluded_roles);
        $this->assertNull($sop->excluded_groups);
    }

    #[Test]
    public function non_array_exclusions_are_rejected()
    {
        $this->actingAs($this->manager)
            ->post(cp_route('sop.store'), $this->form(['excluded_roles' => 'editors']))
            ->assertRedirect()
            ->assertSessionHasErrors(['excluded_roles']);

        $this->assertSame(0, Sop::query()->count());
    }

    #[Test]
    public function the_create_form_renders_the_exclusion_comboboxes()
    {
        $html = $this->actingAs($this->manager)
            ->get(cp_route('sop.create'))
            ->assertOk()
            ->assertSee(__('sop::messages.crud.form.exclusions_heading'))
            ->assertSee(__('sop::messages.crud.form.exclusions_hint'))
            ->assertSee('<sop-multi-select', false)
            ->assertSee('name="excluded_users"', false)
            ->assertSee('name="excluded_roles"', false)
            ->assertSee('name="excluded_groups"', false)
            ->assertSee('placeholder="'.e(__('sop::messages.crud.form.exclusions_placeholder')).'"', false)
            ->assertDontSee('<select', false)
            ->getContent();

        $users = $this->propFor($html, 'excluded_users', 'options');
        $roles = $this->propFor($html, 'excluded_roles', 'options');
        $groups = $this->propFor($html, 'excluded_groups', 'options');

        // The <ui-combobox> option shape, values as strings, sorted by label.
        $this->assertContains(['value' => (string) $this->alice->id(), 'label' => 'alice@example.com'], $users);
        $this->assertSame(
            ['alice@example.com', 'bob@example.com', 'manager@example.com'],
            array_column($users, 'label')
        );
        $this->assertContains(['value' => 'editors', 'label' => Role::find('editors')->title()], $roles);
        $this->assertContains(['value' => 'contractors', 'label' => UserGroup::find('contractors')->title()], $groups);

        foreach ([$users, $roles, $groups] as $options) {
            foreach ($options as $option) {
                $this->assertIsString($option['value']);
                $this->assertIsString($option['label']);
            }
        }

        $this->assertSame([], $this->propFor($html, 'excluded_users', 'initial'));
    }

    #[Test]
    public function the_edit_form_preselects_the_stored_exclusions()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false, extra: [
            'excluded_users' => [$this->alice->id()],
            'excluded_roles' => ['editors'],
        ]);

        $html = $this->actingAs($this->manager)
            ->get(cp_route('sop.edit', $sop))
            ->assertOk()
            ->assertSee('name="excluded_groups"', false)
            ->getContent();

        $this->assertSame([(string) $this->alice->id()], $this->propFor($html, 'excluded_users', 'initial'));
        $this->assertSame(['editors'], $this->propFor($html, 'excluded_roles', 'initial'));
        $this->assertSame([], $this->propFor($html, 'excluded_groups', 'initial'));
    }

    #[Test]
    public function a_failed_submit_redisplays_the_posted_exclusions()
    {
        $this->actingAs($this->manager)
            ->from(cp_route('sop.create'))
            ->post(cp_route('sop.store'), [
                'title' => '',
                'content' => 'Read me.',
                'excluded_roles' => ['editors'],
                'excluded_groups' => '',
            ])
            ->assertRedirect(cp_route('sop.create'))
            ->assertSessionHasErrors(['title']);

        $html = $this->actingAs($this->manager)
            ->get(cp_route('sop.create'))
            ->assertOk()
            ->getContent();

        $this->assertSame(['editors'], $this->propFor($html, 'excluded_roles', 'initial'));
        $this->assertSame([], $this->propFor($html, 'excluded_groups', 'initial'));
    }

    #[Test]
    public function an_empty_choice_list_renders_a_hint_instead_of_an_empty_combobox()
    {
        $this->setTestUserGroups([]);

        $this->actingAs($this->manager)
            ->get(cp_route('sop.create'))
            ->assertOk()
            ->assertDontSee('name="excluded_groups"', false)
            ->assertSee(__('sop::messages.crud.form.none_available'))
            ->assertSee('name="excluded_roles"', false);
    }

    #[Test]
    public function the_show_page_lists_the_exclusions_by_email_and_title()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false, extra: [
            'excluded_users' => [$this->alice->id()],
            'excluded_roles' => ['editors'],
            'excluded_groups' => ['contractors'],
        ]);

        $this->actingAs($this->manager)
            ->get(cp_route('sop.show', $sop))
            ->assertOk()
            ->assertSee('<h2 class="mb-2 text-lg font-medium">'.__('sop::messages.crud.show.exclusions').'</h2>', false)
            ->assertSee('alice@example.com')
            ->assertSee(Role::find('editors')->title())
            ->assertSee(UserGroup::find('contractors')->title());
    }

    #[Test]
    public function the_show_page_has_no_exclusions_card_when_nothing_is_excluded()
    {
        $sop = $this->makeSop('Safety', 'Read me.', active: false);

        $this->actingAs($this->manager)
            ->get(cp_route('sop.show', $sop))
            ->assertOk()
            // The heading markup, not the bare string: Statamic dumps every
            // translation into the page's JS config.
            ->assertDontSee('<h2 class="mb-2 text-lg font-medium">'.__('sop::messages.crud.show.exclusions').'</h2>', false);
    }

    /**
     * Decodes the `:prop="..."` binding of the <sop-multi-select> for the
     * given field. Js::from() emits either a bare `[]` or
     * `JSON.parse('<JSON re-encoded as a JS string literal>')`.
     */
    private function propFor(string $html, string $field, string $prop): array
    {
        $pattern = '/<sop-multi-select\s[^>]*name="'.preg_quote($field, '/').'"[^>]*\s:'.preg_quote($prop, '/').'="(\[\]|JSON\.parse\(\'(.*?)\'\))"/s';

        $this->assertMatchesRegularExpression($pattern, $html, "No :{$prop} binding found for {$field}.");

        preg_match($pattern, $html, $matches);

        if ($matches[1] === '[]') {
            return [];
        }

        $json = json_decode('"'.$matches[2].'"', flags: JSON_THROW_ON_ERROR);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
