<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AreaSettingsController;
use App\Http\Controllers\Budget\ComparisonController;
use App\Http\Controllers\Budget\DeadlineController;
use App\Http\Controllers\Budget\MovementController;
use App\Http\Controllers\Budget\PlannedController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\CalendarSettingsController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardDocumentController;
use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileReadingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationLinkController;
use App\Http\Controllers\LinkedDeviceController;
use App\Http\Controllers\MailgunWebhookController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NoticeSettingsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ReceivedMailController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReminderDoneController;
use App\Http\Controllers\ReminderPostponeController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanCornersController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScanShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ToSortController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\RedirectToWelcome;
use App\Services\AgentRelease;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// The invitation link: opened by whoever has no account yet.
Route::middleware('guest')->group(function (): void {
    Route::get('invitation/{token}', [InvitationLinkController::class, 'show'])->name('invitation.show');
    Route::post('invitation/{token}', [InvitationLinkController::class, 'store'])->name('invitation.store');
});

// Linking a phone with the code made on the computer: opened by a phone that has not entered yet. The order
// matters, `linked` and `resume` must come before the code. Wrong codes are limited in the controller.
Route::get('connect', [ConnectController::class, 'index'])->name('connect');
Route::get('connect/linked', [ConnectController::class, 'linked'])->name('connect.linked');
Route::post('connect/resume', [ConnectController::class, 'resume'])->name('connect.resume');
Route::get('connect/{code}', [ConnectController::class, 'show'])->name('connect.show');
Route::post('connect/{code}', [ConnectController::class, 'use'])->name('connect.use');

// The family's calendar, read by calendar apps without signing in, every hour. Outside the web group: a poll opens
// no session and sets no cookie, and without SetLocale a 404 here reads like one of an address that does not exist
// (the calendar has the language saved with its secret, not the browser's).
Route::get('calendar/{token}.ics', CalendarFeedController::class)
    ->middleware('throttle:60,1')
    ->withoutMiddleware('web')
    ->name('calendar.feed');

// The mail of the families, forwarded by the route of Mailgun for the whole domain and signed with its key. Outside
// the web group like the calendar: no session, no cookie, no CSRF token (Mailgun has none), and the language of the
// texts is the one of the installation. The throttle is high: every mail of every family comes from Mailgun.
Route::post('mail/mailgun', MailgunWebhookController::class)
    ->middleware('throttle:300,1')
    ->withoutMiddleware('web')
    ->name('mail.mailgun');

// The service worker, at the root so its scope is the whole site. Outside the web group: a script asked by the
// browser, with no session and no cookie.
Route::get('sw.js', ServiceWorkerController::class)
    ->withoutMiddleware('web')
    ->name('service-worker');

