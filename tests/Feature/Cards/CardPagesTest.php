<?php

declare(strict_types=1);

use App\Enums\ExceptionType;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\File;
use App\Models\Loan;
use App\Models\PlannedExpenseException;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-10-26 10:00:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
    $this->panda = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Fiat Panda')->sole());
    $this->booklet = insideBianchi(fn (): Document => Document::query()->where('title', 'Scontrino TV Samsung')->sole());
    $this->pandaUrl = ['type' => 'vehicle', 'id' => $this->panda->id];
});

test('it shows the areas, an area and a card to every account', function (User $user): void {
    $this->actingAs($user);

    $this->get(route('areas'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Areas')->has('areas', 9));
    $this->get(route('areas.show', 'home'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Area')->where('area.area', 'home'));
    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/Card')
        ->where('card.card.label', 'Fiat Panda'));
})->with([
    'admin' => fn (): User => User::query()->where('email', 'marco.bianchi@example.com')->sole(),
    'member' => fn (): User => User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]),
]);

test('the card types carry the name of their area in the current language', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('areas.show', 'vehicles'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Area')
        ->where('types.0.area', 'vehicles')
        ->where('types.0.area_label', 'Vehicles'));
});

test('the card types carry their name in the plural, for the groups of an area', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('areas.show', 'home'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Area')
        ->where('types.0.plural_label', 'Residences')
        ->where('types.1.plural_label', 'Utilities'));
});

test('it lets only an admin create, edit and delete', function (): void {
    $this->actingAs($this->rosa);

    $this->get(route('cards.create', ['card' => 'vehicle']))->assertForbidden();
    $this->post(route('cards.store', 'vehicle'), ['name' => 'Vespa'])->assertForbidden();
    $this->get(route('cards.edit', $this->pandaUrl))->assertForbidden();
    $this->put(route('cards.update', $this->pandaUrl), ['name' => 'Panda'])->assertForbidden();
    $this->delete(route('cards.destroy', $this->pandaUrl))->assertForbidden();
});

test('it lets a member link and unlink a document', function (): void {
    $this->actingAs($this->rosa);
    $linked = fn (): bool => DB::table('documentables')->where([
        'document_id' => $this->booklet->id, 'documentable_type' => 'vehicle', 'documentable_id' => $this->panda->id,
    ])->exists();

    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id])->assertRedirect();
    expect($linked())->toBeTrue();

    $this->delete(route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $this->booklet->id]))->assertRedirect();
    expect($linked())->toBeFalse();
});

test('the documents of a card carry the address of their file, and only an admin may delete them', function (User $user, bool $canDelete): void {
    $this->actingAs($user);
    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id]);

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('card.documents', fn ($documents): bool => collect($documents)
            ->contains(fn (array $document): bool => $document['id'] === $this->booklet->id
                && $document['file_url'] === route('files.show', $this->booklet->file_id)))
        ->where('card.can_delete_documents', $canDelete));
})->with([
    'admin' => fn (): array => [User::query()->where('email', 'marco.bianchi@example.com')->sole(), true],
    'member' => fn (): array => [User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]), false],
]);

test('it answers 404 for another family', function (): void {
    $this->actingAs(registerFamily('Rossi', 'mario@rossi.example'));

    $this->get(route('cards.show', $this->pandaUrl))->assertNotFound();
    $this->get(route('cards.edit', $this->pandaUrl))->assertNotFound();
    $this->put(route('cards.update', $this->pandaUrl), ['name' => 'Mia'])->assertNotFound();
    $this->delete(route('cards.destroy', $this->pandaUrl))->assertNotFound();
    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id])->assertNotFound();
    $this->delete(route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $this->booklet->id]))->assertNotFound();
});

test('it answers 404 for a document of another family', function (): void {
    $theirs = registerFamily('Rossi', 'mario@rossi.example');
    $document = Document::factory()->for($theirs->family)->create();
    $this->actingAs($this->marco);

    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $document->id])->assertNotFound();
    $this->delete(route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $document->id]))->assertNotFound();
    expect(DB::table('documentables')->where('document_id', $document->id)->exists())->toBeFalse();
});

