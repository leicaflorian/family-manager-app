<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardTypes;
use App\Data\CardBudgetData;
use App\Models\User;
use App\Services\CardBudgets;
use App\Services\CardSummaries;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Payments of a card*: every payment of the planned items of a card, year by year. Everyone of the family sees it.
 */
final class CardPaymentController extends Controller
{
    public function __construct(private readonly CardBudgets $budgets, private readonly CardSummaries $summaries) {}

    /**
     * The payments of a card, with what was paid this year and the average payment. A card of another family, or
     * of a type without payments, answers 404.
     */
    public function show(string $type, string $id, #[CurrentUser] User $user): Response
    {
        $card = CardTypes::findOrFail($type)->findOrFail($id);
        $today = CarbonImmutable::today('Europe/Rome');
        $budget = $this->budgets->of($card, $user, $today);

        if (! $budget instanceof CardBudgetData || $budget->payments_label === null) {
            abort(404);
        }

        return Inertia::render('cards/CardPayments', [
            'card' => $this->summaries->row($card, $today),
            'budget' => $budget,
            ...$this->budgets->paymentFigures($card, $budget, $today),
        ]);
    }
}
