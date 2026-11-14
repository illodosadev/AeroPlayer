<?php

// spotify-search-page, task 1.1: "GET /search" for the owner, with and without Spotify, and "A visitor without a
// session" (design D1).

use App\Models\SpotifyConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake();
});

test('the owner with Spotify connected gets the search page', function () {
    $owner = SpotifyConnection::factory()->create()->user;

    $this->actingAs($owner)
        ->get('/search')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('spotify.status', 'connected'));

    Http::assertNothingSent();
});

test('the owner without Spotify still gets the page, with status 200', function () {
    $this->actingAs(User::factory()->create())
        ->get('/search')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('spotify.status', 'not_connected'));

    Http::assertNothingSent();
});

test('A visitor without a session', function () {
    $this->get('/search')->assertRedirect('/login');

    Http::assertNothingSent();
});