test('it answers 404, not 422, for an invalid change to a card of another family', function (): void {
    $this->actingAs(registerFamily('Rossi', 'mario@rossi.example'));

    $this->put(route('cards.update', $this->pandaUrl), ['name' => ''])->assertNotFound();
    $this->post(route('cards.documents.store', $this->pandaUrl), [])->assertNotFound();
});

test('it answers 404 for an unknown type or area', function (): void {
    $this->actingAs($this->marco);

    $this->get('/cards/spaceship/1')->assertNotFound();
    $this->get('/cards/create?card=spaceship')->assertNotFound();
    $this->get('/areas/garden')->assertNotFound();
});

test('it opens the form of a new card with the values of the address, but not the type of the card', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.create', ['card' => 'maintenance_record', 'target' => 'vehicle', 'vehicle_id' => $this->panda->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('cards/EditCard')
            ->where('type.type', 'maintenance_record')
            ->where('values.target', 'vehicle')
            ->where('values.vehicle_id', (string) $this->panda->id)
            ->missing('values.type'));
});

test('it offers an admin the cards that can be added linked to this one', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('card.addable', 2)
        ->where('card.addable.0.label', 'Maintenance job')
        ->where('card.addable.0.href', route('cards.create', ['card' => 'maintenance_record', 'target' => 'vehicle', 'vehicle_id' => $this->panda->id], false))
        ->where('card.addable.1.label', 'Policy')
        ->where('card.addable.1.href', route('cards.create', ['card' => 'insurance_policy', 'type' => 'car', 'vehicle_id' => $this->panda->id], false)));
});

test('it offers a member no card to add', function (): void {
    $this->actingAs($this->rosa);

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('card.addable', []));
});

test('it creates a vehicle and opens it', function (): void {
    $this->actingAs($this->marco);

    $response = $this->post(route('cards.store', 'vehicle'), [
        'name' => 'Vespa', 'status' => 'active', 'is_owned' => true, 'inspection_due_date' => '2027-03-01',
    ]);

    $vespa = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Vespa')->sole());
    $response->assertRedirect(route('cards.show', ['type' => 'vehicle', 'id' => $vespa->id]));
    expect($vespa->inspection_due_date?->toDateString())->toBe('2027-03-01');
});

test('it deletes a card and goes back to its area', function (): void {
    $this->actingAs($this->marco);
    $reminder = insideBianchi(fn (): Reminder => Reminder::factory()->create());
    DB::table('documentables')->insert(['document_id' => $this->booklet->id, 'documentable_type' => 'vehicle', 'documentable_id' => $this->panda->id]);
    DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => 'vehicle', 'remindable_id' => $this->panda->id]);

    $this->delete(route('cards.destroy', $this->pandaUrl))->assertRedirect(route('areas.show', 'vehicles'));

    expect(insideBianchi(fn (): bool => Vehicle::query()->whereKey($this->panda->id)->exists()))->toBeFalse();
    foreach (['documentables', 'remindables'] as $table) {
        $link = rtrim($table, 's');
        expect(DB::table($table)->where([$link.'_type' => 'vehicle', $link.'_id' => $this->panda->id])->exists())->toBeFalse();
    }
    $this->get(route('documents.show', $this->booklet->id))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('document.links', fn ($links): bool => collect($links)->doesntContain(fn (array $link): bool => $link['type'] === 'vehicle' && $link['id'] === $this->panda->id)));
});

test('it answers 404 for an id that is not a number', function (): void {
    $this->actingAs($this->marco);

    $this->get('/cards/vehicle/'.$this->panda->id.'abc')->assertNotFound();
    $this->get('/cards/vehicle/'.$this->panda->id.'abc/edit')->assertNotFound();
});

test('it saves an untouched switch as off, whatever the card type', function (string $type, array $input, string $table, string $column): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.store', $type), [...$input, $column => null])->assertRedirect();

    expect(insideBianchi(fn (): int => DB::table($table)->where($column, false)->where('name', $input['name'])->count()))->toBe(1);
})->with([
    'home' => ['home', ['name' => 'Casa al mare'], 'homes', 'is_owned'],
    'vehicle' => ['vehicle', ['name' => 'Vespa'], 'vehicles', 'is_owned'],
    'account' => ['financial_account', ['name' => 'Conto nuovo', 'type' => 'deposit'], 'financial_accounts', 'is_default'],
    'subscription' => ['subscription', ['name' => 'Spotify', 'type' => 'streaming'], 'subscriptions', 'is_auto_renew'],
]);

