<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\AgentReleaseData;
use App\Services\AgentRelease;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Scan the sheets*: the page that drives the scanner of the house through its agent, a program on the computer that
 * the page reaches on 127.0.0.1. The server only offers the page and the download of the agent.
 */
final class ScannerController extends Controller
{
    /**
     * The page. The last version of the agent comes after the page (it asks GitHub, once an hour), so the page opens
     * at once; `agent_release` is null when GitHub does not answer.
     */
    public function show(AgentRelease $releases): Response
    {
        return Inertia::render('scan/Scanner', [
            'agent_release' => Inertia::defer(static function () use ($releases): ?AgentReleaseData {
                $latest = $releases->latest();

                return $latest === null ? null : new AgentReleaseData(
                    $latest['version'],
                    isset($latest['files']['mac']),
                    isset($latest['files']['windows']),
                );
            }),
        ]);
    }

    /**
     * The zip of the agent for a system: the last release the page can talk to, or the page of the releases when
     * GitHub does not answer or has no zip for it.
     */
    public function agent(string $system, AgentRelease $releases): RedirectResponse
    {
        return redirect()->away($releases->download($system) ?? AgentRelease::RELEASES);
    }
}
