<?php

use App\Models\Budget;
use App\Models\User;
use App\Services\FamilyBudgetProvisioner;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Simulates the Socialite response for a given Google user.
 */
function fakeGoogleUser(string $email, string $name = 'Fede', string $id = 'g-123'): void
{
    $socialiteUser = (new SocialiteUser)->map([
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'avatar' => 'https://example.com/avatar.png',
    ]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

beforeEach(function () {
    config()->set('budget.allowed_emails', ['owner@example.com', 'member@example.com']);
});

it('recognizes only the whitelist emails', function () {
    expect(FamilyBudgetProvisioner::isAllowed('owner@example.com'))->toBeTrue()
        ->and(FamilyBudgetProvisioner::isAllowed('OWNER@EXAMPLE.COM'))->toBeTrue()
        ->and(FamilyBudgetProvisioner::isAllowed('intruso@gmail.com'))->toBeFalse();
});

it('provisions the first whitelist email as admin of the family budget', function () {
    $user = app(FamilyBudgetProvisioner::class)->provision([
        'name' => 'Fede',
        'email' => 'owner@example.com',
        'google_id' => 'g-1',
        'avatar' => null,
    ]);

    expect(Budget::count())->toBe(1);
    expect($user->budgets()->first()->pivot->role)->toBe('admin');
});

it('provisions the other emails as members of the same budget', function () {
    $provisioner = app(FamilyBudgetProvisioner::class);
    $provisioner->provision(['email' => 'owner@example.com', 'name' => 'Fede']);
    $member = $provisioner->provision(['email' => 'member@example.com', 'name' => 'Pareja']);

    expect(Budget::count())->toBe(1);
    expect($member->budgets()->first()->pivot->role)->toBe('member');
});

it('logs in an authorized user and sends them to the budget', function () {
    fakeGoogleUser('owner@example.com');

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('budget'));
    $this->assertAuthenticated();
    expect(User::where('email', 'owner@example.com')->exists())->toBeTrue();
});

it('rejects an email that is not on the whitelist', function () {
    fakeGoogleUser('intruso@gmail.com');

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
    expect(User::where('email', 'intruso@gmail.com')->exists())->toBeFalse();
});

it('redirects to login when a guest enters the budget', function () {
    $this->get(route('budget'))->assertRedirect(route('login'));
});

it('shows the dashboard to an authenticated and authorized user', function () {
    $user = app(FamilyBudgetProvisioner::class)->provision([
        'email' => 'owner@example.com',
        'name' => 'Fede',
    ]);

    $this->actingAs($user)->get(route('budget'))
        ->assertOk()
        ->assertSee('Listo para asignar');
});
