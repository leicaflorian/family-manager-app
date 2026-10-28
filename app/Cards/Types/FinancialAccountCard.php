<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\FinancialAccountStatus;
use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * A financial account: a current account or a card. Only the last four
 * digits are saved, never the full numbers.
 */
final class FinancialAccountCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Banks;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Account or card');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('A current account or a card');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return FinancialAccount::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('name', __('Name'), FieldKind::Text, required: true),
            new CardField('type', __('Type'), FieldKind::Dropdown, required: true, enum: FinancialAccountType::class),
            new CardField('organization_id', __('Bank'), FieldKind::Organization),
            new CardField('person_id', __('Account holder'), FieldKind::Person),
            new CardField('last_four_digits', __('Last 4 digits'), FieldKind::Text),
            new CardField('is_default', __('Default account'), FieldKind::Toggle),
            $this->status(FinancialAccountStatus::class),
            $this->notes(),
        ];
    }

    /**
     * The last four digits, for example «•••• 4821», or else the bank.
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        $account = $this->card($card, FinancialAccount::class);

        return $account->last_four_digits !== null && $account->last_four_digits !== ''
            ? '•••• '.$account->last_four_digits
            : $account->organization->name ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['loans', 'subscriptions'];
    }

    /**
     * That this is the default account, when it is.
     *
     * {@inheritDoc}
     */
    public function notices(Model $card): array
    {
        return $this->card($card, FinancialAccount::class)->is_default
            ? [__('It is the default account. Full IBANs, card numbers and codes are not saved here.')]
            : [];
    }
}
