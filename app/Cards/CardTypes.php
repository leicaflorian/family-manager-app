<?php

declare(strict_types=1);

namespace App\Cards;

use App\Cards\Types\BelongingCard;
use App\Cards\Types\EmploymentCard;
use App\Cards\Types\EnrollmentCard;
use App\Cards\Types\FilingCard;
use App\Cards\Types\FinancialAccountCard;
use App\Cards\Types\HealthEventCard;
use App\Cards\Types\HomeCard;
use App\Cards\Types\InsurancePolicyCard;
use App\Cards\Types\LoanCard;
use App\Cards\Types\MaintenanceRecordCard;
use App\Cards\Types\SubscriptionCard;
use App\Cards\Types\UtilityCard;
use App\Cards\Types\VehicleCard;
use App\Enums\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The register of the 13 card types.
 */
final class CardTypes
{
    /**
     * The descriptions, in the order of *New card*.
     *
     * @var list<class-string<CardType>>
     */
    private const array TYPES = [
        HomeCard::class,
        UtilityCard::class,
        BelongingCard::class,
        MaintenanceRecordCard::class,
        VehicleCard::class,
        InsurancePolicyCard::class,
        FinancialAccountCard::class,
        LoanCard::class,
        SubscriptionCard::class,
        FilingCard::class,
        EmploymentCard::class,
        HealthEventCard::class,
        EnrollmentCard::class,
    ];

    /**
     * Every card type, in the order of *New card*.
     *
     * @return list<CardType>
     */
    public static function all(): array
    {
        return array_map(static fn (string $class): CardType => new $class, self::TYPES);
    }

    /**
     * The card type of a type of the morph map.
     *
     * @param  string  $type  for example `insurance_policy`
     * @return CardType|null the card type, or null if the type is not a card
     */
    public static function find(string $type): ?CardType
    {
        foreach (self::all() as $card) {
            if ($card->type() === $type) {
                return $card;
            }
        }

        return null;
    }

    /**
     * The card type of a type in an address.
     *
     * @param  string  $type  for example `insurance_policy`
     *
     * @throws NotFoundHttpException if the type is not a card
     */
    public static function findOrFail(string $type): CardType
    {
        return self::find($type) ?? throw new NotFoundHttpException;
    }

    /**
     * Whether the person may add cards (any type: the permission is the same for all).
     *
     * @param  User  $user  whoever signed in
     */
    public static function canCreate(User $user): bool
    {
        return $user->can('create', self::all()[0]->model());
    }

    /**
     * The card types of an area, in the order of *New card*.
     *
     * @return list<CardType>
     */
    public static function of(Area $area): array
    {
        return array_values(array_filter(self::all(), static fn (CardType $card): bool => $card->area() === $area));
    }

    /**
     * The card type of a card.
     *
     * @param  Model  $card  a record of one of the 13 models
     *
     * @throws InvalidArgumentException if the record is not a card
     */
    public static function for(Model $card): CardType
    {
        foreach (self::all() as $type) {
            if ($type->model() === $card::class) {
                return $type;
            }
        }

        throw new InvalidArgumentException($card::class.' is not a card.');
    }
}
