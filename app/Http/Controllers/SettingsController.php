<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\AccountData;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The index of *Settings*: my account and the settings of the family.
 */
final class SettingsController extends Controller
{
    /**
     * The entries there are in this stage: my account and the categories.
     */
    public function __invoke(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Settings', [
            'account' => AccountData::fromUser($user),
            'mainCategories' => DocumentCategory::query()->whereNull('parent_id')->count(),
        ]);
    }
}
