<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * *Scan*: photographing the pages of a document with the phone. For now only the page is there.
 */
final class ScanController extends Controller
{
    /**
     * The page of the scan.
     */
    public function show(): Response
    {
        return Inertia::render('scan/Scan');
    }
}
