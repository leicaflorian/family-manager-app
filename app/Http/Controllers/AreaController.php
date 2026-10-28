<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\CardTypeData;
use App\Enums\Area;
use App\Models\User;
use App\Services\CardSummaries;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Areas* and *An area*: the cards of the family grouped by area. Everyone sees them.
 */
final class AreaController extends Controller
{
    public function __construct(private readonly CardSummaries $summaries) {}

    /**
     * *Areas*: every area with its cards, and the card types for *New card*.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('Areas', [
            'areas' => $this->summaries->areas(),
            'types' => self::typeData(CardTypes::all()),
            'canCreate' => CardTypes::canCreate($user),
        ]);
    }

    /**
     * *An area*: its cards and its card types. An unknown area answers 404.
     */
    public function show(Area $area, #[CurrentUser] User $user): Response
    {
        return Inertia::render('Area', [
            'area' => $this->summaries->area($area),
            'types' => self::typeData(CardTypes::of($area)),
            'canCreate' => CardTypes::canCreate($user),
        ]);
    }

    /**
     * The card types as the pages take them.
     *
     * @param  list<CardType>  $types  the descriptions
     * @return list<CardTypeData>
     */
    public static function typeData(array $types): array
    {
        return array_map(CardTypeData::fromCardType(...), $types);
    }
}
