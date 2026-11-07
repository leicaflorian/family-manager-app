<?php

declare(strict_types=1);

use App\Enums\HealthEventType;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\File;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->marco = registerFamily();
    $this->laura = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
});

/**
 * Makes a file waiting in the Bianchi family.
 *
 * @param  array<string, mixed>  $attributes
 */
function waitingFile(array $attributes = []): File
{
    return File::factory()->for(test()->marco->family)->create(['to_sort' => true, 'created_by' => test()->marco->id, ...$attributes]);
}

/**
 * One entry of the body of `POST import`.
 *
 * @param  array<string, mixed>  $document
 * @return array{file_id: int, document: array<string, mixed>}
 */
function importEntry(File $file, array $document = []): array
{
    return ['file_id' => $file->id, 'document' => ['title' => 'Doc '.$file->id, 'status' => 'active', ...$document]];
}

test('the page opens with the files asked for, in that order, and the choices of the form', function (): void {
    $first = waitingFile(['original_name' => 'a.pdf']);
    $second = waitingFile(['original_name' => 'b.pdf']);

    $this->actingAs($this->marco)->get(route('import', ['files' => "{$second->id},{$first->id}"]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('documents/Import')
            ->where('files.0.id', $second->id)->where('files.1.id', $first->id)
            ->has('statuses')->has('categories')->has('people')->has('organizations')->has('links')->has('tags')
            ->where('failures', []));
});

test('the page names the categories and the accounts of the expense a file proposes', function (): void {
    $file = waitingFile();
    $health = ExpenseCategory::factory()->for($this->marco->family)->create(['name' => 'Salute', 'parent_id' => null]);
    ExpenseCategory::factory()->for($this->marco->family)->create(['name' => 'Spese mediche', 'parent_id' => $health->id]);
    $card = FinancialAccount::factory()->for($this->marco->family)->create(['name' => 'Carta Laura']);

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('expenseCategories', fn (Collection $options): bool => $options->pluck('label')->contains('Salute › Spese mediche'))
            ->where('accounts', fn (Collection $options): bool => $options->contains(fn (array $option): bool => $option['value'] === (string) $card->id && $option['label'] === 'Carta Laura')));
});

test('the page gives the types of a health event, for the box of the event', function (): void {
    $file = waitingFile();

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('healthTypes', fn (Collection $options): bool => $options->pluck('value')->all() === array_column(HealthEventType::cases(), 'value')));
});

test('a file of another family is a 404 before anything else', function (): void {
    $mine = waitingFile();
    $foreign = File::factory()->create(['to_sort' => true]);

    $this->actingAs($this->marco)->get(route('import', ['files' => "{$mine->id},{$foreign->id}"]))->assertNotFound();
});

test('a file that is no longer waiting is a 404', function (): void {
    $filed = File::factory()->for($this->marco->family)->create(['to_sort' => false]);

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $filed->id]))->assertNotFound();
});

test('without files the page goes back to the files to sort', function (): void {
    $this->actingAs($this->marco)->get(route('import'))->assertRedirect(route('to-sort'));
});

test('three files read become three documents', function (): void {
    $files = [waitingFile(), waitingFile(), waitingFile()];
    $comune = Organization::factory()->for($this->marco->family)->create();

    $this->actingAs($this->laura)->post(route('import.store'), ['files' => [
        importEntry($files[0], ['title' => 'Luce', 'organization_id' => $comune->id, 'tags' => ['bills']]),
        importEntry($files[1], ['title' => 'Gas']),
        importEntry($files[2], ['title' => 'Acqua', 'notes' => 'Paid']),
    ]])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    expect(Document::query()->orderBy('id')->pluck('title')->all())->toBe(['Luce', 'Gas', 'Acqua'])
        ->and(Document::query()->where('file_id', $files[0]->id)->sole()->organization_id)->toBe($comune->id)
        ->and(File::query()->toSort()->count())->toBe(0);
});

test('a file left out stays in To sort', function (): void {
    $kept = waitingFile();
    $left = waitingFile();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($kept)]])->assertSessionHasNoErrors();

    expect(Document::query()->count())->toBe(1)
        ->and($left->fresh()->to_sort)->toBeTrue()
        ->and($kept->fresh()->to_sort)->toBeFalse();
});

test('a file of another family in the body is a 422 and nothing is saved', function (): void {
    $mine = waitingFile();
    $foreign = File::factory()->create(['to_sort' => true]);

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($mine), importEntry($foreign)]])
        ->assertSessionHasErrors('files.1.file_id');

    expect(Document::query()->count())->toBe(0)->and($mine->fresh()->to_sort)->toBeTrue();
});

