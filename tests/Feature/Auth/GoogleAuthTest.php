<?php

use App\Models\Budget;
use App\Models\User;
use App\Services\FamilyBudgetProvisioner;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Simula la respuesta de Socialite para un usuario de Google dado.
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
    config()->set('budget.allowed_emails', ['tu-email@gmail.com', 'pareja@gmail.com']);
});

it('reconoce solo los emails de la whitelist', function () {
    expect(FamilyBudgetProvisioner::isAllowed('tu-email@gmail.com'))->toBeTrue()
        ->and(FamilyBudgetProvisioner::isAllowed('OWNER@EXAMPLE.COM'))->toBeTrue()
        ->and(FamilyBudgetProvisioner::isAllowed('intruso@gmail.com'))->toBeFalse();
});

it('aprovisiona al primer email de la whitelist como admin del presupuesto familiar', function () {
    $user = app(FamilyBudgetProvisioner::class)->provision([
        'name' => 'Fede',
        'email' => 'tu-email@gmail.com',
        'google_id' => 'g-1',
        'avatar' => null,
    ]);

    expect(Budget::count())->toBe(1);
    expect($user->budgets()->first()->pivot->role)->toBe('admin');
});

it('aprovisiona a los demás emails como miembros del mismo presupuesto', function () {
    $provisioner = app(FamilyBudgetProvisioner::class);
    $provisioner->provision(['email' => 'tu-email@gmail.com', 'name' => 'Fede']);
    $member = $provisioner->provision(['email' => 'pareja@gmail.com', 'name' => 'Pareja']);

    expect(Budget::count())->toBe(1);
    expect($member->budgets()->first()->pivot->role)->toBe('member');
});

it('loguea a un usuario autorizado y lo manda al presupuesto', function () {
    fakeGoogleUser('tu-email@gmail.com');

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('budget'));
    $this->assertAuthenticated();
    expect(User::where('email', 'tu-email@gmail.com')->exists())->toBeTrue();
});

it('rechaza a un email que no está en la whitelist', function () {
    fakeGoogleUser('intruso@gmail.com');

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
    expect(User::where('email', 'intruso@gmail.com')->exists())->toBeFalse();
});

it('redirige a login cuando un invitado entra al presupuesto', function () {
    $this->get(route('budget'))->assertRedirect(route('login'));
});

it('muestra el dashboard a un usuario autenticado y autorizado', function () {
    $user = app(FamilyBudgetProvisioner::class)->provision([
        'email' => 'tu-email@gmail.com',
        'name' => 'Fede',
    ]);

    $this->actingAs($user)->get(route('budget'))
        ->assertOk()
        ->assertSee('Listo para asignar');
});
