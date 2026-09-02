<?php

namespace TakepartMedia\StatamicSop\Tests;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;

/**
 * Guards two things: that the consent screen actually renders in the app's
 * current locale (not just that the language files exist), and that the two
 * language files never drift apart. The latter is what stops a translator —
 * or a future PR — from adding a key to one file and forgetting the other.
 */
class LocalizationTest extends TestCase
{
    private UserContract $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles([
            'cp' => ['access cp'],
        ]);

        $this->user = $this->makeUser(['roles' => ['cp']]);
    }

    #[Test]
    public function the_consent_page_renders_in_german_when_the_app_locale_is_german()
    {
        // Statamic's CP Localize middleware falls back to app()->getLocale()
        // whenever the user has no explicit locale preference set, which is
        // the case for a freshly made test user.
        app()->setLocale('de');

        $this->makeSop('Safety', 'Read me.');

        $this->actingAs($this->user)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertSee(__('sop::messages.consent.confirm', [], 'de'))
            ->assertDontSee(__('sop::messages.consent.confirm', [], 'en'));
    }

    #[Test]
    public function the_consent_page_renders_in_english_when_the_app_locale_is_english()
    {
        app()->setLocale('en');

        $this->makeSop('Safety', 'Read me.');

        $this->actingAs($this->user)
            ->get(cp_route('sop.consent'))
            ->assertOk()
            ->assertSee(__('sop::messages.consent.confirm', [], 'en'))
            ->assertDontSee(__('sop::messages.consent.confirm', [], 'de'));
    }

    #[Test]
    public function the_german_and_english_language_files_declare_the_same_keys()
    {
        $de = require __DIR__.'/../lang/de/messages.php';
        $en = require __DIR__.'/../lang/en/messages.php';

        $this->assertSame(
            $this->flattenKeys($en),
            $this->flattenKeys($de),
            'lang/de/messages.php and lang/en/messages.php must declare exactly the same set of keys.'
        );
    }

    /**
     * Recursively reduces a nested translation array to a sorted list of its
     * dot-notation keys, ignoring the translated values entirely — this test
     * only cares that both files define the same set of translatable strings.
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }
}
