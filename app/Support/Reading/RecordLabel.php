<?php

declare(strict_types=1);

namespace App\Support\Reading;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Enums\RecordKind;
use App\Models\Belonging;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\CardSummaries;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * How a card the reading found, or the person picked, is named in *What I also do*: `Luce (IT001E…)`,
 * `Panda (GH482TK)`, the number of a policy (its title without one), `Tagliando (12/10/2026)`; any other card as
 * *Cards* names it.
 */
final class RecordLabel
{
    /**
     * The model class of the cards of a kind.
     *
     * @param  RecordKind  $kind  which card
     * @param  string|null  $cardType  the type of the morph map of a `Card` (`home`)
     * @return class-string<Model>
     *
     * @throws LogicException if a `Card` has no card type, or one that is not a card
     */
    public static function model(RecordKind $kind, ?string $cardType = null): string
    {
        return match ($kind) {
            RecordKind::Organization => Organization::class,
            RecordKind::Vehicle => Vehicle::class,
            RecordKind::Utility => Utility::class,
            RecordKind::Policy => InsurancePolicy::class,
            RecordKind::Belonging => Belonging::class,
            RecordKind::Maintenance => MaintenanceRecord::class,
            RecordKind::Card => self::cardModel($cardType),
        };
    }

    /**
     * The name of a card.
     *
     * @param  Model  $card  the card, or the organization
     */
    public static function of(Model $card): string
    {
        return match (true) {
            $card instanceof Utility => ucfirst($card->type->label()).($card->supply_point_code === null ? '' : ' ('.$card->supply_point_code.')'),
            $card instanceof Vehicle => $card->name.($card->plate_number === null ? '' : ' ('.$card->plate_number.')'),
            $card instanceof InsurancePolicy => $card->policy_number ?? $card->title ?? (string) $card->id,
            $card instanceof MaintenanceRecord => $card->title.' ('.$card->date->format('d/m/Y').')',
            $card instanceof Organization, $card instanceof Belonging => $card->name,
            default => resolve(CardSummaries::class)->label($card),
        };
    }

    /**
     * A card as a box links it: its id and its name.
     *
     * @param  Model|null  $card  the card, if one was found
     * @return array{id: int, title: string}|null
     */
    public static function linked(?Model $card): ?array
    {
        return $card instanceof Model ? ['id' => self::id($card), 'title' => self::of($card)] : null;
    }

    /**
     * The id of a card.
     *
     * @param  Model  $card  the card
     */
    public static function id(Model $card): int
    {
        $key = $card->getKey();

        return is_numeric($key) ? (int) $key : 0;
    }

    /**
     * The model of a card type.
     *
     * @param  string|null  $cardType  the type of the morph map
     * @return class-string<Model>
     *
     * @throws LogicException if the type is missing or not a card
     */
    private static function cardModel(?string $cardType): string
    {
        $card = $cardType === null ? null : CardTypes::find($cardType);

        return $card instanceof CardType ? $card->model() : throw new LogicException('A card box needs its card type.');
    }
}
