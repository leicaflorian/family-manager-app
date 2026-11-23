<?php

declare(strict_types=1);

use App\Console\Concerns\RunsPerFamily;
use App\Models\Family;
use App\Support\CurrentFamily;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->families = Family::factory()->count(3)->create();
    $this->blocked = Family::factory()->create(['suspended_at' => now()]);
    $this->failing = $this->families[1]->id;

    // A command that counts one for each family it runs in, and fails in the second one
    $command = new #[Signature('test:per-family {--family=}')] class extends Command
    {
        use RunsPerFamily;

        /** @var list<int|null> */
        public static array $seen = [];

        public static int $failing = 0;

        public function handle(): int
        {
            if (! $this->hasValidFamilyOption()) {
                return self::FAILURE;
            }

            return $this->runPerFamily('Things done', static function (Family $family): int {
                self::$seen[] = CurrentFamily::id();

                if ($family->id === self::$failing) {
                    throw new RuntimeException('Broken family');
                }

                return 2;
            });
        }
    };
    $command::$seen = [];
    $command::$failing = $this->failing;
    $this->command = $command;
    resolve(Kernel::class)->registerCommand($command);
});

test('runs inside each family that is not blocked, reports the one that fails and ends with a failure', function (): void {
    Exceptions::fake();

    $this->artisan('test:per-family')
        ->expectsOutput('Things done: 4')
        ->expectsOutput('Families that failed: 1')
        ->assertFailed();

    expect($this->command::$seen)->toBe($this->families->pluck('id')->all());
    Exceptions::assertReported(RuntimeException::class);
});

test('--family runs only that family and ends well', function (): void {
    $this->artisan('test:per-family', ['--family' => $this->families[2]->id])
        ->expectsOutput('Things done: 2')
        ->doesntExpectOutput('Families that failed: 0')
        ->assertSuccessful();

    expect($this->command::$seen)->toBe([$this->families[2]->id]);
});

test('a --family that is not an id stops with a message and runs nothing', function (string $value): void {
    $this->artisan('test:per-family', ['--family' => $value])
        ->expectsOutput('--family must be the id of a family.')
        ->assertFailed();

    expect($this->command::$seen)->toBe([]);
})->with(['abc', '0', '-3', '1.5']);
