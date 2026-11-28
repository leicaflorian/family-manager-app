<?php

declare(strict_types=1);

namespace App\Support\Reading;

use App\Data\Reading\Extraction;
use App\Enums\UtilityType;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * The title the reading proposes for a paper: what it is, the issuer, and the month and year of its date. A bill
 * with more than one supply names all their types («Bolletta luce e gas novembre 2026»).
 */
final class BillTitle
{
    /**
     * The title of a paper.
     *
     * @param  Extraction  $x  what the model read
     * @param  string  $issuer  who made the paper
     * @param  list<string>  $utilityTypes  the types of supply of a bill (values of `UtilityType`), one per supply, in order
     * @param  Vehicle|null  $vehicle  the vehicle of a road tax
     * @param  string|null  $date  `YYYY-MM-DD`
     * @param  string  $locale  the language of the title
     */
    public static function of(Extraction $x, string $issuer, array $utilityTypes, ?Vehicle $vehicle, ?string $date, string $locale): string
    {
        $parsed = ValidDate::carbon($date);
        $year = $parsed?->format('Y');
        $month = $parsed instanceof CarbonImmutable ? ReadingText::month($parsed, $locale) : null;
        $with = ['issuer' => $issuer, 'month' => $month, 'year' => $year];
        $put = static fn (string $without, string $withDate, array $more = []): string => __($parsed instanceof CarbonImmutable ? $withDate : $without, [...$with, ...$more], $locale);
        $types = array_values(array_unique($utilityTypes));

        return match ($x->kind) {
            'utility_bill' => count($types) > 1 ? self::manyTypes($types, $put, $locale) : match ($types[0] ?? null) {
                'electricity' => $put('Electricity bill', 'Electricity bill :month :year'),
                'gas' => $put('Gas bill', 'Gas bill :month :year'),
                'water' => $put('Water bill', 'Water bill :month :year'),
                'internet' => $put('Internet bill', 'Internet bill :month :year'),
                'phone' => $put('Phone bill', 'Phone bill :month :year'),
                'waste_tax' => $put('Waste tax bill', 'Waste tax bill :month :year'),
                'tv_license' => $put('TV license bill', 'TV license bill :month :year'),
                default => $put('Bill :issuer', 'Bill :issuer :month :year'),
            },
            'receipt' => __('Receipt :issuer', $with, $locale),
            'invoice' => $put('Invoice :issuer', 'Invoice :issuer :month :year'),
            'payment_receipt' => $put('Payment receipt :issuer', 'Payment receipt :issuer :month :year'),
            'payslip' => $put('Payslip', 'Payslip :month :year'),
            'road_tax' => __($year === null ? 'Road tax :vehicle' : 'Road tax :vehicle :year', [...$with, 'vehicle' => $vehicle->name ?? $x->plate_number ?? __('car', [], $locale)], $locale),
            default => __($parsed instanceof CarbonImmutable ? ':title :month :year' : ':title', [...$with, 'title' => $x->title ?? $issuer], $locale),
        };
    }

    /**
     * Whether the types are electricity and gas, the two of a dual-fuel bill, in any order.
     *
     * @param  list<string>  $types  the different types of the supplies
     */
    public static function isElectricityAndGas(array $types): bool
    {
        return count($types) === 2 && array_diff($types, [UtilityType::Electricity->value, UtilityType::Gas->value]) === [];
    }

    /**
     * The name of a type of supply as the person reads it, in the given language («Luce»).
     *
     * @param  string  $type  a value of `UtilityType`
     * @param  string  $locale  the language of the name
     */
    public static function typeLabel(string $type, string $locale): string
    {
        return __("enums.utility_type.{$type}", [], $locale);
    }

    /**
     * The types of a list joined in a sentence, lower case: «luce e acqua», «luce, gas e acqua».
     *
     * @param  list<string>  $types  values of `UtilityType`
     * @param  string  $locale  the language of the names
     */
    public static function typesText(array $types, string $locale): string
    {
        $names = array_map(static fn (string $type): string => mb_strtolower(self::typeLabel($type, $locale)), $types);
        $last = array_pop($names);

        return $names === [] ? (string) $last : __(':first and :second', ['first' => implode(', ', $names), 'second' => $last], $locale);
    }

    /**
     * The title of a bill with supplies of more than one type.
     *
     * @param  list<string>  $types  the different types, in order
     * @param  callable(string, string, array<string, string>=): string  $put  the title with or without the date
     * @param  string  $locale  the language of the title
     */
    private static function manyTypes(array $types, callable $put, string $locale): string
    {
        return self::isElectricityAndGas($types)
            ? $put('Electricity and gas bill', 'Electricity and gas bill :month :year')
            : self::upperFirst($put(':types bill', ':types bill :month :year', ['types' => self::typesText($types, $locale)]));
    }

    /**
     * The text with its first letter in upper case.
     */
    private static function upperFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
