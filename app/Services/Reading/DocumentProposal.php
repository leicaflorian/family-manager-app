<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\DocumentProposalData;
use App\Data\Reading\Extraction;
use App\Data\Reading\FoundLineData;
use App\Enums\UtilityType;
use App\Models\Employment;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use App\Support\Reading\Choices;
use App\Support\Reading\ReadingText;
use App\Support\Reading\ValidDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

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
        $vehicle = $links->vehicle;
        $organizationId = $links->organizationId;
        $personId = $links->personId;
        $issuer = $links->issuer;
        $date = ValidDate::of($x->issue_date) ?? ValidDate::of($x->due_date);
        $category = Choices::pick($context->documentCategories, $x->document_category_id);
        $titleDate = $x->kind === 'payslip' ? ValidDate::of($x->period_start) ?? $date : $date;

        $cards = array_values(array_filter([
            $this->link('utility', $utility?->id),
            $this->link('vehicle', $links->vehicleId),
            $this->link('insurance_policy', $links->policy?->id),
            $this->link('subscription', $links->subscription?->id),
            $this->link('employment', $this->employment($x, $context, $category, $personId, $organizationId, $titleDate)?->id),
        ]));

        return new DocumentProposalData(
            title: $this->title($x, $issuer ?? __('Document', [], $locale), $utility->type->value ?? $x->utility_type, $vehicle, $titleDate, $locale),
            category_id: $category,
            person_id: $personId,
            organization_id: $organizationId,
            date: $date,
            expiry_date: in_array($x->kind, Extraction::NO_EXPIRY, true) ? null : ValidDate::of($x->expiry_date),
            links: $cards,
            headline: $this->headline($x->kind, $utility->type->value ?? $x->utility_type, $locale),
            found: $this->found($x, $issuer, $locale),
        );
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
     * The title of the document: what the paper is, the issuer, and the month and year of its date.
     *
     * @param  Extraction  $x  what the model read
     * @param  string  $issuer  who made the paper
     * @param  string|null  $utilityType  the kind of supply of a bill
     * @param  Vehicle|null  $vehicle  the vehicle of a road tax
     * @param  string|null  $date  `YYYY-MM-DD`
     * @param  string  $locale  the language of the title
     */
    private function title(Extraction $x, string $issuer, ?string $utilityType, ?Vehicle $vehicle, ?string $date, string $locale): string
    {
        $parsed = ValidDate::carbon($date);
        $year = $parsed?->format('Y');
        $month = $parsed instanceof CarbonImmutable ? ReadingText::month($parsed, $locale) : null;
        $with = ['issuer' => $issuer, 'month' => $month, 'year' => $year];
        $put = static fn (string $without, string $withDate): string => __($parsed instanceof CarbonImmutable ? $withDate : $without, $with, $locale);

        return match ($x->kind) {
            'utility_bill' => match ($utilityType) {
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
     * The sentence of the box on top of the form: what the paper is.
     *
     * @param  string|null  $kind  one of `Extraction::KINDS`
     * @param  string|null  $utilityType  the kind of supply of a bill
     * @param  string  $locale  the language of the sentence
     */
    private function headline(?string $kind, ?string $utilityType, string $locale): string
    {
        return match ($kind) {
            'utility_bill' => match ($utilityType) {
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
            $x->total_amount === null ? null : new FoundLineData(__('How much', [], $locale), (Number::currency($x->total_amount, 'EUR', 'it') ?: (string) $x->total_amount)),
            $due instanceof CarbonImmutable ? new FoundLineData(__('Due on', [], $locale), $due->format('d/m/Y')) : null,
        ];

        return array_values(array_filter($lines));
    }
}