test('it creates a home without the switch in the form', function (): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.store', 'home'), ['name' => 'Casa al mare'])->assertRedirect();

    expect(insideBianchi(fn (): bool => DB::table('homes')->where('name', 'Casa al mare')->sole()->is_owned === 0))->toBeTrue();
});

test('it creates a vehicle with its documents and opens the upload linked to it', function (): void {
    $this->actingAs($this->marco);

    // As the page sends it with files: form data, so the switch is "1" and an empty date "".
    $response = $this->post(route('cards.store', 'vehicle'), [
        'name' => 'Vespa', 'status' => 'active', 'is_owned' => '1', 'inspection_due_date' => '',
        'files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf'), UploadedFile::fake()->create('Bollo.pdf', 120, 'application/pdf')],
    ]);

    $vespa = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Vespa')->sole());
    $response->assertSessionHasNoErrors()->assertRedirect(route('upload', ['link' => 'vehicle:'.$vespa->id]));
    expect($vespa->is_owned)->toBeTrue()
        ->and(insideBianchi(fn (): array => File::query()->toSort()->oldest('id')->pluck('original_name')->all()))->toBe(['Libretto.pdf', 'Bollo.pdf'])
        ->and(insideBianchi(fn (): array => File::query()->toSort()->get(['link_type', 'link_id'])->map->only('link_type', 'link_id')->unique()->values()->all()))
        ->toBe([['link_type' => 'vehicle', 'link_id' => $vespa->id]]);
});

test('it creates nothing when a document is not a file it takes', function (): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.store', 'vehicle'), [
        'name' => 'Vespa', 'files' => [UploadedFile::fake()->create('Virus.exe', 120, 'application/x-msdownload')],
    ])->assertSessionHasErrors(['files.0' => 'Only PDF, JPG, PNG and HEIC files can be uploaded.']);

    expect(insideBianchi(fn (): bool => Vehicle::query()->where('name', 'Vespa')->exists()))->toBeFalse();
});

test('it saves a change with documents and opens the upload linked to the card', function (): void {
    $this->actingAs($this->marco);

    // As the page sends it with files: a POST with the real method in _method.
    $this->post(route('cards.update', $this->pandaUrl), [
        '_method' => 'put', 'name' => 'Fiat Panda bianca', 'files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf')],
    ])->assertSessionHasNoErrors()->assertRedirect(route('upload', ['link' => 'vehicle:'.$this->panda->id]));

    expect(insideBianchi(fn (): array => File::query()->toSort()->pluck('original_name')->all()))->toBe(['Libretto.pdf'])
        ->and(insideBianchi(fn (): string => Vehicle::query()->sole()->name))->toBe('Fiat Panda bianca')
        ->and(insideBianchi(fn (): array => File::query()->toSort()->sole()->only('link_type', 'link_id')))
        ->toBe(['link_type' => 'vehicle', 'link_id' => $this->panda->id]);
});

test('it changes nothing when a document of a change is not a file it takes', function (): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.update', $this->pandaUrl), [
        '_method' => 'put', 'name' => 'Fiat Panda bianca', 'files' => [UploadedFile::fake()->create('Virus.exe', 120, 'application/x-msdownload')],
    ])->assertSessionHasErrors(['files.0' => 'Only PDF, JPG, PNG and HEIC files can be uploaded.']);

    expect(insideBianchi(fn (): string => Vehicle::query()->sole()->name))->toBe('Fiat Panda')
        ->and(insideBianchi(fn (): bool => File::query()->toSort()->exists()))->toBeFalse();
});

test('it unlinks a document and goes back to the page it came from, the card when there is none', function (): void {
    $this->actingAs($this->marco);
    $link = fn () => $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id]);
    $unlink = route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $this->booklet->id]);

    $link();
    $this->from(route('cards.edit', $this->pandaUrl))->delete($unlink)->assertRedirect(route('cards.edit', $this->pandaUrl));

    $link();
    $this->flushSession()->withoutHeader('referer')->delete($unlink)->assertRedirect(route('cards.show', $this->pandaUrl));
});

