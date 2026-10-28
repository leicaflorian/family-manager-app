<?php

declare(strict_types=1);

use App\Actions\Cards\DeleteCard;
use App\Actions\Cards\SaveCard;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Http\Requests\Cards\SaveCardRequest;
use App\Models\Document;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

/**
 * A form request as the controller will receive it, without a route file.
 *
 * @param  string  $type  the `type` of the route
 * @param  array<string, mixed>  $input  what the form sends
 * @param  User  $user  who sends it
 * @param  Model|null  $card  the card of the route, for a change
 */
function cardRequest(string $type, array $input, User $user, ?Model $card = null): SaveCardRequest
{
    $request = SaveCardRequest::create('/cards/'.$type, 'POST', $input);
    $route = new Route('POST', '/cards/{type}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('type', $type);
    if ($card instanceof Model) {
        $route->setParameter('id', $card->getKey());
    }
    $request->setRouteResolver(fn (): Route => $route);
    $request->setUserResolver(fn (): User => $user);
    $request->setContainer(app());
    $request->setRedirector(resolve('redirect'));

    return $request;
}

/**
 * Validates the request like the framework does before the controller.
 */
function validated(SaveCardRequest $request): SaveCardRequest
{
    $request->validateResolved();

    return $request;
}

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->home = Home::factory()->for($this->marco->family)->create(['name' => 'Via Roma 12']);
    $this->panda = Vehicle::factory()->for($this->marco->family)->create(['name' => 'Fiat Panda']);
});

test('it saves a vehicle with the fields of its description', function (): void {
    $request = validated(cardRequest('vehicle', [
        'name' => 'Fiat Panda', 'brand' => 'Fiat', 'model' => 'Panda', 'plate_number' => 'GH 482 TK',
        'registration_year' => 2019, 'is_owned' => true, 'road_tax_due_date' => '2026-11-30',
        'inspection_due_date' => '2027-03-15', 'status' => 'active', 'notes' => 'Blue',
    ], $this->marco));

    $vehicle = resolve(SaveCard::class)->handle($request->cardType(), $request->columns());

    expect($vehicle)->toBeInstanceOf(Vehicle::class)
        ->and($vehicle->exists)->toBeTrue()
        ->and($vehicle->family_id)->toBe($this->marco->family_id)
        ->and($vehicle->plate_number)->toBe('GH 482 TK')
        ->and($vehicle->registration_year)->toBe(2019)
        ->and($vehicle->is_owned)->toBeTrue()
        ->and($vehicle->road_tax_due_date?->format('Y-m-d'))->toBe('2026-11-30')
        ->and($vehicle->inspection_due_date?->format('Y-m-d'))->toBe('2027-03-15');

    $changed = validated(cardRequest('vehicle', ['name' => 'Panda 4x4', 'plate_number' => 'GH 482 TK'], $this->marco, $vehicle));
    $same = resolve(SaveCard::class)->handle($changed->cardType(), $changed->columns(), $vehicle);

    expect($same->is($vehicle))->toBeTrue()
        ->and($vehicle->fresh()?->name)->toBe('Panda 4x4')
        ->and(Vehicle::query()->where('name', 'Panda 4x4')->count())->toBe(1);
});

