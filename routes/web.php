<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationLinkController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\RedirectToWelcome;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// The invitation link: opened by whoever has no account yet.
Route::middleware('guest')->group(function (): void {
    Route::get('invitation/{token}', [InvitationLinkController::class, 'show'])->name('invitation.show');
    Route::post('invitation/{token}', [InvitationLinkController::class, 'store'])->name('invitation.store');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('welcome', [WelcomeController::class, 'show'])->name('welcome');
    Route::post('welcome', [WelcomeController::class, 'store'])->name('welcome.store');

    Route::middleware(RedirectToWelcome::class)->group(function (): void {
        Route::get('dashboard', HomeController::class)->name('dashboard');
        Route::inertia('areas', 'Areas')->name('areas');

        Route::get('family/invite', [InvitationController::class, 'create'])->name('family.invitations.create');
        Route::post('family/invitations', [InvitationController::class, 'store'])->name('family.invitations.store');
        Route::get('family/invitations/{invitation}', [InvitationController::class, 'show'])->name('family.invitations.show');

        Route::get('settings', SettingsController::class)->name('settings');
        Route::get('settings/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::patch('settings/account', [AccountController::class, 'update'])->name('account.update');
        Route::put('settings/account/password', [AccountController::class, 'password'])->middleware('throttle:6,1')->name('account.password');
        Route::post('settings/account/photo', [AccountController::class, 'photo'])->name('account.photo');
        Route::delete('settings/account', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::resource('settings/categories', CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['categories' => 'category'])
            ->names('categories');

        Route::get('files/{file}', [FileController::class, 'show'])->name('files.show');
        Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');

        Route::get('upload', [UploadController::class, 'index'])->name('upload');
        Route::post('upload', [UploadController::class, 'store'])->name('upload.store');
        Route::delete('upload/{file}', [UploadController::class, 'destroy'])->name('upload.destroy');
        Route::resource('documents', DocumentController::class)->except('create');

        Route::redirect('family', '/family/people')->name('family');
        Route::resource('family/people', PersonController::class)->parameters(['people' => 'person'])->names('people');
        Route::resource('family/organizations', OrganizationController::class)->names('organizations');
    });
});
