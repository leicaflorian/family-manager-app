<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The global search: the page of the results and the few suggestions of the search box.
 */
final class SearchController extends Controller
{
    /** Results listed per group on the page of all the groups. */
    private const int PAGE_LIMIT = 20;

    /** Results listed when the page is narrowed to one group. */
    private const int GROUP_LIMIT = 100;

    /** Results per group in the suggestions. */
    private const int SUGGEST_LIMIT = 3;

    /**
     * The page of the results.
     */
    public function index(SearchRequest $request, GlobalSearch $search): Response
    {
        $group = $request->group();

        return Inertia::render('Search', [
            'results' => $search->search($request->terms(), $group, $group === null ? self::PAGE_LIMIT : self::GROUP_LIMIT),
        ]);
    }

    /**
     * The first results of every group, for the search box.
     */
    public function suggest(SearchRequest $request, GlobalSearch $search): JsonResponse
    {
        return response()->json($search->search($request->terms(), null, self::SUGGEST_LIMIT));
    }
}
