<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Model;

/**
 * A loan: a mortgage, a loan, a purchase in instalments. The totals, the
 * instalments paid and left and the last instalment are calculated, so the
 * form does not ask them: the card shows them as they are saved.
 */
final class LoanCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Banks;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Loan');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('Mortgage, loan, purchase in instalments');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Loan::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('name', __('Name'), FieldKind::Text, required: true),
            new CardField('organization_id', __('Lender'), FieldKind::Organization),
            new CardField('person_id', __('Account holder'), FieldKind::Person),
            new CardField('financial_account_id', __('Debit account'), FieldKind::Card, target: 'financial_account'),
            new CardField('total_amount', __('Amount financed'), FieldKind::Currency),
            new CardField('installments_count', __('Number of instalments'), FieldKind::Number),
            new CardField('installment_amount', __('Instalment'), FieldKind::Currency),
            new CardField('final_installment_amount', __('Amount of the last instalment'), FieldKind::Currency),
            new CardField('start_date', __('Start'), FieldKind::Date),
            $this->status(LoanStatus::class),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [new CardDate('end_date', __('Last instalment'), expires: false)];
    }

    /**
     * The lender, for example «Findomestic».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, Loan::class)->organization->name ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['financialAccount'];
    }
}