// *Share* on Android (`share_target` of the manifest): the service worker answers it on the phone; this is the
// fallback when the worker is not active yet, and it only sends the person to *Scan*. Outside the web group because
// the system menu sends the POST without a CSRF token (and no session is needed: it reads and keeps nothing); a
// guest sent on to `scan` signs in as usual.
Route::post('scan/share', ScanShareController::class)
    ->withoutMiddleware('web')
    ->name('scan.share');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('welcome', [WelcomeController::class, 'show'])->name('welcome');
    Route::post('welcome', [WelcomeController::class, 'store'])->name('welcome.store');

    // The scan of the documents, also the only page of a linked phone
    Route::get('scan', [ScanController::class, 'show'])->name('scan');
    Route::post('scan', [ScanController::class, 'store'])->name('scan.store');
    Route::post('scan/corners', ScanCornersController::class)->name('scan.corners');

    // The scanner of the house, from the computer: the names do not start with `scan.`, so a linked phone cannot open them
    Route::get('scanner', [ScannerController::class, 'show'])->name('scanner');
    Route::get('scanner/agent/{system}', [ScannerController::class, 'agent'])->whereIn('system', AgentRelease::SYSTEMS)->name('scanner.agent');

    // The page to time *Raddrizza* on a phone: only on the development machine, never online
    if (app()->isLocal()) {
        Route::inertia('scan/dewarp-check', 'scan/DewarpCheck')->name('scan.dewarp-check');
    }

    Route::middleware(RedirectToWelcome::class)->group(function (): void {
        Route::get('dashboard', HomeController::class)->name('dashboard');
        Route::get('agenda', AgendaController::class)->name('agenda');
        Route::put('agenda/filters', [AgendaController::class, 'filters'])->name('agenda.filters');
        Route::put('agenda/deadlines/{field}/{id}', [AgendaController::class, 'updateDeadline'])->name('agenda.deadlines.update');
        Route::resource('reminders', ReminderController::class)->except('show');
        Route::post('reminders/{reminder}/done', [ReminderDoneController::class, 'store'])->name('reminders.done');
        Route::delete('reminders/{reminder}/done', [ReminderDoneController::class, 'destroy'])->name('reminders.reopen');
        Route::post('reminders/{reminder}/postpone', ReminderPostponeController::class)->name('reminders.postpone');
        Route::get('search', [SearchController::class, 'index'])->name('search');
        Route::get('search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
        Route::get('notices', [NoticeController::class, 'index'])->name('notices');
        Route::post('notices/read', [NoticeController::class, 'readAll'])->name('notices.read-all');
        Route::post('notices/{notice}/read', [NoticeController::class, 'read'])->name('notices.read')->whereNumber('notice');
        Route::get('areas', [AreaController::class, 'index'])->name('areas');
        Route::get('areas/{area}', [AreaController::class, 'show'])->name('areas.show');

        Route::get('cards/create', [CardController::class, 'create'])->name('cards.create');
        Route::post('cards/{type}', [CardController::class, 'store'])->name('cards.store');
        Route::get('cards/{type}/{id}', [CardController::class, 'show'])->name('cards.show')->whereNumber('id');
        Route::get('cards/{type}/{id}/edit', [CardController::class, 'edit'])->name('cards.edit')->whereNumber('id');
        Route::get('cards/{type}/{id}/payments', [CardPaymentController::class, 'show'])->name('cards.payments')->whereNumber('id');
        Route::put('cards/{type}/{id}', [CardController::class, 'update'])->name('cards.update')->whereNumber('id');
        Route::delete('cards/{type}/{id}', [CardController::class, 'destroy'])->name('cards.destroy')->whereNumber('id');
        Route::post('cards/{type}/{id}/documents', [CardDocumentController::class, 'store'])->name('cards.documents.store')->whereNumber('id');
        Route::delete('cards/{type}/{id}/documents/{document}', [CardDocumentController::class, 'destroy'])->name('cards.documents.destroy')->whereNumber('id');

        Route::redirect('budget', '/budget/movements')->name('budget');
        Route::get('budget/movements', [MovementController::class, 'index'])->name('budget.movements.index');
        Route::get('budget/movements/create', [MovementController::class, 'create'])->name('budget.movements.create');
        Route::post('budget/movements', [MovementController::class, 'store'])->name('budget.movements.store');
        Route::prefix('budget/movements/{kind}/{id}')
            ->where(['kind' => 'expense|income', 'id' => '[0-9]+'])
            ->name('budget.movements.')
            ->group(function (): void {
                Route::get('', [MovementController::class, 'show'])->name('show');
                Route::put('', [MovementController::class, 'update'])->name('update');
                Route::delete('', [MovementController::class, 'destroy'])->name('destroy');
            });

        Route::get('budget/comparison', [ComparisonController::class, 'index'])->name('budget.comparison');

        Route::get('budget/planned', [PlannedController::class, 'index'])->name('budget.planned.index');
        Route::prefix('budget/planned/{kind}')
            ->where(['kind' => 'expense|income', 'id' => '[0-9]+'])
            ->name('budget.planned.')
            ->group(function (): void {
                Route::get('create', [PlannedController::class, 'create'])->name('create');
                Route::post('', [PlannedController::class, 'store'])->name('store');
                Route::get('{id}', [PlannedController::class, 'show'])->name('show');
                Route::get('{id}/edit', [PlannedController::class, 'edit'])->name('edit');
                Route::put('{id}', [PlannedController::class, 'update'])->name('update');
                Route::delete('{id}', [PlannedController::class, 'destroy'])->name('destroy');
            });

        Route::prefix('budget/planned/{kind}/{id}/occurrences/{date}')
            ->where(['kind' => 'expense|income', 'id' => '[0-9]+', 'date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'])
            ->name('budget.deadlines.')
            ->group(function (): void {
                Route::get('', [DeadlineController::class, 'show'])->name('show');
                Route::post('payment', [DeadlineController::class, 'payment'])->name('payment');
                Route::put('exceptions', [DeadlineController::class, 'exceptions'])->name('exceptions');
            });

        Route::get('family/invite', [InvitationController::class, 'create'])->name('family.invitations.create');
        Route::post('family/invitations', [InvitationController::class, 'store'])->name('family.invitations.store');
        Route::get('family/invitations/{invitation}', [InvitationController::class, 'show'])->name('family.invitations.show');

        Route::get('settings', SettingsController::class)->name('settings');
        Route::get('settings/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::patch('settings/account', [AccountController::class, 'update'])->name('account.update');
        Route::put('settings/account/password', [AccountController::class, 'password'])->middleware('throttle:6,1')->name('account.password');
        Route::put('settings/account/theme', [AccountController::class, 'theme'])->name('account.theme');
        Route::post('settings/account/photo', [AccountController::class, 'photo'])->name('account.photo');
        Route::delete('settings/account', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::get('settings/devices', [LinkedDeviceController::class, 'index'])->name('devices.index');
        Route::delete('settings/devices/{device}', [LinkedDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::get('settings/calendar', [CalendarSettingsController::class, 'edit'])->name('calendar.edit');
        Route::post('settings/calendar', [CalendarSettingsController::class, 'store'])->name('calendar.store');
        Route::delete('settings/calendar', [CalendarSettingsController::class, 'destroy'])->name('calendar.destroy');
        Route::post('settings/calendar/reset', [CalendarSettingsController::class, 'reset'])->name('calendar.reset');
        Route::get('settings/mail', [MailSettingsController::class, 'edit'])->name('mail-settings.edit');
        Route::put('settings/mail', [MailSettingsController::class, 'update'])->name('mail-settings.update');
        Route::post('settings/mail/address', [MailSettingsController::class, 'address'])->name('mail-settings.address');
        Route::get('settings/notices', [NoticeSettingsController::class, 'edit'])->name('settings.notices.edit');
        Route::put('settings/notices', [NoticeSettingsController::class, 'update'])->name('settings.notices.update');
        Route::get('settings/areas', [AreaSettingsController::class, 'edit'])->name('settings.areas.edit');
        Route::put('settings/areas', [AreaSettingsController::class, 'update'])->name('settings.areas.update');
        Route::post('settings/areas/reset', [AreaSettingsController::class, 'reset'])->name('settings.areas.reset');
        Route::resource('settings/categories', CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['categories' => 'category'])
            ->names('categories');
        Route::get('settings/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
        Route::patch('settings/expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');

        Route::get('files/{file}', [FileController::class, 'show'])->name('files.show');
        Route::get('files/{file}/preview', [FileController::class, 'preview'])->name('files.preview');
        Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');
        Route::post('files/{file}/read', FileReadingController::class)->name('files.read');

        Route::get('to-sort', [ToSortController::class, 'index'])->name('to-sort');
        Route::post('to-sort/read', [ToSortController::class, 'read'])->name('to-sort.read');
        Route::get('import', [ImportController::class, 'index'])->name('import');
        Route::post('import', [ImportController::class, 'store'])->name('import.store');
        Route::get('upload', [UploadController::class, 'index'])->name('upload');
        Route::post('upload', [UploadController::class, 'store'])->name('upload.store');
        Route::delete('upload/{file}', [UploadController::class, 'destroy'])->name('upload.destroy');
        Route::get('mail', [ReceivedMailController::class, 'index'])->name('mail.index');
        Route::get('mail/{receivedMail}', [ReceivedMailController::class, 'show'])->name('mail.show')->whereNumber('receivedMail');
        Route::get('mail/{receivedMail}/text', [ReceivedMailController::class, 'text'])->name('mail.text')->whereNumber('receivedMail');
        Route::delete('mail/{receivedMail}', [ReceivedMailController::class, 'destroy'])->name('mail.destroy')->whereNumber('receivedMail');
        Route::post('mail/{receivedMail}/notice', [ReceivedMailController::class, 'notice'])->name('mail.notice')->whereNumber('receivedMail');
        Route::resource('documents', DocumentController::class)->except(['create', 'store']);
        Route::post('documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');

        Route::redirect('family', '/family/people')->name('family');
        Route::resource('family/people', PersonController::class)->parameters(['people' => 'person'])->names('people');
        Route::resource('family/organizations', OrganizationController::class)->names('organizations');
    });
});