test('the rules of a document apply to each file and nothing is saved when one is wrong', function (): void {
    $one = waitingFile();
    $two = waitingFile();
    $foreignOrganization = Organization::factory()->create();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importEntry($one),
        importEntry($two, ['title' => '', 'organization_id' => $foreignOrganization->id]),
    ]])->assertSessionHasErrors(['files.1.document.title', 'files.1.document.organization_id']);

    expect(Document::query()->count())->toBe(0);
});

test('entries that are not a list are refused, so no document escapes its rules', function (): void {
    $file = waitingFile();
    $foreign = Person::factory()->create();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => ['foo' => importEntry($file, ['person_id' => $foreign->id])]])
        ->assertSessionHasErrors('files');

    expect(Document::query()->count())->toBe(0)->and($file->fresh()->to_sort)->toBeTrue();
});

test('at least one file is needed', function (): void {
    $this->actingAs($this->marco)->post(route('import.store'), ['files' => []])->assertSessionHasErrors('files');
    $this->actingAs($this->marco)->post(route('import.store'))->assertSessionHasErrors('files');
});

test('a file already filed is not imported twice', function (): void {
    $file = waitingFile();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($file)]])->assertSessionHasNoErrors();
    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($file)]])->assertSessionHasErrors('files.0.file_id');

    expect(Document::query()->count())->toBe(1);
});

test('the same file twice in one request is refused and nothing is saved', function (): void {
    $file = waitingFile();
    $other = waitingFile();

    $response = $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($file), importEntry($file, ['title' => 'Again']), importEntry($other)]]);

    $response->assertSessionHasErrors('files.1.file_id');
    expect(Document::query()->count())->toBe(0);
});

test('a file that fails to save leaves the others saved, and the page says why', function (): void {
    $good = waitingFile();
    $bad = waitingFile();
    Document::creating(function (Document $document): void {
        if ($document->title === 'Boom') {
            throw new RuntimeException('The disk is full');
        }
    });

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($bad, ['title' => 'Boom']), importEntry($good)]])
        ->assertRedirect(route('import', ['files' => (string) $bad->id]))
        ->assertSessionHas('import_failures.'.$bad->id, 'This file could not be saved.');

    expect(Document::query()->count())->toBe(1)
        ->and(Document::query()->sole()->file_id)->toBe($good->id)
        ->and($bad->fresh()->to_sort)->toBeTrue();
});

test('a file filed by someone else while the others are saved is skipped, and the others stay saved', function (): void {
    $good = waitingFile();
    $taken = waitingFile();
    Document::created(function () use ($taken): void {
        File::query()->whereKey($taken->id)->update(['to_sort' => false]);
    });

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($good), importEntry($taken)]])
        ->assertRedirect(route('documents.index'));

    expect(Document::query()->count())->toBe(1);
});

test('the failures come back to the page', function (): void {
    $file = waitingFile();

    $this->actingAs($this->marco)->withSession(['import_failures' => [$file->id => 'It could not be saved.']])
        ->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('failures', [$file->id => 'It could not be saved.']));
});

test('the page keeps the card the files come from, and ignores one of another family', function (): void {
    $file = waitingFile();
    $panda = Vehicle::factory()->for($this->marco->family)->create();
    $theirs = Vehicle::factory()->create();

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id, 'link' => 'vehicle:'.$panda->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('presetLinks', [['type' => 'vehicle', 'id' => $panda->id]]));
    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id, 'link' => 'vehicle:'.$theirs->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('presetLinks', []));
});

