<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReadsMonth;
use App\Models\Utility;
use App\Services\ConsumptionPage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The address of the Consumption page: `?utility=12&to=2026-09&month=2026-01`. A utility that is not an electricity,
 * gas or water utility of the family, or a month that cannot be read, is a wrong address and answers 404.
 */
final class ConsumptionRequest extends FormRequest
{
    use ReadsMonth;

    /** The utility of the address, found in `prepareForValidation()`. */
    private ?Utility $resolvedUtility = null;

    /** @var Collection<int, Utility>|null the measured utilities of the family, read once */
    private ?Collection $resolvedUtilities = null;

    /**
     * Finds the utility before the authorization and the rules, so a utility of another family answers 404 whatever
     * else is wrong.
     *
     * @throws NotFoundHttpException if the utility is not a measured utility of the family, or the family has none
     */
    protected function prepareForValidation(): void
    {
        $this->utility();
    }

    /**
     * The rules of the address.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...self::MONTH_RULES, 'to' => ['nullable', 'date_format:Y-m']];
    }

    /**
     * The utility asked, else the one the page opens on.
     *
     * @throws NotFoundHttpException if the utility is not a measured utility of the family, or the family has none
     */
    public function utility(): Utility
    {
        if ($this->resolvedUtility instanceof Utility) {
            return $this->resolvedUtility;
        }

        $id = $this->query('utility');

        if ($id !== null && (! is_string($id) || ! ctype_digit($id))) {
            throw new NotFoundHttpException;
        }

        return $this->resolvedUtility = resolve(ConsumptionPage::class)->choose($this->utilities(), $id === null ? null : (int) $id);
    }

    /**
     * The measured utilities of the family, read once for the choice and for the menu of the page.
     *
     * @return Collection<int, Utility>
     */
    public function utilities(): Collection
    {
        return $this->resolvedUtilities ??= resolve(ConsumptionPage::class)->utilities();
    }

    /**
     * The last month asked, null for the latest with a bill.
     *
     * @throws NotFoundHttpException if the month cannot be read
     */
    public function to(): ?CarbonImmutable
    {
        $to = $this->query('to');

        return is_string($to) && $to !== ''
            ? CarbonImmutable::createFromFormat('!Y-m', $to, 'Europe/Rome') ?: throw new NotFoundHttpException
            : null;
    }

    /**
     * The month asked for the detail, null for none.
     *
     * @throws NotFoundHttpException if the month cannot be read
     */
    public function detailMonth(): ?CarbonImmutable
    {
        return $this->filled('month') ? $this->month() : null;
    }
}
