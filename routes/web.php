<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\VisitController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AreaSettingsController;
use App\Http\Controllers\Budget\BankFilterController;
use App\Http\Controllers\Budget\BankMatchController;
use App\Http\Controllers\Budget\BankTransactionController;
use App\Http\Controllers\Budget\ComparisonController;
use App\Http\Controllers\Budget\DeadlineController;
use App\Http\Controllers\Budget\MovementController;
use App\Http\Controllers\Budget\PlannedController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\CalendarSettingsController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardDocumentController;
use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CardRelatedController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\ConsumptionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFavoriteController;
use App\Http\Controllers\DocumentPinController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileReadingController;
use App\Http\Controllers\GalleryController;
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
use App\Http\Controllers\ReleaseNotesController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReminderDoneController;
use App\Http\Controllers\ReminderPostponeController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanCornersController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScanShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Controllers\Settings\BankController;
use App\Http\Controllers\Settings\FamilyNameController;
use App\Http\Controllers\Settings\FeatureSettingsController;
use App\Http\Controllers\Settings\SupportSettingsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ToSortController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UtilityConsumptionController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WelcomeLinkController;
use App\Http\Middleware\RedirectToWelcome;
use App\Http\Middleware\SeparateHosts;
use App\Services\AgentRelease;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// The invitation link: opened by whoever has no account yet.
Route::middleware('guest')->group(function (): void {
    Route::get('invitation/{token}', [InvitationLinkController::class, 'show'])->name('invitation.show');
    Route::post('invitation/{token}', [InvitationLinkController::class, 'store'])->name('invitation.store');
    // The link of the welcome mail of a family created from the owner panel: signed, valid for a week
    Route::get('start/{user}', WelcomeLinkController::class)->middleware(['signed', 'throttle:6,1,welcome-link'])->name('welcome.link');
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
    ->middleware([SeparateHosts::OUTSIDE_WEB, 'throttle:60,1,calendar-feed'])
    ->withoutMiddleware('web')
    ->name('calendar.feed');

// The mail of the families, forwarded by the route of Mailgun for the whole domain and signed with its key. Outside
// the web group like the calendar: no session, no cookie, no CSRF token (Mailgun has none), and the language of the
// texts is the one of the installation. The throttle is high: every mail of every family comes from Mailgun.
Route::post('mail/mailgun', MailgunWebhookController::class)
    ->middleware([SeparateHosts::OUTSIDE_WEB, 'throttle:300,1,mailgun'])
    ->withoutMiddleware('web')
    ->name('mail.mailgun');

// The service worker, at the root so its scope is the whole site. Outside the web group: a script asked by the
// browser, with no session and no cookie.
Route::get('sw.js', ServiceWorkerController::class)
    ->middleware(SeparateHosts::OUTSIDE_WEB)
    ->withoutMiddleware('web')
    ->name('service-worker');

// The version of the server, asked by every open page when it comes back to the front and every few minutes, to tell
// the person that a new version is out. Outside the web group: asked often by everyone, it opens no session and sets
// no cookie (and the linked phone, kept to the scan inside the web group, reads it too).
Route::get('version', VersionController::class)
    ->middleware([SeparateHosts::OUTSIDE_WEB, 'throttle:120,1,version'])
    ->withoutMiddleware('web')
    ->name('version');

// *Share* on Android (`share_target` of the manifest): the service worker answers it on the phone; this is the
// fallback when the worker is not active yet, and it only sends the person to *Scan*. Outside the web group because
// the system menu sends the POST without a CSRF token (and no session is needed: it reads and keeps nothing); a
// guest sent on to `scan` signs in as usual.
Route::post('scan/share', ScanShareController::class)
    ->middleware(SeparateHosts::OUTSIDE_WEB)
    ->withoutMiddleware('web')
    ->name('scan.share');

// *Enter as…* of the admin panel: on the host of the app, which does not share the session of the panel, so the
// link is signed and carries a one-time token that signs the owner into this session (`VisitHandoff`, valid
// `LimitVisit::LINK_SECONDS` seconds). Leaving is the only change a visit may send (`LimitVisit::ALLOWED`).
Route::get('support/visit/{user}', [VisitController::class, 'start'])->middleware('signed')->name('support.visit.start');

Route::post('visit/leave', [VisitController::class, 'leave'])->name('visit.leave');

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
        Route::post('dashboard/checklist/hide', [ChecklistController::class, 'hide'])->middleware('throttle:10,1,checklist-hide')->name('dashboard.checklist.hide');
        Route::post('dashboard/checklist/skip', [ChecklistController::class, 'skip'])->middleware('throttle:10,1,checklist-skip')->name('dashboard.checklist.skip');
        Route::get('agenda', AgendaController::class)->name('agenda');
        Route::put('agenda/filters', [AgendaController::class, 'filters'])->name('agenda.filters');
        Route::put('agenda/deadlines/{field}/{id}', [AgendaController::class, 'updateDeadline'])->name('agenda.deadlines.update');
        Route::resource('reminders', ReminderController::class)->except('show');
        Route::post('reminders/{reminder}/done', [ReminderDoneController::class, 'store'])->name('reminders.done');
        Route::delete('reminders/{reminder}/done', [ReminderDoneController::class, 'destroy'])->name('reminders.reopen');
        Route::post('reminders/{reminder}/postpone', ReminderPostponeController::class)->name('reminders.postpone');
        Route::get('search', [SearchController::class, 'index'])->name('search');
        Route::get('search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1,search-suggest')->name('search.suggest');
        Route::get('notices', [NoticeController::class, 'index'])->name('notices');
        Route::post('notices/read', [NoticeController::class, 'readAll'])->name('notices.read-all');
        Route::post('notices/{notice}/read', [NoticeController::class, 'read'])->name('notices.read')->whereNumber('notice');
        Route::post('feedback', [FeedbackController::class, 'store'])->middleware('throttle:10,60,feedback')->name('feedback.store');
        Route::get('areas', [AreaController::class, 'index'])->name('areas');
        Route::get('areas/{area}', [AreaController::class, 'show'])->name('areas.show');

        Route::get('cards/create', [CardController::class, 'create'])->name('cards.create');
        Route::post('cards/{type}', [CardController::class, 'store'])->name('cards.store');
        Route::get('cards/{type}/{id}', [CardController::class, 'show'])->name('cards.show')->whereNumber('id');
        Route::get('cards/{type}/{id}/edit', [CardController::class, 'edit'])->name('cards.edit')->whereNumber('id');
        Route::get('cards/{type}/{id}/payments', [CardPaymentController::class, 'show'])->middleware('feature:budget')->name('cards.payments')->whereNumber('id');
        Route::get('cards/{type}/{id}/related', [CardRelatedController::class, 'show'])->name('cards.related')->whereNumber('id');
        Route::put('cards/{type}/{id}', [CardController::class, 'update'])->name('cards.update')->whereNumber('id');
        Route::delete('cards/{type}/{id}', [CardController::class, 'destroy'])->name('cards.destroy')->whereNumber('id');
        Route::post('cards/{type}/{id}/documents', [CardDocumentController::class, 'store'])->name('cards.documents.store')->whereNumber('id');
        Route::delete('cards/{type}/{id}/documents/{document}', [CardDocumentController::class, 'destroy'])->name('cards.documents.destroy')->whereNumber('id');

        Route::middleware('feature:budget')->group(function (): void {
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

            Route::middleware('feature:bank_feed')->group(function (): void {
                Route::get('budget/bank', [BankTransactionController::class, 'index'])->name('budget.bank.index');
                Route::post('budget/bank/sync', [BankTransactionController::class, 'sync'])->middleware('throttle:6,1,bank-sync')->name('budget.bank.sync');
                Route::get('budget/bank/matches', [BankMatchController::class, 'matches'])->middleware('throttle:60,1,bank-matches')->name('budget.bank.matches');
                Route::get('budget/bank/examples', [BankMatchController::class, 'examples'])->name('budget.bank.examples');
                Route::post('budget/bank/rule', [BankMatchController::class, 'rule'])->middleware('throttle:20,1,bank-rule')->name('budget.bank.rule');
                Route::get('budget/bank/filters/create', [BankFilterController::class, 'create'])->name('budget.bank.filters.create');
                Route::post('budget/bank/filters', [BankFilterController::class, 'store'])->name('budget.bank.filters.store');
                Route::post('budget/bank/filters/preview', [BankFilterController::class, 'preview'])->middleware('throttle:60,1,bank-filter-preview')->name('budget.bank.filters.preview');
                Route::get('budget/bank/filters/{filter}/edit', [BankFilterController::class, 'edit'])->name('budget.bank.filters.edit')->whereNumber('filter');
                Route::put('budget/bank/filters/{filter}', [BankFilterController::class, 'update'])->name('budget.bank.filters.update')->whereNumber('filter');
                Route::delete('budget/bank/filters/{filter}', [BankFilterController::class, 'destroy'])->name('budget.bank.filters.destroy')->whereNumber('filter');
                Route::get('budget/bank/{transaction}/candidates', [BankTransactionController::class, 'candidates'])->middleware('throttle:60,1,bank-candidates')->name('budget.bank.candidates')->whereNumber('transaction');
                Route::post('budget/bank/{transaction}/assign', [BankTransactionController::class, 'assign'])->name('budget.bank.assign')->whereNumber('transaction');
                Route::post('budget/bank/{transaction}/ignore', [BankTransactionController::class, 'ignore'])->name('budget.bank.ignore')->whereNumber('transaction');
                Route::post('budget/bank/{transaction}/transfer', [BankTransactionController::class, 'transfer'])->name('budget.bank.transfer')->whereNumber('transaction');
                Route::delete('budget/bank/{transaction}/ignore', [BankTransactionController::class, 'restore'])->name('budget.bank.restore')->whereNumber('transaction');
                Route::delete('budget/bank/{transaction}/payment', [BankTransactionController::class, 'undo'])->name('budget.bank.undo')->whereNumber('transaction');
            });

            Route::get('budget/comparison', [ComparisonController::class, 'index'])->name('budget.comparison');
            Route::get('budget/consumption', [ConsumptionController::class, 'index'])->name('budget.consumption');
            Route::post('budget/consumption/{utility}/read', [ConsumptionController::class, 'read'])->name('budget.consumption.read')->whereNumber('utility')->middleware('throttle:6,1,consumption-read');
            Route::middleware('throttle:30,1,consumption-save')->name('budget.consumption.')->group(function (): void {
                Route::post('budget/consumption/{utility}/rows', [UtilityConsumptionController::class, 'store'])->name('store')->whereNumber('utility');
                Route::put('budget/consumption/rows/{consumption}', [UtilityConsumptionController::class, 'update'])->name('update')->whereNumber('consumption');
                Route::delete('budget/consumption/rows/{consumption}', [UtilityConsumptionController::class, 'destroy'])->name('destroy')->whereNumber('consumption');
            });

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
        });

        Route::get('family/invite', [InvitationController::class, 'create'])->name('family.invitations.create');
        Route::post('family/invitations', [InvitationController::class, 'store'])->name('family.invitations.store');
        Route::get('family/invitations/{invitation}', [InvitationController::class, 'show'])->name('family.invitations.show');

        Route::get('settings', SettingsController::class)->name('settings');
        Route::get('settings/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::patch('settings/account', [AccountController::class, 'update'])->name('account.update');
        Route::put('settings/account/password', [AccountController::class, 'password'])->middleware('throttle:6,1,account-password')->name('account.password');
        Route::put('settings/account/theme', [AccountController::class, 'theme'])->name('account.theme');
        Route::post('settings/account/photo', [AccountController::class, 'photo'])->name('account.photo');
        Route::delete('settings/account', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::get('settings/family', [FamilyNameController::class, 'edit'])->name('settings.family.edit');
        Route::put('settings/family', [FamilyNameController::class, 'update'])->name('settings.family.update');
        Route::get('settings/devices', [LinkedDeviceController::class, 'index'])->name('devices.index');
        Route::delete('settings/devices/{device}', [LinkedDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::get('settings/calendar', [CalendarSettingsController::class, 'edit'])->name('calendar.edit');
        Route::post('settings/calendar', [CalendarSettingsController::class, 'store'])->name('calendar.store');
        Route::delete('settings/calendar', [CalendarSettingsController::class, 'destroy'])->name('calendar.destroy');
        Route::post('settings/calendar/reset', [CalendarSettingsController::class, 'reset'])->name('calendar.reset');
        Route::get('settings/features', [FeatureSettingsController::class, 'edit'])->name('settings.features.edit');
        Route::put('settings/features', [FeatureSettingsController::class, 'update'])->middleware('throttle:20,1,settings-features')->name('settings.features.update');
        Route::get('settings/support', [SupportSettingsController::class, 'edit'])->name('settings.support.edit');
        Route::get('settings/news', ReleaseNotesController::class)->name('settings.news');
        Route::post('settings/support', [SupportSettingsController::class, 'store'])->name('settings.support.store');
        Route::delete('settings/support', [SupportSettingsController::class, 'destroy'])->name('settings.support.destroy');
        Route::get('settings/mail', [MailSettingsController::class, 'edit'])->middleware('feature:mail')->name('mail-settings.edit');
        Route::put('settings/mail', [MailSettingsController::class, 'update'])->middleware('feature:mail')->name('mail-settings.update');
        Route::post('settings/mail/address', [MailSettingsController::class, 'address'])->middleware('feature:mail')->name('mail-settings.address');
        Route::middleware(['feature:budget', 'feature:bank_feed'])->group(function (): void {
            Route::get('settings/banks', [BankController::class, 'index'])->name('banks.index');
            Route::post('settings/banks', [BankController::class, 'store'])->name('banks.store');
            Route::get('settings/banks/callback', [BankController::class, 'callback'])->name('banks.callback');
            Route::post('settings/banks/{connection}/sync', [BankController::class, 'sync'])->middleware('throttle:6,1,bank-sync')->name('banks.sync');
            Route::delete('settings/banks/{connection}', [BankController::class, 'destroy'])->name('banks.destroy');
        });
        Route::get('settings/notices', [NoticeSettingsController::class, 'edit'])->name('settings.notices.edit');
        Route::put('settings/notices', [NoticeSettingsController::class, 'update'])->name('settings.notices.update');
        Route::get('settings/areas', [AreaSettingsController::class, 'edit'])->name('settings.areas.edit');
        Route::put('settings/areas', [AreaSettingsController::class, 'update'])->name('settings.areas.update');
        Route::post('settings/areas/reset', [AreaSettingsController::class, 'reset'])->name('settings.areas.reset');
        Route::resource('settings/categories', CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['categories' => 'category'])
            ->names('categories');
        Route::middleware('feature:budget')->group(function (): void {
            Route::get('settings/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
            Route::patch('settings/expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        });

        Route::get('files/{file}', [FileController::class, 'show'])->name('files.show');
        Route::get('files/{file}/preview', [FileController::class, 'preview'])->name('files.preview');
        Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');
        Route::post('files/{file}/read', FileReadingController::class)->name('files.read');

        Route::get('to-sort', [ToSortController::class, 'index'])->name('to-sort');
        Route::delete('to-sort', [ToSortController::class, 'destroy'])->name('to-sort.destroy');
        Route::post('to-sort/read', [ToSortController::class, 'read'])->name('to-sort.read');
        Route::get('import', [ImportController::class, 'index'])->name('import');
        Route::post('import', [ImportController::class, 'store'])->name('import.store');
        Route::get('upload', [UploadController::class, 'index'])->name('upload');
        Route::post('upload', [UploadController::class, 'store'])->name('upload.store');
        Route::delete('upload/{file}', [UploadController::class, 'destroy'])->name('upload.destroy');
        Route::get('mail', [ReceivedMailController::class, 'index'])->middleware('feature:mail')->name('mail.index');
        Route::get('mail/{receivedMail}', [ReceivedMailController::class, 'show'])->middleware('feature:mail')->name('mail.show')->whereNumber('receivedMail');
        Route::get('mail/{receivedMail}/text', [ReceivedMailController::class, 'text'])->middleware('feature:mail')->name('mail.text')->whereNumber('receivedMail');
        Route::delete('mail/{receivedMail}', [ReceivedMailController::class, 'destroy'])->middleware('feature:mail')->name('mail.destroy')->whereNumber('receivedMail');
        Route::post('mail/{receivedMail}/notice', [ReceivedMailController::class, 'notice'])->middleware('feature:mail')->name('mail.notice')->whereNumber('receivedMail');
        // `gallery` before `documents/{document}`, so it is not taken for a document id
        Route::get('documents/gallery', [GalleryController::class, 'index'])->name('documents.gallery');
        Route::post('documents/gallery', [GalleryController::class, 'store'])->middleware('throttle:20,1,gallery-add')->name('documents.gallery.store');
        Route::put('documents/{document}/favorite', [DocumentFavoriteController::class, 'store'])->middleware('throttle:60,1,document-favorite')->name('documents.favorite');
        Route::delete('documents/{document}/favorite', [DocumentFavoriteController::class, 'destroy'])->middleware('throttle:60,1,document-favorite')->name('documents.unfavorite');
        Route::resource('documents', DocumentController::class)->except(['create', 'store']);
        Route::post('documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
        Route::put('documents/{document}/pin', [DocumentPinController::class, 'store'])->middleware('throttle:30,1,document-pin')->name('documents.pin');
        Route::delete('documents/{document}/pin', [DocumentPinController::class, 'destroy'])->middleware('throttle:30,1,document-pin')->name('documents.unpin');

        Route::redirect('family', '/family/people')->name('family');
        Route::resource('family/people', PersonController::class)->parameters(['people' => 'person'])->names('people');
        Route::post('family/organizations/quick', [OrganizationController::class, 'quick'])->middleware('throttle:30,1,organization-quick')->name('organizations.quick');
        Route::resource('family/organizations', OrganizationController::class)->names('organizations');
    });
});