test('it refuses a card without its required fields', function (): void {
    try {
        validated(cardRequest('insurance_policy', ['type' => 'home', 'home_id' => $this->home->id], $this->marco));
        $this->fail('A policy without a title passed.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['title']);
    }

    try {
        validated(cardRequest('maintenance_record', ['title' => 'Boiler', 'date' => '2026-10-01'], $this->marco));
        $this->fail('A maintenance without a target passed.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['target']);
    }

    try {
        validated(cardRequest('maintenance_record', ['title' => 'Boiler', 'date' => '2026-10-01', 'target' => 'vehicle'], $this->marco));
        $this->fail('A maintenance on a vehicle without the vehicle passed.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['vehicle_id']);
    }
});

test('it empties the fields hidden by a condition', function (): void {
    $request = validated(cardRequest('insurance_policy', [
        'title' => 'Home policy', 'type' => 'home', 'vehicle_id' => $this->panda->id, 'home_id' => $this->home->id,
    ], $this->marco));

    expect($request->columns())->toHaveKey('vehicle_id', null)
        ->and($request->columns())->toHaveKey('home_id', $this->home->id);

    $policy = resolve(SaveCard::class)->handle($request->cardType(), $request->columns());

    expect($policy)->toBeInstanceOf(InsurancePolicy::class)
        ->and($policy->vehicle_id)->toBeNull()
        ->and($policy->home_id)->toBe($this->home->id);
});

test('it does not save the virtual field of a maintenance', function (): void {
    $request = validated(cardRequest('maintenance_record', [
        'title' => 'Service', 'date' => '2026-10-01', 'target' => 'vehicle', 'vehicle_id' => $this->panda->id, 'home_id' => $this->home->id,
    ], $this->marco));

    expect($request->columns())->not->toHaveKey('target')
        ->and($request->columns())->toHaveKey('home_id', null)
        ->and($request->columns())->toHaveKey('belonging_id', null);

    $record = resolve(SaveCard::class)->handle($request->cardType(), $request->columns());

    expect($record->getAttribute('vehicle_id'))->toBe($this->panda->id);
});

test('it refuses a record of another family in a field', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $theirs = Vehicle::factory()->for($rossi->family)->create();

    try {
        validated(cardRequest('insurance_policy', ['title' => 'Car', 'type' => 'car', 'vehicle_id' => $theirs->id], $this->marco));
        $this->fail('The vehicle of another family passed.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['vehicle_id']);
    }
});

test('it refuses wrong values of each kind', function (): void {
    try {
        validated(cardRequest('vehicle', [
            'name' => 'Panda', 'registration_year' => 'old', 'road_tax_due_date' => '30/11/2026', 'status' => 'flying', 'is_owned' => 'maybe',
        ], $this->marco));
        $this->fail('The wrong values passed.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['registration_year', 'is_owned', 'road_tax_due_date', 'status']);
    }
});

test('it knows the type of the route and refuses an unknown one', function (): void {
    expect(cardRequest('loan', [], $this->marco)->cardType()->type())->toBe(CardTypes::find('loan')?->type());

    expect(fn (): CardType => cardRequest('spaceship', [], $this->marco)->cardType())
        ->toThrow(NotFoundHttpException::class);
});

test('only an admin may save a card', function (): void {
    $member = User::factory()->for($this->marco->family)->create();

    expect(cardRequest('vehicle', [], $this->marco)->authorize())->toBeTrue()
        ->and(cardRequest('vehicle', [], $member)->authorize())->toBeFalse()
        ->and(cardRequest('vehicle', [], $member, $this->panda)->authorize())->toBeFalse()
        ->and(cardRequest('vehicle', [], $this->marco, $this->panda)->authorize())->toBeTrue();
});

test('it deletes a card without leaving links behind', function (): void {
    $logbook = Document::factory()->for($this->marco->family)->create(['title' => 'Logbook']);
    $logbook->vehicles()->attach($this->panda);
    $other = Vehicle::factory()->for($this->marco->family)->create();
    $logbook->vehicles()->attach($other);
    $reminderId = DB::table('reminders')->insertGetId(['family_id' => $this->marco->family_id, 'title' => 'Road tax', 'status' => 'open', 'due_date' => '2026-11-30']);
    DB::table('remindables')->insert([
        ['reminder_id' => $reminderId, 'remindable_type' => 'vehicle', 'remindable_id' => $this->panda->id],
        ['reminder_id' => $reminderId, 'remindable_type' => 'vehicle', 'remindable_id' => $other->id],
    ]);

    resolve(DeleteCard::class)->handle($this->panda);

    expect(Vehicle::query()->find($this->panda->id))->toBeNull()
        ->and(DB::table('documentables')->where('documentable_type', 'vehicle')->where('documentable_id', $this->panda->id)->count())->toBe(0)
        ->and(DB::table('remindables')->where('remindable_type', 'vehicle')->where('remindable_id', $this->panda->id)->count())->toBe(0)
        ->and(DB::table('documentables')->where('documentable_id', $other->id)->count())->toBe(1)
        ->and(DB::table('remindables')->where('remindable_id', $other->id)->count())->toBe(1)
        ->and(Document::query()->find($logbook->id))->not->toBeNull()
        ->and($logbook->fresh()?->vehicles->pluck('id')->all())->toBe([$other->id]);
});

test('it can save every field of every description', function (): void {
    foreach (CardTypes::all() as $type) {
        $model = new ($type->model());

        foreach ($type->fields() as $field) {
            if (! $field->virtual) {
                expect($model->isFillable($field->column))->toBeTrue($type->type().'.'.$field->column.' is not fillable');
            }
        }
    }
});
