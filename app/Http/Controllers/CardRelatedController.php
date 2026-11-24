<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardTypes;
use App\Models\User;
use App\Services\CardDetails;
use App\Services\CardSummaries;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Linked cards of a card*: all of them, the ones with a date coming first and the closed ones last. Everyone of
 * the family sees it.
 */
final class CardRelatedController extends Controller
{
    public function __construct(private readonly CardDetails $details, private readonly CardSummaries $summaries) {}

    /**
     * Every card linked to this one, with the cards that can be added. A card of another family answers 404.
     */
    public function show(string $type, string $id, #[CurrentUser] User $user): Response
    {
        $card = CardTypes::findOrFail($type)->findOrFail($id);
        $cardType = CardTypes::for($card);
        $row = $this->summaries->row($card, CarbonImmutable::today('Europe/Rome'));

        return Inertia::render('cards/CardRelated', [
            'card' => $row,
            'related' => $this->details->related($cardType, $card),
            'addable' => CardTypes::canCreate($user) ? $this->details->addable($cardType, $card) : [],
            'back' => route('cards.show', ['type' => $row->type, 'id' => $row->id], false),
        ]);
    }
}
