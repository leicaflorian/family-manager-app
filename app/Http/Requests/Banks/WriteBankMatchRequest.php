<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The texts the AI writes a rule from: pasted lines, or transactions of the family (one to five of them).
 */
final class WriteBankMatchRequest extends BankMatchRequest
{
    /**
     * The rules: pasted texts or ids of transactions, from one to five.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'examples' => ['required_without:transactions', 'array', 'list', 'min:1', 'max:5'],
            'examples.*' => ['string', 'max:500'],
            'transactions' => ['required_without:examples', 'array', 'list', 'min:1', 'max:5'],
            'transactions.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * The texts to write the rule from: the pasted ones, then the texts of the transactions.
     *
     * @return list<string>
     *
     * @throws NotFoundHttpException when a transaction is not of the family
     */
    public function texts(): array
    {
        /** @var list<string> $pasted */
        $pasted = array_values(array_filter((array) $this->validated('examples', []), is_string(...)));
        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, (array) $this->validated('transactions', []))));
        $found = BankTransaction::query()->whereIn('id', $ids)->orderBy('id')->get();

        // The scope of the family leaves out the ones of other families: any missing id is a 404
        abort_unless($found->count() === count($ids), 404);

        return [...$pasted, ...$found->map(static fn (BankTransaction $transaction): string => $transaction->text)->all()];
    }
}
