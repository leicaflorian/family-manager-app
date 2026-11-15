<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Feedback\SubmitFeedback;
use App\Enums\FeedbackKind;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * *Report a problem or an idea*: the panel of the account menu sends here.
 */
final class FeedbackController extends Controller
{
    /**
     * Saves the report, emails it to the owner and goes back to the page it was sent from.
     */
    public function store(StoreFeedbackRequest $request, #[CurrentUser] User $user, SubmitFeedback $submit): RedirectResponse
    {
        $layout = $request->input('layout');
        $file = $request->file('file');
        // Left out of the validated data for an idea, so only a problem passes them on.
        $screenshot = $request->validated('screenshot');
        $details = $request->validated('diagnostics');
        $diagnostics = is_string($details) ? json_decode($details, true) : null;

        $submit->handle($user, [
            'kind' => $request->enum('kind', FeedbackKind::class) ?? FeedbackKind::Problem,
            'message' => $request->string('message')->toString(),
            'page' => $request->string('page')->toString(),
            'layout' => is_string($layout) ? $layout : null,
            'user_agent' => $request->userAgent(),
        ], $file instanceof UploadedFile ? $file : null,
            $screenshot instanceof UploadedFile ? $screenshot : null,
            is_array($diagnostics) ? $diagnostics : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks, we will answer you by email.')]);

        return back();
    }
}
