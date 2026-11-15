<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReleaseNotes;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → What's new*: the versions of the app, newest first, with what changed in each one.
 */
final class ReleaseNotesController extends Controller
{
    /**
     * The versions in the language of the person, and the one that runs.
     */
    public function __invoke(ReleaseNotes $releaseNotes): Response
    {
        return Inertia::render('settings/News', [
            'release' => config()->string('app.version'),
            'notes' => $releaseNotes->latest(app()->getLocale()),
        ]);
    }
}
