<?php

declare(strict_types=1);

test('l\'indirizzo dell\'app porta alla Home, e chi non è entrato ad Accedi', function (): void {
    $this->get(route('home'))->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('login'));
});
