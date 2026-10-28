<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription: streaming, software, cloud, domains.
 */
final class SubscriptionCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Subscriptions;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Subscription');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('Streaming, software, cloud, domains');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Subscription::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('name', __('Name'), FieldKind::Text, required: true),
            new CardField('type', __('Type'), FieldKind::Dropdown, required: true, enum: SubscriptionType::class),
            new CardField('organization_id', __('Supplier'), FieldKind::Organization),
            new CardField('person_id', __('Account holder'), FieldKind::Person),
            new CardField('account', __('Account'), FieldKind::Text),
            new CardField('financial_account_id', __('Account or card'), FieldKind::Card, target: 'financial_account'),
            new CardField('activation_date', __('Activation'), FieldKind::Date),
            new CardField('renewal_date', __('Renewal'), FieldKind::Date),
            new CardField('is_auto_renew', __('Automatic renewal'), FieldKind::Toggle),
            new CardField('trial_end_date', __('End of the trial'), FieldKind::Date),
            new CardField('manage_url', __('Link'), FieldKind::Text),
            $this->status(SubscriptionStatus::class),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [
            new CardDate('renewal_date', __('Renewal'), expires: true),
            // There is no «in trial» status: once the trial end has passed the trial is over, so it is never overdue.
            new CardDate('trial_end_date', __('End of the trial'), expires: true, overdueWhile: static fn (): bool => false),
        ];
    }

    /**
     * The supplier, for example «Netflix».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, Subscription::class)->organization->name ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['financialAccount'];
    }

    /** {@inheritDoc} */
    public function subtitleRelations(): array
    {
        return ['organization'];
    }
}