test('the form of a card says whether its documents can be deleted', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.edit', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('canDeleteDocuments', true));
});

test('it opens the form of a card with the documents linked to it', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.edit', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/EditCard')
        ->has('documents', 1)
        ->where('documents.0.title', 'Polizza RC auto 2026'));
});

test('the documents to link come 15 at a time, without those already linked', function (): void {
    $this->actingAs($this->marco);
    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id]);
    $total = insideBianchi(fn (): int => Document::query()->count()) - DB::table('documentables')->where(['documentable_type' => 'vehicle', 'documentable_id' => $this->panda->id])->count();

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('linkable', 15)
        ->where('linkableNext', 2)
        ->where('hasLinkable', true)
        ->where('linkable', fn ($documents): bool => ! collect($documents)->contains('id', $this->booklet->id)));

    $this->get(route('cards.show', [...$this->pandaUrl, 'link_page' => 2]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('linkable', $total - 15)
        ->where('linkableNext', null));
});

test('the documents to link can be searched', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.show', [...$this->pandaUrl, 'link_search' => 'busta paga']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('linkable', fn ($documents): bool => collect($documents)->isNotEmpty()
            && collect($documents)->every(fn (array $document): bool => str_contains($document['title'], 'Busta paga')))
        ->where('linkableSearch', 'busta paga')
        ->where('hasLinkable', true));
});

test('the forms of a card offer the documents to link, without those already linked', function (): void {
    $this->actingAs($this->marco);
    $policy = insideBianchi(fn (): Document => Document::query()->where('title', 'Polizza RC auto 2026')->sole());

    $this->get(route('cards.edit', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('linkable', 15)
        ->where('linkableNext', 2)
        ->where('hasLinkable', true)
        ->where('linkable', fn ($documents): bool => ! collect($documents)->contains('id', $policy->id)));

    $this->get(route('cards.create', ['card' => 'vehicle', 'link_search' => 'busta paga']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('linkable', fn ($documents): bool => collect($documents)->isNotEmpty()
            && collect($documents)->every(fn (array $document): bool => str_contains($document['title'], 'Busta paga')))
        ->where('linkableSearch', 'busta paga')
        ->where('hasLinkable', true));
});

test('a change links and unlinks the documents when it is saved', function (): void {
    $this->actingAs($this->marco);
    $policy = insideBianchi(fn (): Document => Document::query()->where('title', 'Polizza RC auto 2026')->sole());

    $this->put(route('cards.update', $this->pandaUrl), [
        'name' => 'Fiat Panda', 'link_documents' => [$this->booklet->id], 'unlink_documents' => [$policy->id],
    ])->assertSessionHasNoErrors()->assertRedirect(route('cards.show', $this->pandaUrl));

    expect(insideBianchi(fn (): array => $this->panda->documents()->pluck('title')->all()))->toBe(['Scontrino TV Samsung']);
});

test('a new card is saved with the documents linked to it', function (): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.store', 'vehicle'), [
        'name' => 'Vespa', 'status' => 'active', 'is_owned' => true, 'link_documents' => [$this->booklet->id],
    ])->assertSessionHasNoErrors();

    expect(insideBianchi(fn (): array => Vehicle::query()->where('name', 'Vespa')->sole()->documents()->pluck('title')->all()))
        ->toBe(['Scontrino TV Samsung']);
});

test('it answers 404 and saves nothing when a document to link or unlink is of another family', function (string $list): void {
    $theirs = registerFamily('Rossi', 'mario@rossi.example');
    $document = Document::factory()->for($theirs->family)->create();
    $this->actingAs($this->marco);

    $this->put(route('cards.update', $this->pandaUrl), ['name' => 'Fiat Panda bianca', $list => [$document->id]])->assertNotFound();

    expect(insideBianchi(fn (): string => Vehicle::query()->sole()->name))->toBe('Fiat Panda')
        ->and(DB::table('documentables')->where('document_id', $document->id)->exists())->toBeFalse();
})->with(['link_documents', 'unlink_documents']);

/**
 * Asks only for the loan figures of a card form, as the form does while the person types.
 *
 * @param  string  $url  The address of the form.
 * @param  array<string, mixed>  $figures  The values written in the form.
 */
