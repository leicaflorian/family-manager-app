<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use App\Cards\CardField;
use App\Cards\CardRules;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Cards\FieldKind;
use App\Enums\InsurancePolicyStatus;
use App\Http\Requests\Concerns\UploadsFiles;
use App\Models\Document;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The form of a card, new or changed (only an `admin`). The fields and their
 * rules come from the description of the card type in the route; new or
 * changed, it can also bring the files of its documents and the documents to
 * link to it or unlink from it, chosen in the form.
 */
final class SaveCardRequest extends FormRequest
{
    use UploadsFiles;

    /**
     * The card type of the `type` of the route, for example `vehicle`.
     *
     * @throws NotFoundHttpException if the type is not a card
     */
    public function cardType(): CardType
    {
        $type = $this->route('type');

        return is_string($type) ? CardTypes::findOrFail($type) : throw new NotFoundHttpException;
    }

    /**
     * The card of the `id` of the route, or null on a new card.
     *
     * @throws ModelNotFoundException if the family has no such card (it answers 404)
     */
    public function card(): ?Model
    {
        $id = $this->route()?->parameters()['id'] ?? null;

        return is_string($id) || is_int($id) ? $this->cardType()->findOrFail($id) : null;
    }

    /**
     * A new card whoever adds them, a change whoever may change it.
     */
    public function authorize(): bool
    {
        $card = $this->card();

        return $card instanceof Model
            ? $this->user()?->can('update', $card) ?? false
            : $this->user()?->can('create', $this->cardType()->model()) ?? false;
    }

    /**
     * The rules of the fields, of the files and of the documents to link and unlink.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...CardRules::for($this->cardType()),
            ...$this->filesRules(required: false),
            'link_documents' => ['array'],
            'link_documents.*' => ['integer'],
            'unlink_documents' => ['array'],
            'unlink_documents.*' => ['integer'],
            ...$this->replacementRules(),
        ];
    }

    /**
     * Checks the policies to replace once the shape of the form is right: one of another family answers 404, one that
     * is not active or expired, or that the account cannot change, is an error of the field.
     *
     * @return list<Closure(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->canReplace()) {
                return;
            }

            $user = $this->user();

            foreach ($this->policiesToReplace() as $policy) {
                if (! in_array($policy->status, [InsurancePolicyStatus::Active, InsurancePolicyStatus::Expired], true) || ! ($user?->can('update', $policy) ?? false)) {
                    $validator->errors()->add('replace_policies', __('This policy cannot be replaced.'));
                }
            }
        }];
    }

    /**
     * The old policies this new policy replaces; empty unless it is a new policy and the form sends some.
     *
     * @return SupportCollection<int, InsurancePolicy>
     *
     * @throws ModelNotFoundException if one is not of the family (it answers 404)
     */
    public function policiesToReplace(): SupportCollection
    {
        if (! $this->canReplace()) {
            return new SupportCollection;
        }

        return InsurancePolicy::query()->findOrFail($this->ids('replace_policies'))->toBase();
    }

    /**
     * The payment just made that moves to the new policy, or null when there is none or it is not a payment of the
     * premium of one of the policies replaced.
     *
     * A payment deleted meanwhile, or of another family, is not found and ignored.
     */
    public function paymentToMove(): ?Expense
    {
        $id = $this->input('replace_payment');
        $old = $this->policiesToReplace();

        if (! is_numeric($id) || $old->isEmpty()) {
            return null;
        }

        $payment = Expense::query()->with('plannedExpense')->find((int) $id);
        $planned = $payment?->plannedExpense;

        return $payment !== null && $planned !== null
            && $planned->card_type === (new InsurancePolicy)->getMorphClass()
            && $old->contains('id', $planned->card_id)
                ? $payment
                : null;
    }

    /**
     * The documents to link to the card.
     *
     * @return Collection<int, Document>
     *
     * @throws ModelNotFoundException if one is not of the family (it answers 404)
     */
    public function documentsToLink(): Collection
    {
        return $this->documents('link_documents');
    }

    /**
     * The documents to unlink from the card.
     *
     * @return Collection<int, Document>
     *
     * @throws ModelNotFoundException if one is not of the family (it answers 404)
     */
    public function documentsToUnlink(): Collection
    {
        return $this->documents('unlink_documents');
    }

    /**
     * The messages of the files that say what to do.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->filesMessages();
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = [];

        foreach ($this->cardType()->fields() as $field) {
            $labels[$field->column] = mb_strtolower($field->label);
        }

        return $labels;
    }

    /**
     * An untouched switch arrives as null; a yes/no column is never null, so it
     * counts as off.
     */
    protected function prepareForValidation(): void
    {
        $off = [];

        foreach ($this->cardType()->fields() as $field) {
            if ($field->kind === FieldKind::Toggle && $this->has($field->column) && $this->input($field->column) === null) {
                $off[$field->column] = false;
            }
        }

        $this->merge($off);
    }

    /**
     * The validated columns of the card: without the virtual fields, with the
     * fields whose condition does not hold emptied (a policy that goes from car
     * to home loses its vehicle even if the form still sends it).
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $columns = [];

        foreach ($this->cardType()->fields() as $field) {
            if ($field->virtual) {
                continue;
            }

            if (! $this->isShown($field, $validated)) {
                $columns[$field->column] = null;
            } elseif (array_key_exists($field->column, $validated)) {
                $columns[$field->column] = $validated[$field->column];
            }
        }

        // A status left empty keeps the default of the column.
        if (array_key_exists('status', $columns) && $columns['status'] === null) {
            unset($columns['status']);
        }

        return $columns;
    }

    /**
     * The rules of the policies to replace, only for a new policy.
     *
     * @return array<string, list<string>>
     */
    private function replacementRules(): array
    {
        if (! $this->canReplace()) {
            return [];
        }

        return [
            'replace_policies' => ['array', 'list'],
            'replace_policies.*' => ['integer'],
            'replace_payment' => ['nullable', 'integer'],
        ];
    }

    /**
     * Whether the form can replace policies: a new insurance policy.
     */
    private function canReplace(): bool
    {
        return $this->cardType()->type() === 'insurance_policy' && ! $this->card() instanceof Model;
    }

    /**
     * The ids of a list of the form, as numbers.
     *
     * @param  string  $key  the list
     * @return list<int>
     */
    private function ids(string $key): array
    {
        return array_values($this->collect($key)->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->unique()->all());
    }

    /**
     * The documents of the family whose ids are in a list of the form.
     *
     * @param  string  $key  the list, `link_documents` or `unlink_documents`
     * @return Collection<int, Document>
     *
     * @throws ModelNotFoundException if one is not of the family (it answers 404)
     */
    private function documents(string $key): Collection
    {
        // The rule lets "5" through as well as 5
        return Document::query()->findOrFail($this->ids($key));
    }

    /**
     * Whether the condition of the field holds with the values sent.
     *
     * @param  CardField  $field  the field
     * @param  array<string, mixed>  $values  the validated values
     */
    private function isShown(CardField $field, array $values): bool
    {
        foreach ($field->showWhen as $column => $allowed) {
            $value = $values[$column] ?? null;

            if (! is_string($value) || ! in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
