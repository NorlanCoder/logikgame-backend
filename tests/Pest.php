<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create an admin and return its Sanctum token.
 *
 * @return array{admin: \App\Models\Admin, token: string}
 */
function createAdminWithToken(array $attributes = []): array
{
    $admin = \App\Models\Admin::factory()->create($attributes);
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    return ['admin' => $admin, 'token' => $token];
}

/**
 * Create a full session setup (admin + session + 8 rounds).
 *
 * @return array{admin: \App\Models\Admin, token: string, session: \App\Models\Session, rounds: array<int, \App\Models\SessionRound>}
 */
function createSessionSetup(array $sessionAttributes = []): array
{
    ['admin' => $admin, 'token' => $token] = createAdminWithToken();

    $session = \App\Models\Session::factory()->create(array_merge(
        ['admin_id' => $admin->id],
        $sessionAttributes
    ));

    $roundDefs = [
        1 => ['type' => \App\Enums\RoundType::SuddenDeath, 'name' => 'Mort subite'],
        2 => ['type' => \App\Enums\RoundType::Hint, 'name' => 'Indice'],
        3 => ['type' => \App\Enums\RoundType::SecondChance, 'name' => 'Seconde chance'],
        4 => ['type' => \App\Enums\RoundType::RoundSkip, 'name' => 'Passage'],
        5 => ['type' => \App\Enums\RoundType::Top4Elimination, 'name' => 'Top 4'],
        6 => ['type' => \App\Enums\RoundType::DuelJackpot, 'name' => 'Duel Cagnotte'],
        7 => ['type' => \App\Enums\RoundType::DuelElimination, 'name' => 'Duel Élim'],
        8 => ['type' => \App\Enums\RoundType::Finale, 'name' => 'Finale'],
    ];

    $rounds = [];
    foreach ($roundDefs as $num => $def) {
        $rounds[$num] = \App\Models\SessionRound::factory()->create([
            'session_id' => $session->id,
            'round_number' => $num,
            'round_type' => $def['type'],
            'name' => $def['name'],
            'is_active' => true,
            'display_order' => $num,
        ]);
    }

    return ['admin' => $admin, 'token' => $token, 'session' => $session, 'rounds' => $rounds];
}
