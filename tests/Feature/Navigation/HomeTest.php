<?php

declare(strict_types=1);

test('the address of the app leads to the Home, and a guest to Sign in', function (): void {
    $this->get(route('home'))->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('login'));
});
