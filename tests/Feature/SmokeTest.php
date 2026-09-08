<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create([
        'username' => 'tester',
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $this->admin->assignRole('admin');
});

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('shows the login page', function () {
    $this->get('/login')->assertOk();
});

/**
 * Every authenticated GET route should render. This catches the class of bug
 * that only shows up at runtime: a missing Vue page, a bad relation name, a
 * scope that collides with an Eloquent built-in.
 */
it('renders every main screen', function (string $uri) {
    $this->actingAs($this->admin)->get($uri)->assertOk();
})->with([
    '/',
    '/patients',
    '/patients/create',
    '/treatments',
    '/payments',
    '/images',
    '/prescriptions',
    '/appointments',
    '/stock',
    '/sms',
    '/reports',
    '/catalog/treatments',
    '/catalog/drugs',
    '/catalog/insurances',
    '/catalog/payment-types',
    '/users',
    '/users/activity',
    '/profile',
]);

it('serves the JSON lookups', function (string $uri) {
    $this->actingAs($this->admin)->get($uri)->assertOk()->assertJson([]);
})->with([
    '/lookups/services',
    '/lookups/payment-types',
    '/lookups/drug-variants',
]);
