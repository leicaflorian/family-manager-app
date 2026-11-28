<?php

declare(strict_types=1);

use App\Models\Family;

it('never names a factory family like the seeded Bianchi, which tests look up by name', function (): void {
    // About 1 in 100 Italian last names is Bianchi, so 3000 draws make a collision certain without the guard.
    $names = collect(range(1, 3000))->map(fn (): string => Family::factory()->make()->name);

    expect($names->contains('Bianchi'))->toBeFalse();
});
