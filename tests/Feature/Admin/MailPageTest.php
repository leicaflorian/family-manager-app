<?php

declare(strict_types=1);

use App\Enums\CallOutcome;
use App\Filament\Pages\MailPage;
use App\Models\Family;
use App\Models\MailInboxCall;
use App\Models\Owner;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Date::setTestNow('2026-10-25 09:41:00');
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');
    config()->set('services.mailgun.inbox_domain', 'bollette.example.com');
    config()->set('services.mailgun.signing_key');
    config()->set('services.mailgun.api_key');
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
});

/**
 * Three calls of different days, outcomes and families.
 */
function pageCalls(Family $family): void
{
    MailInboxCall::factory()->create(['outcome' => CallOutcome::Saved, 'http_status' => 200, 'family_id' => $family->id, 'attachments' => 2, 'saved' => 1, 'payload' => ['subject' => 'SECRET SUBJECT'], 'created_at' => now()]);
    MailInboxCall::factory()->create(['outcome' => CallOutcome::Rejected, 'http_status' => 401, 'family_id' => null, 'attachments' => 0, 'saved' => 0, 'payload' => [], 'created_at' => now()->subDays(10)]);
    MailInboxCall::factory()->create(['outcome' => CallOutcome::Unknown, 'http_status' => 200, 'family_id' => null, 'attachments' => 0, 'saved' => 0, 'payload' => ['recipient' => 'x@y.test'], 'created_at' => now()->subDays(2)]);
}

it('shows the mail log without the text of the mails', function (): void {
    pageCalls($this->bianchi);

    Livewire::test(MailPage::class)
        ->assertSee('bollette.example.com')
        ->assertSee('Bianchi')
        ->assertSee('25/10/2026 09:41')
        ->assertSee('Saved')
        ->assertDontSee('SECRET SUBJECT')
        ->assertDontSee('x@y.test');
});

it('filters by family, outcome and date', function (): void {
    pageCalls($this->bianchi);

    Livewire::test(MailPage::class)
        ->set('outcome', 'rejected')
        ->assertSee('401')->assertDontSeeHtml('">Bianchi</td>')
        ->set('outcome', '')
        ->set('family', (string) $this->bianchi->id)
        ->assertSeeHtml('">Bianchi</td>')->assertDontSee('401')
        ->set('family', '')
        ->set('since', '2026-10-22')
        ->assertSeeHtml('">Bianchi</td>')->assertDontSee('401');
});

it('says so when there are no calls', function (): void {
    Livewire::test(MailPage::class)->assertSee('No calls.');
});

it('never shows the keys', function (): void {
    config()->set('services.mailgun.signing_key', 'secret-signing-value');
    config()->set('services.mailgun.api_key', 'secret-api-value');

    Livewire::test(MailPage::class)
        ->assertSee('Signing key: set')
        ->assertSee('API key: set')
        ->assertDontSee('secret-signing-value')
        ->assertDontSee('secret-api-value');

    config()->set('services.mailgun.signing_key');
    config()->set('services.mailgun.api_key');

    Livewire::test(MailPage::class)->assertSee('Signing key: not set')->assertSee('API key: not set');
});

it('shows the route button only with both keys', function (): void {
    config()->set('services.mailgun.signing_key', 'sk');
    Livewire::test(MailPage::class)->assertActionHidden('writeRoute');

    config()->set('services.mailgun.api_key', 'ak');
    config()->set('services.mailgun.signing_key');
    Livewire::test(MailPage::class)->assertActionHidden('writeRoute');

    config()->set('services.mailgun.signing_key', 'sk');
    Livewire::test(MailPage::class)->assertActionVisible('writeRoute');
});

it('asks for confirmation before writing the route', function (): void {
    config()->set('services.mailgun.signing_key', 'sk');
    config()->set('services.mailgun.api_key', 'ak');

    $page = Livewire::test(MailPage::class)->mountAction('writeRoute')->assertActionMounted('writeRoute');

    $action = $page->instance()->getMountedAction();

    expect($action->isConfirmationRequired())->toBeTrue()
        ->and((string) $action->getModalDescription())->toBe('Mailgun will send to this app all the mail of the domain. Do it only at step 4, when the owner\'s family moves here.');
});

it('calls the Mailgun route service', function (): void {
    config()->set('services.mailgun.signing_key', 'sk');
    config()->set('services.mailgun.api_key', 'ak');
    config()->set('app.url', 'https://app.example.com');
    Http::preventStrayRequests();
    config()->set('services.mailgun.endpoint', 'https://mailgun.test');
    Http::fake(fn ($request) => $request->method() === 'GET'
        ? Http::response(['items' => [], 'total_count' => 0])
        : Http::response(['message' => 'ok']));

    Livewire::test(MailPage::class)
        ->callAction('writeRoute')
        ->assertNotified(__('Route created for :domain.', ['domain' => 'bollette.example.com']));

    Http::assertSent(fn ($request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/v3/routes'));
});

it('shows the refusal of Mailgun as a failed notification, never the key', function (): void {
    config()->set('services.mailgun.signing_key', 'sk');
    config()->set('services.mailgun.api_key', 'ak-secret');
    config()->set('app.url', 'https://app.example.com');
    Http::preventStrayRequests();
    config()->set('services.mailgun.endpoint', 'https://mailgun.test');
    Http::fake(fn () => Http::response(['message' => 'denied ak-secret'], 403));

    Livewire::test(MailPage::class)
        ->callAction('writeRoute')
        ->assertNotified(__('The route was not written.'))
        ->assertDontSee('ak-secret');
});