function loanFiguresReload(TestCase $test, string $url, array $figures): mixed
{
    return $test->getJson($url.(str_contains($url, '?') ? '&' : '?').http_build_query(['figures' => $figures]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Inertia-Partial-Component' => 'cards/EditCard',
        'X-Inertia-Partial-Data' => 'loanFigures',
    ]);
}

it('works out the totals of a loan from the values of the form', function (): void {
    $this->actingAs($this->marco);
    $loan = insideBianchi(fn (): Loan => Loan::factory()->create([
        'family_id' => $this->marco->family_id,
        'name' => 'Prestito prova',
        'total_amount' => 1100,
        'installments_count' => 12,
        'installment_amount' => 100,
        'start_date' => '2026-01-05',
    ]));
    $form = ['total_amount' => '1100', 'installments_count' => '12', 'installment_amount' => '100', 'start_date' => '2026-01-05'];

    loanFiguresReload($this, route('cards.create', ['card' => 'loan'], false), $form)
        ->assertOk()
        ->assertJsonPath('props.loanFigures.total_to_pay', 1200)
        ->assertJsonPath('props.loanFigures.cost', 100);

    insideBianchi(function () use ($loan): void {
        $planned = $loan->plannedExpenses()->sole();
        PlannedExpenseException::factory()->create([
            'family_id' => $loan->family_id,
            'planned_expense_id' => $planned->id,
            'type' => ExceptionType::AmountOverride,
            'occurrence_date' => '2026-03-05',
            'amount_override' => 40,
        ]);
    });

    loanFiguresReload($this, route('cards.edit', ['type' => 'loan', 'id' => $loan->id], false), $form)
        ->assertJsonPath('props.loanFigures.total_to_pay', 1140)
        ->assertJsonPath('props.loanFigures.cost', 40);
});

it('works out only the loan figures when the form asks for them again', function (): void {
    $this->actingAs($this->marco);
    $loan = insideBianchi(fn (): Loan => Loan::query()->firstOrFail());
    DB::enableQueryLog();

    $response = loanFiguresReload($this, route('cards.edit', ['type' => 'loan', 'id' => $loan->id], false), ['total_amount' => '1100'])->assertOk();

    $tables = array_map(static fn (array $query): string => $query['query'], DB::getQueryLog());
    expect(array_keys($response->json('props')))->not->toContain('budget', 'documents', 'fields', 'values', 'type')
        ->and(array_filter($tables, static fn (string $query): bool => (bool) preg_match('/from `(expenses|incomes|expense_categories)`/', $query)))->toBe([]);
});

it('answers null figures when the form of a loan is not complete', function (): void {
    $this->actingAs($this->marco);

    loanFiguresReload($this, route('cards.create', ['card' => 'loan'], false), ['installment_amount' => 'abc', 'start_date' => 'ieri'])
        ->assertOk()
        ->assertJsonPath('props.loanFigures', ['total_to_pay' => null, 'cost' => null]);
});

