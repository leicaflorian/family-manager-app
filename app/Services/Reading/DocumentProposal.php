<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\DocumentProposalData;
use App\Data\Reading\Extraction;
use App\Data\Reading\FoundLineData;
use App\Data\Reading\SupplyData;
use App\Enums\UtilityType;
use App\Models\Employment;
use App\Models\Utility;
use App\Support\CurrentFamily;
use App\Support\Money;
use App\Support\Reading\BillTitle;
use App\Support\Reading\Choices;
use App\Support\Reading\TypeCategory;
use App\Support\Reading\ValidDate;
use Carbon\CarbonImmutable;

/**
 * What the reading proposes for a document, the `document` profile of the old reader: title, category, dates,
 * issuer, person, the cards to link, and the headline and lines of the box on top of the form.
 *
 * The cards are found with the `Matcher` inside the family of the context, so a code printed on the paper never
 * links a card of another family, whoever is signed in.
 */
final readonly class DocumentProposal
{
    /** The category names that tell a paper is about a job, in both languages (a payslip, the annual income statement). */
    private const string WORK_CATEGORY = '/lavoro|\bwork\b|buste paga|payslips?|\bcu\b/iu';

    /**
     * @param  Matcher  $matcher  the exact searches of the cards
     * @param  LinkResolver  $links  what the identifiers on the paper point to
     */
    public function __construct(private Matcher $matcher, private LinkResolver $links) {}

    /**
     * The proposal of a document.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from; its family is where the cards are searched
     * @param  string  $locale  the language of the texts (`it` or `en`)
     */
    public function of(Extraction $x, ReadingContext $context, string $locale): DocumentProposalData
    {
        return $context->familyId === null
            ? $this->build($x, $context, $locale)
            : CurrentFamily::using($context->familyId, fn (): DocumentProposalData => $this->build($x, $context, $locale));
    }

    /**
     * Builds the proposal in the current family.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  string  $locale  the language of the texts
     */
    private function build(Extraction $x, ReadingContext $context, string $locale): DocumentProposalData
    {
        $links = $this->links->resolve($x, $context);
        $utility = $links->utility;
        $organizationId = $links->organizationId;
        $personId = $links->personId;
        $issuer = $links->issuer;
        $date = ValidDate::of($x->issue_date) ?? ValidDate::of($x->due_date);
        $category = Choices::pick($context->documentCategories, $x->document_category_id);
        $titleDate = $x->kind === 'payslip' ? ValidDate::of($x->period_start) ?? $date : $date;
        $types = $this->types($x, $utility);
        $utilities = $x->isMultiSupply() ? $links->utilities : array_filter([$utility]);

        $cards = array_values(array_filter([
            ...array_map(fn (Utility $found): ?array => $this->link('utility', $found->id), $utilities),
            $this->link('vehicle', $links->vehicleId),
            $this->link('insurance_policy', $links->policy?->id),
            $this->link('subscription', $links->subscription?->id),
            $this->link('employment', $this->employment($x, $context, $category, $personId, $organizationId, $titleDate)?->id),
        ]));

        return new DocumentProposalData(
            title: BillTitle::of($x, $issuer ?? __('Document', [], $locale), $types, $links->vehicle, $titleDate, $locale),
            category_id: $x->isMultiSupply() ? $this->typesCategory($types, $context, $locale) ?? $category : $category,
            person_id: $personId,
            organization_id: $organizationId,
            date: $date,
            expiry_date: in_array($x->kind, Extraction::NO_EXPIRY, true) ? null : ValidDate::of($x->expiry_date),
            links: $cards,
            headline: $this->headline($x->kind, $types, $locale),
            found: $this->found($x, $issuer, $locale),
        );
    }

    /**
     * The types of supply of a bill: of each supply when there are more, otherwise of the utility found or as read.
     *
     * @param  Extraction  $x  what the model read
     * @param  Utility|null  $utility  the utility found for the only supply
     * @return list<string> values of `UtilityType`, without repetitions
     */
    private function types(Extraction $x, ?Utility $utility): array
    {
        $types = $x->isMultiSupply()
            ? array_map(static fn (SupplyData $supply): ?string => $supply->utility_type, $x->supplies)
            : [$utility->type->value ?? $x->utility_type];

        return array_values(array_unique(array_filter($types, is_string(...))));
    }

    /**
     * The main document category the categories of all the types share («Casa e utenze» for «Luce» and «Gas»).
     *
     * @param  list<string>  $types  values of `UtilityType`
     * @param  ReadingContext  $context  the lists of the family
     * @param  string  $locale  the language the categories are named in
     */
    private function typesCategory(array $types, ReadingContext $context, string $locale): ?int
    {
        return TypeCategory::commonMain(array_map(UtilityType::from(...), $types), $context->documentCategories, $locale);
    }

    /**
     * The employment of the person, searched only for a payslip or a paper filed under work.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  int|null  $category  the document category chosen
     * @param  int|null  $personId  the person of the paper
     * @param  int|null  $organizationId  the issuer
     * @param  string|null  $date  the date that decides which job was in progress
     */
    private function employment(Extraction $x, ReadingContext $context, ?int $category, ?int $personId, ?int $organizationId, ?string $date): ?Employment
    {
        $label = Choices::label($context->documentCategories, $category) ?? '';

        if ($x->kind !== 'payslip' && preg_match(self::WORK_CATEGORY, $label) !== 1) {
            return null;
        }

        return $this->matcher->employment($personId, $organizationId, ValidDate::carbon($date));
    }

    /**
     * The entry of a card in the links.
     *
     * @param  string  $type  the type as the morph map writes it
     * @param  int|null  $id  the id of the card
     * @return array{type: string, id: int}|null null when there is no card
     */
    private function link(string $type, ?int $id): ?array
    {
        return $id === null ? null : ['type' => $type, 'id' => $id];
    }

    /**
     * The sentence of the box on top of the form: what the paper is.
     *
     * @param  string|null  $kind  one of `Extraction::KINDS`
     * @param  list<string>  $types  the kinds of supply of a bill, one for each different one
     * @param  string  $locale  the language of the sentence
     */
    private function headline(?string $kind, array $types, string $locale): string
    {
        return match ($kind) {
            'utility_bill' => count($types) > 1 ? __(BillTitle::isElectricityAndGas($types) ? 'I found an electricity and gas bill' : 'I found a bill', [], $locale) : match ($types[0] ?? null) {
                UtilityType::Electricity->value => __('I found an electricity bill', [], $locale),
                UtilityType::Gas->value => __('I found a gas bill', [], $locale),
                UtilityType::Water->value => __('I found a water bill', [], $locale),
                UtilityType::Internet->value => __('I found an internet bill', [], $locale),
                UtilityType::Phone->value => __('I found a phone bill', [], $locale),
                UtilityType::WasteTax->value => __('I found a waste tax bill', [], $locale),
                UtilityType::TvLicense->value => __('I found a TV license bill', [], $locale),
                default => __('I found a bill', [], $locale),
            },
            'receipt' => __('I found a receipt', [], $locale),
            'invoice' => __('I found an invoice', [], $locale),
            'payment_receipt' => __('I found a payment receipt', [], $locale),
            'payslip' => __('I found a payslip', [], $locale),
            'insurance' => __('I found an insurance document', [], $locale),
            'road_tax' => __('I found a road tax notice', [], $locale),
            'medical' => __('I found a medical document', [], $locale),
            default => __('I found a document', [], $locale),
        };
    }

    /**
     * The lines under the headline, only for what the paper has: who sent it, how much, due on.
     *
     * @param  Extraction  $x  what the model read
     * @param  string|null  $issuer  who made the paper
     * @param  string  $locale  the language of the labels
     * @return list<FoundLineData>
     */
    private function found(Extraction $x, ?string $issuer, string $locale): array
    {
        $due = ValidDate::carbon($x->due_date);
        $lines = [
            $issuer === null ? null : new FoundLineData(__('Who sent it', [], $locale), $issuer),
            $x->total_amount === null ? null : new FoundLineData(__('How much', [], $locale), $this->howMuch($x, $x->total_amount, $locale)),
            $due instanceof CarbonImmutable ? new FoundLineData(__('Due on', [], $locale), $due->format('d/m/Y')) : null,
        ];

        return array_values(array_filter($lines));
    }

    /**
     * The total of the paper and, with more than one supply, the amount of each: «165,65 € · luce 96,25 €, gas 69,40 €».
     *
     * @param  Extraction  $x  what the model read
     * @param  float  $total  the total to pay
     * @param  string  $locale  the language of the names of the types
     */
    private function howMuch(Extraction $x, float $total, string $locale): string
    {
        $parts = [];
        foreach ($x->isMultiSupply() ? $x->supplies : [] as $supply) {
            if ($supply->amount !== null) {
                $type = $supply->utility_type === null ? '' : mb_strtolower(BillTitle::typeLabel($supply->utility_type, $locale)).' ';
                $parts[] = $type.Money::euro($supply->amount);
            }
        }

        return Money::euro($total).($parts === [] ? '' : ' · '.implode(', ', $parts));
    }
}
