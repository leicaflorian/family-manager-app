<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Banks\SuggestBankFilter;
use App\Data\BankTransactionData;
use App\Exceptions\Reading\ReadingFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banks\BankMatchPreviewRequest;
use App\Http\Requests\Banks\BankMatchRequest;
use App\Http\Requests\Banks\WriteBankMatchRequest;
use App\Models\BankTransaction;
use App\Services\Banks\BankMatchPreview;
use App\Support\Banks\BankMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The calls of the field *How it shows in the bank* of a planned expense or income, answered as JSON: the
 * transactions a rule recognises, the transactions to take examples from and the rule the AI writes. Only who can
 * create a planned expense, while the link to the bank is on.
 */
final class BankMatchController extends Controller
{
    /** How many transactions to sort the sheet offers as examples. */
    private const int EXAMPLES = 30;

    /**
     * The transactions a rule recognises among the booked ones of the last 90 days: `{count, rows}`, 422 when the
     * rule does not work.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function matches(BankMatchPreviewRequest $request): JsonResponse
    {
        return response()->json(BankMatchPreview::for($request->pattern(), $request->kind(), $request->accountId()));
    }

    /**
     * The last 30 transactions to sort in the direction of the form, the examples the sheet lets choose from.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function examples(BankMatchRequest $request): JsonResponse
    {
        $rows = BankTransaction::query()
            ->with('financialAccount')
            ->unsorted()
            ->where('amount', $request->kind() === 'income' ? '>' : '<', 0)
            ->when($request->accountId() !== null, static fn ($query) => $query->where('financial_account_id', $request->accountId()))
            ->latest('booked_on')
            ->orderByDesc('id')
            ->limit(self::EXAMPLES)
            ->get();

        return response()->json(['rows' => $rows->map(static fn (BankTransaction $row): BankTransactionData => BankTransactionData::fromTransaction($row))->all()]);
    }

    /**
     * The rule the AI writes from the examples, with the sentence that explains it and what it recognises. The AI now
     * proposes the words of a bank filter (`SuggestBankFilter`); here they become a rule that needs every one of them.
     * A reading that is off, used up or without an answer is a 422 with its message under `examples`.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when a transaction is not of the family
     * @throws ValidationException when the reading is off, used up or gives no answer
     */
    public function rule(WriteBankMatchRequest $request, SuggestBankFilter $suggest): JsonResponse
    {
        $sign = $request->kind() === 'income' ? '1.00' : '-1.00';
        $examples = array_map(static fn (string $text): BankTransaction => (new BankTransaction)->forceFill(['text' => $text, 'amount' => $sign, 'financial_account_id' => 0]), $request->texts());

        try {
            $suggestion = $suggest->handle($examples, [], SuggestBankFilter::WORDS, app()->getLocale());
        } catch (ReadingFailed $failure) {
            report($failure);

            throw ValidationException::withMessages(['examples' => $failure->getMessage()]);
        } catch (ValidationException $refused) {
            throw ValidationException::withMessages(['examples' => (string) ($refused->validator->errors()->first('keep'))]);
        }

        $pattern = implode('', array_map(static fn (string $word): string => '(?=.*'.preg_quote(BankMatch::normalize($word), '~').')', $suggestion->contains));

        return response()->json([
            'pattern' => '^'.$pattern,
            'explanation' => $suggestion->explanation,
            ...BankMatchPreview::for('^'.$pattern, $request->kind(), $request->accountId()),
        ]);
    }
}