it('sends the loan figures only to a loan', function (): void {
    $this->actingAs($this->marco);
    $form = ['total_amount' => '1100', 'installments_count' => '12', 'installment_amount' => '100'];

    loanFiguresReload($this, route('cards.create', ['card' => 'vehicle'], false), $form)->assertJsonMissingPath('props.loanFigures');
    loanFiguresReload($this, route('cards.edit', $this->pandaUrl, false), $form)->assertJsonMissingPath('props.loanFigures');
    // Not asked for, not worked out
    $this->get(route('cards.create', ['card' => 'loan']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->missing('loanFigures'));
});

it('answers 404 for the figures of a loan of another family', function (): void {
    $other = Family::factory()->create();
    $loan = CurrentFamily::using($other->id, fn (): Loan => Loan::factory()->create(['family_id' => $other->id]));
    $this->actingAs($this->marco);

    loanFiguresReload($this, route('cards.edit', ['type' => 'loan', 'id' => $loan->id], false), [])->assertNotFound();
});

it('opens the edit page of a loan with its saved total and cost', function (): void {
    $this->actingAs($this->marco);
    $loan = insideBianchi(fn (): Loan => Loan::factory()->create([
        'family_id' => $this->marco->family_id,
        'total_amount' => 1100,
        'installments_count' => 12,
        'installment_amount' => 100,
        'start_date' => '2026-01-05',
    ]));

    $this->get(route('cards.edit', ['type' => 'loan', 'id' => $loan->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('loanFigures.total_to_pay', 1200)
        ->where('loanFigures.cost', 100));
    $this->get(route('cards.edit', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->missing('loanFigures'));
});

it('works out the figures of a saved loan with its saved last date and status, as saving does', function (): void {
    $this->actingAs($this->marco);
    $loan = insideBianchi(fn (): Loan => Loan::factory()->create([
        'family_id' => $this->marco->family_id,
        'total_amount' => 1100,
        'installments_count' => 12,
        'installment_amount' => 100,
        'start_date' => '2026-01-05',
    ]));

    // The count is left out: the saved last date (05/12/2026) gives it back
    loanFiguresReload($this, route('cards.edit', ['type' => 'loan', 'id' => $loan->id], false), ['total_amount' => '1100', 'installment_amount' => '100', 'start_date' => '2026-01-05'])
        ->assertJsonPath('props.loanFigures.total_to_pay', 1200);
});

test('a card shows its documents 5 at a time, with the addresses of the other pages', function (): void {
    $this->actingAs($this->marco);
    $linked = insideBianchi(fn (): int => DB::table('documentables')->where(['documentable_type' => 'vehicle', 'documentable_id' => $this->panda->id])->count());
    Document::factory()->for($this->marco->family)->count(7 - $linked)->create()
        ->each(fn (Document $document): bool => DB::table('documentables')->insert(['document_id' => $document->id, 'documentable_type' => 'vehicle', 'documentable_id' => $this->panda->id]));

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('card.documents', 5)
        ->where('card.documents_previous', null)
        ->where('card.documents_next', route('cards.show', [...$this->pandaUrl, 'documents_page' => 2])));

    $this->get(route('cards.show', [...$this->pandaUrl, 'documents_page' => 2]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('card.documents', 2)
        ->where('card.documents_previous', route('cards.show', $this->pandaUrl))
        ->where('card.documents_next', null));
});

test('a card shows its payments 5 at a time, each pager keeping the page of the other list', function (): void {
    $this->actingAs($this->marco);
    insideBianchi(fn () => Expense::factory()->count(5)->create([
        'family_id' => $this->marco->family_id,
        'card_type' => 'vehicle',
        'card_id' => $this->panda->id,
        'date' => '2025-03-10',
    ]));
    $count = fn (array $years): int => array_sum(array_map(static fn (array $year): int => count($year['payments']), $years));

    $this->get(route('cards.show', [...$this->pandaUrl, 'documents_page' => 2]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('card.budget.payments', fn (Collection $years): bool => $count($years->toArray()) === 5)
        ->where('card.budget.payments_previous', null)
        ->where('card.budget.payments_next', route('cards.show', [...$this->pandaUrl, 'documents_page' => 2, 'payments_page' => 2])));

    $this->get(route('cards.show', [...$this->pandaUrl, 'payments_page' => 2]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        // The 3 payments of the Panda of the seeder and the 5 added: 3 left after the first page
        ->where('card.budget.payments', fn (Collection $years): bool => $count($years->toArray()) === 3)
        ->where('card.budget.payments_previous', route('cards.show', $this->pandaUrl))
        ->where('card.budget.payments_next', null)
        ->where('card.documents_previous', null));
});

test('the column of payments beside the form shows the first page, without the pager', function (): void {
    $this->actingAs($this->marco);
    insideBianchi(fn () => Expense::factory()->count(5)->create([
        'family_id' => $this->marco->family_id,
        'card_type' => 'vehicle',
        'card_id' => $this->panda->id,
        'date' => '2025-03-10',
    ]));

    $this->get(route('cards.edit', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('budget.payments', fn (Collection $years): bool => array_sum(array_map(static fn (array $year): int => count($year['payments']), $years->toArray())) === 5)
        ->where('budget.payments_next', null));
});

test('a page of documents past the last one shows the first', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.show', [...$this->pandaUrl, 'documents_page' => 5]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('card.documents.0.title', 'Polizza RC auto 2026')
        ->where('card.documents_previous', null));
});
