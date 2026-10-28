<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PendingFileData;
use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Queries\DocumentRows;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The *Home*, for now with the files waiting for their document, the documents
 * expiring and the last ones uploaded. The payments and the next deadline come
 * with stage 3.
 */
final class HomeController extends Controller
{
    /** How many documents *Recently uploaded* shows. */
    private const int RECENT = 5;

    /** How many files *Waiting* shows. */
    private const int PENDING = 5;

    /**
     * The *Home*: the first five files waiting for their document and how many
     * wait, the documents expiring within 60 days or already expired, the most
     * urgent first, and the last five uploaded. Without documents, the empty *Home*.
     */
    public function __invoke(DocumentRows $rows, #[CurrentUser] User $user): Response
    {
        return Inertia::render('Home', [
            'firstName' => $user->person->first_name ?? $user->name,
            'today' => today()->toDateString(),
            'expiring' => $rows->of(['expiring' => true], byExpiry: true),
            'recent' => $rows->latest(self::RECENT),
            'hasDocuments' => Document::query()->exists(),
            'pending' => PendingFileData::waiting(self::PENDING),
            'pendingCount' => File::query()->toSort()->count(),
        ]);
    }
}
