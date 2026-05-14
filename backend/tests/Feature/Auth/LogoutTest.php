<?php

use App\Models\Account;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Cache::flush();
});

test('guest cannot logout', function (): void {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

test('authenticated user receives no content on logout', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($account, 'web');

    $this->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();
});
