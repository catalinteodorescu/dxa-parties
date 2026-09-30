<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    // „/” e acasa aplicației participanților (publică, fără cont); admin-ul rămâne la /admin.
    $this->get('/')->assertOk();
    $this->get('/admin')->assertRedirect(route('admin.login'));
});