test('files imported from a card go back to the card', function (): void {
    $file = waitingFile();
    $panda = Vehicle::factory()->for($this->marco->family)->create();

    $this->actingAs($this->marco)->post(route('import.store', ['link' => 'vehicle:'.$panda->id]), ['files' => [importEntry($file)]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('cards.show', ['type' => 'vehicle', 'id' => $panda->id]));
});

test('a file that fails to save from a card comes back to the page with the card', function (): void {
    $bad = waitingFile();
    $panda = Vehicle::factory()->for($this->marco->family)->create();
    Document::creating(fn (): never => throw new RuntimeException('The disk is full'));

    $this->actingAs($this->marco)->post(route('import.store', ['link' => 'vehicle:'.$panda->id]), ['files' => [importEntry($bad)]])
        ->assertRedirect(route('import', ['files' => (string) $bad->id, 'link' => 'vehicle:'.$panda->id]));
});

test('guests are sent to sign in', function (): void {
    $this->get(route('import'))->assertRedirect(route('login'));
    $this->post(route('import.store'), [])->assertRedirect(route('login'));
});

test('to-sort read opens the import page with the files queued, copies left out', function (): void {
    $sum = hash('sha256', 'same');
    $original = waitingFile(['checksum' => $sum]);
    $copy = waitingFile(['checksum' => $sum]);
    $other = waitingFile(['checksum' => hash('sha256', 'other')]);

    $this->actingAs($this->marco)->post(route('to-sort.read'), ['ids' => [$original->id, $copy->id, $other->id]])
        ->assertRedirect(route('import', ['files' => "{$original->id},{$other->id}"]));
});

test('a member can import: members add documents', function (): void {
    $file = waitingFile();

    $this->actingAs($this->laura)->post(route('import.store'), ['files' => [importEntry($file)]])->assertSessionHasNoErrors();

    expect(Document::query()->count())->toBe(1);
});

describe('what each file does too', function (): void {
    beforeEach(function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-10-16 10:00', 'Europe/Rome'));
        $this->dentist = Organization::factory()->for($this->marco->family)->create(['name' => 'Studio dentistico']);
        $this->invoice = File::factory()->for($this->marco->family)->read([
            'kind' => 'invoice', 'title' => 'Cure dentistiche', 'organization_id' => $this->dentist->id, 'total_amount' => 480,
            'issue_date' => '2026-10-15', 'due_date' => '2026-11-30',
        ])->create(['to_sort' => true]);
    });

    afterEach(function (): void {
        Date::setTestNow();
    });

    test('the page has the proposal of each file', function (): void {
        $other = waitingFile();

        $this->actingAs($this->marco)->get(route('import', ['files' => "{$this->invoice->id},{$other->id}"]))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where("proposals.{$this->invoice->id}.fileId", $this->invoice->id)
                ->where("proposals.{$this->invoice->id}.movement.due.mode", 'new')
                ->where("proposals.{$this->invoice->id}.movement.due.date", '2026-11-30')
                ->where("proposals.{$other->id}.fileId", $other->id));
    });

    test('the check recalculates the proposal of the file it names, with its choices', function (): void {
        $other = waitingFile();

        $this->actingAs($this->marco)->get(route('import', [
            'files' => "{$this->invoice->id},{$other->id}",
            'check' => ['file_id' => $this->invoice->id, 'document' => [], 'also' => ['overrides' => ['movement' => ['due_date' => '2026-12-15']]]],
        ]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$this->invoice->id}.movement.due.date", '2026-12-15')
            ->missing("proposals.{$other->id}")
            ->reloadOnly('proposals', fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where("proposals.{$this->invoice->id}.movement.due.date", '2026-12-15')
                ->missing("proposals.{$other->id}")
                ->missing('files')));

        // The page keeps the proposals of the other files: the one that comes back is merged with them
        $this->actingAs($this->marco)->getJson(route('import', ['files' => (string) $this->invoice->id, 'check' => ['file_id' => $this->invoice->id]]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')),
            'X-Inertia-Partial-Component' => 'documents/Import',
            'X-Inertia-Partial-Data' => 'proposals',
        ])->assertOk()->assertJsonPath('mergeProps', ['proposals']);
    });

    test('a file of another family is 404 on the check', function (): void {
        $foreign = File::factory()->create(['to_sort' => true]);

        $this->actingAs($this->marco)->get(route('import', [
            'files' => (string) $this->invoice->id,
            'check' => ['file_id' => $foreign->id, 'document' => [], 'also' => []],
        ]))->assertNotFound();
    });

    test('the expense to pay is saved with its document', function (): void {
        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($this->invoice), 'also' => ['movement' => true]],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

        expect(PlannedExpense::query()->sole()->start_date?->toDateString())->toBe('2026-11-30')
            ->and(Document::query()->sole()->planned_expense_id)->toBe(PlannedExpense::query()->sole()->id);
    });

    test('a chosen expense deleted before the confirm', function (): void {
        $gone = Expense::factory()->for($this->marco->family)->create();
        $gone->delete();

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($this->invoice), 'also' => ['movement' => true, 'targets' => ['movement' => $gone->id]]],
        ]])->assertSessionHasErrors(['files.0.also.targets.movement' => 'The chosen expense no longer exists: choose another one.']);

        expect(Document::query()->count())->toBe(0)->and(PlannedExpense::query()->count())->toBe(0);
    });

    test('a chosen card of another family', function (): void {
        $theirs = Utility::factory()->create();
        $theirEvent = HealthEvent::factory()->create();

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($this->invoice), 'also' => ['event' => true, 'records' => ['utility'], 'targets' => ['event' => $theirEvent->id, 'records' => ['utility' => $theirs->id]]]],
        ]])->assertSessionHasErrors([
            'files.0.also.targets.records.utility' => 'The chosen card no longer exists: choose another one.',
            'files.0.also.targets.event' => 'The chosen event no longer exists: choose another one.',
        ]);

        expect(Document::query()->count())->toBe(0);
    });

    test('a ticked expense without what it needs is refused', function (): void {
        $undated = File::factory()->for($this->marco->family)->read(['kind' => 'invoice', 'title' => 'Cure', 'total_amount' => 480])->create(['to_sort' => true]);

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($undated), 'also' => ['movement' => true, 'due' => false]],
        ]])->assertSessionHasErrors(['files.0.also.movement' => 'To create the expense the amount and the date are needed: enter them or untick the expense.']);

        expect(Document::query()->count())->toBe(0);
    });

    test('a ticked card missing a required field is refused', function (): void {
        $policy = File::factory()->for($this->marco->family)->read([
            'kind' => 'insurance', 'policy_number' => '40/123456', 'issue_date' => '2026-10-01', 'organization_id' => $this->dentist->id,
        ])->create(['to_sort' => true]);

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($policy), 'also' => ['records' => ['policy']]],
        ]])->assertSessionHasErrors(['files.0.also.records.policy' => 'To create the policy 40/123456 · Studio dentistico the type is needed: enter it or untick it.']);

        expect(Document::query()->count())->toBe(0)->and(InsurancePolicy::query()->count())->toBe(0);
    });

    test('an id of another family in the corrections is refused, and nothing is saved', function (): void {
        $theirCategory = ExpenseCategory::factory()->create();
        $theirHome = Home::factory()->create();

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($this->invoice), 'also' => ['movement' => true, 'overrides' => [
                'movement' => ['category_id' => $theirCategory->id],
                'records' => ['utility' => ['home_id' => $theirHome->id]],
            ]]],
        ]])->assertSessionHasErrors(['files.0.also.overrides.movement.category_id', 'files.0.also.overrides.records.utility.home_id']);

        expect(Document::query()->count())->toBe(0)->and(PlannedExpense::query()->count())->toBe(0);
    });

    test('the corrections of the expense reach the import', function (): void {
        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($this->invoice), 'also' => ['movement' => true, 'overrides' => ['movement' => ['title' => 'Dentista di Giulia']]]],
        ]])->assertSessionHasNoErrors();

        expect(PlannedExpense::query()->sole()->title)->toBe('Dentista di Giulia');
    });

    test('a new utility with a new organization: the organization the utility needs is not missing', function (): void {
        $bill = File::factory()->for($this->marco->family)->read([
            'kind' => 'utility_bill', 'issuer_name' => 'Iren', 'utility_type' => 'gas', 'total_amount' => 80, 'issue_date' => '2026-10-10',
            'supply_point_code' => '00881234567890',
        ])->create(['to_sort' => true]);

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($bill), 'also' => ['records' => ['organization', 'utility']]],
        ]])->assertSessionHasNoErrors();

        $iren = Organization::query()->where('name', 'Iren')->sole();
        expect(Utility::query()->where('supply_point_code', '00881234567890')->sole()->organization_id)->toBe($iren->id);
    });

    test('two files of the same new issuer confirmed together make one organization', function (): void {
        $reading = ['kind' => 'invoice', 'title' => 'Caldaia', 'issuer_name' => 'Termoidraulica Rossi', 'total_amount' => 150, 'issue_date' => '2026-10-10'];
        $first = File::factory()->for($this->marco->family)->read($reading)->create(['to_sort' => true]);
        $second = File::factory()->for($this->marco->family)->read([...$reading, 'total_amount' => 90, 'issue_date' => '2026-09-01'])->create(['to_sort' => true]);

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($first), 'also' => ['records' => ['organization']]],
            [...importEntry($second), 'also' => ['records' => ['organization']]],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

        $rossi = Organization::query()->where('name', 'Termoidraulica Rossi')->sole();
        expect(Document::query()->where('file_id', $second->id)->sole()->organization_id)->toBe($rossi->id);
    });

    test('the second file of an import links to the expense the first one created', function (): void {
        $reading = ['kind' => 'receipt', 'title' => 'Cure dentistiche', 'organization_id' => $this->dentist->id, 'total_amount' => 150, 'issue_date' => '2026-10-10'];
        $first = File::factory()->for($this->marco->family)->read($reading)->create(['to_sort' => true]);
        $second = File::factory()->for($this->marco->family)->read($reading)->create(['to_sort' => true]);

        $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
            [...importEntry($first), 'also' => ['movement' => true, 'due' => false]],
            [...importEntry($second), 'also' => ['movement' => true, 'due' => false]],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

        $expense = Expense::query()->sole();
        expect($expense->documents()->count())->toBe(2);
    });
});
