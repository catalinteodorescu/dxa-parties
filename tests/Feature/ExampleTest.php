<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    // „/” nu randează un răspuns propriu: redirecționează mereu spre login (sau dashboard, cu sesiune de admin).
    $response->assertRedirect(route('admin.login'));
});
