<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\CardTypeData;
use App\Enums\Area;
use App\Models\FamilySetting;
use App\Models\User;
use App\Services\CardSummaries;
use App\Services\FamilyAreas;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Areas* and *An area*: the cards of the family grouped by area. Everyone sees them.
 */
final class AreaController extends Controller
{
    public function __construct(private readonly CardSummaries $summaries, private readonly FamilyAreas $familyAreas) {}

    /**
     * *Areas*: every area with its cards, and the card types for *New card*.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        // Not named `areas`: a page prop would replace the shared `areas` (visible areas with counts) of the sidebar
        return Inertia::render('Areas', [
            'groups' => $this->summaries->areas(),
            'types' => self::typeData(CardTypes::ofAreas($this->familyAreas->visible())),
            'canCreate' => CardTypes::canCreate($user),
        ]);
    }

    /**
     * *An area*: its cards and its card types, and whether the family hid it (a hidden area still opens). An unknown area answers 404.
     */
    public function show(Area $area, #[CurrentUser] User $user): Response
    {
        return Inertia::render('Area', [
            'area' => $this->summaries->area($area),
            'types' => self::typeData(CardTypes::of($area)),
            'canCreate' => CardTypes::canCreate($user),
            'hidden' => $this->familyAreas->isHidden($area),
            'can_show' => $user->can('create', FamilySetting::class),
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
