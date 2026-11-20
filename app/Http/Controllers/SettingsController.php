<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\AccountData;
use App\Enums\Area;
use App\Models\BankConnection;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Banks\BankFeedAvailability;
use App\Services\FamilyAreas;
use App\Services\ReleaseNotes;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The index of *Settings*: my account and the settings of the family.
 */
final class SettingsController extends Controller
{
    /**
     * The entries there are in this stage: my account, the settings of the family and, under *The app*,
     * the version with the day it was released (none for a version without notes, as `dev`).
     */
    public function __invoke(#[CurrentUser] User $user, FamilyAreas $areas, ReleaseNotes $releaseNotes): Response
    {
        $release = config()->string('app.version');

        return Inertia::render('settings/Settings', [
            'account' => AccountData::fromUser($user),
            'mainCategories' => DocumentCategory::query()->whereNull('parent_id')->count(),
            'mainExpenseCategories' => ExpenseCategory::query()->whereNull('parent_id')->count(),
            'areaCount' => count(Area::cases()),
            'hiddenAreas' => count($areas->hidden()),
            'bankCount' => BankFeedAvailability::on() ? BankConnection::query()->count() : 0,
            'release' => $release,
            'releasedOn' => $releaseNotes->released($release, app()->getLocale())?->date,
        ]);
    }
}
