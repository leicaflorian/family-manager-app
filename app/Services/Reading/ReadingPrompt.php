<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\Extraction;
use App\Exceptions\Reading\ReadingFailed;
use App\Support\Reading\Choices;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Throwable;

/**
 * The prompt of a reading: the system text, the schema of the answer, the instructions that go with the file
 * (the closed lists and the notes on the file) and the parsing of the answer, which drops the ids that are not in
 * the lists.
 */
final class ReadingPrompt
{
    /** What the model is for and the rules of every field. */
    public const string SYSTEM = <<<'TXT'
You read Italian family paperwork for a household archive: utility bills, receipts, invoices, payslips, insurance documents, road tax receipts, medical reports and prescriptions, contracts, certificates, identity documents. Extract the data needed to file the document and to record what it is about.

Rules:
- Copy values exactly as printed. Use null when a value is not on the document or does not apply; never guess codes or dates.
- kind: utility_bill (energy, gas, water, phone, internet, waste tax, TV licence), receipt, invoice, payslip ("busta paga", "cedolino"), insurance (policy, premium receipt, "quietanza"), payment_receipt (proof that a payment was made to a creditor: bank transfer receipt "contabile", direct debit notice "addebito SDD", PagoPA receipt, paid postal slip "bollettino", POS or card receipt, "quietanza" of whoever collected the money; it is NOT the bill, the invoice or the shop receipt of what was bought: those stay utility_bill, invoice and receipt, and the premium receipt of a policy stays insurance), road_tax ("bollo auto"), medical (report, exam result, prescription, vaccination, optician), other.
- title: a short title (2 to 5 words) for what the document is about or what was bought or paid, without the date and without the issuer's name, since the issuer is stored apart (e.g. "Tagliando 60.000 km", "Visita oculistica", "Carta d'identità", "Polizza RC auto", "Farmaci", "Cena al ristorante", "Abbonamento Claude Pro", "Ricarica telefonica", "Scarpe da running"). It becomes the title of the expense, so it must say what the money was spent on.
- total_amount: in euro, as a number. The total to pay (bills, invoices), paid (receipts), or the net pay ("netto in busta", "netto a pagare") for payslips. For a payment_receipt it is the sum paid to the creditor WITHOUT the fee ("Importo"), not the headline total that includes the fee.
- fee_amount: only for payment_receipt, the fee charged for the payment ("commissione", "costo del servizio") in euro; null when there is none and for every other kind.
- For a payment_receipt: the issuer is whoever was paid (the creditor, e.g. the municipality or the supplier), never the bank, Poste, PagoPA or the card circuit that carried the payment; issue_date is the day of the payment; due_date is null; iban_last_four is the IBAN of the account that paid; title comes from the reason of the payment ("causale", "oggetto del pagamento"); summary mentions the fee if there is one.
- Dates in YYYY-MM-DD. issue_date: the document date (for a medical report, the date of the visit or exam). due_date: payment deadline ("scadenza"), only for payments. expiry_date: when the document itself stops being valid (identity document, warranty, certificate, policy end), not a payment deadline. The period of a service or subscription ("valido fino al", "periodo dal… al…", "rinnovo il") is not an expiry: for utility_bill, receipt, invoice, payment_receipt, payslip and road_tax expiry_date is null.
- period_start / period_end: the period a bill or payslip refers to (for a payslip, first and last day of the paid month).
- supply_point_code: POD (electricity, starts with IT) or PDR (gas, 14 digits). customer_code: "codice cliente". iban_last_four: last 4 characters of the IBAN the payment is debited to or credited to, if shown.
- policy_number: insurance policy number. plate_number: vehicle plate (Italian format like AB123CD). tax_code: the "codice fiscale" of the person the document is about (employee on a payslip, patient on a medical document, holder of a policy or bill).
- organization_id, expense_category_id, document_category_id, person_id: pick the id from the lists below, or null when nothing fits. Prefer the most specific category. person_id is the account holder, employee, patient or person the document is about.
- income_type, for payslips and payments received: salary, benefit (INPS, bonus), refund, other. maintenance_type, for work on a car or a house: service ("tagliando"), inspection ("revisione"), repair, boiler_service, other. health_type, for medical documents: visit, exam, vaccine, prescription, optical.
- outcome: for medical documents, the diagnosis or result in one or two sentences. next_checkup_date: a follow-up date if the document states one.
- utility_type, for a utility bill or a supply contract: electricity, gas, water, internet, phone, waste_tax ("TARI"), tv_license ("canone RAI"). insurance_type, for insurance documents: car, life, home, health, accident, fund (pension or investment fund), other. organization_type: what the issuer is: bank, insurance, utility_provider, employer, public_office, school, healthcare, shop, other.
- durable_good: true only when the receipt or invoice is for a durable item worth keeping track of (a household appliance, electronics, furniture, a tool); false for food, medicines, clothes, services, consumables and everything else; null when nothing was bought. item_name, item_brand, item_model: the main item bought (one only, the most important), e.g. "Lavatrice", "Bosch", "WGG244". warranty_expiry_date: only when the warranty end is printed.
- vehicle_brand, vehicle_model, vehicle_year: the vehicle the document is about (registration certificate, policy, road tax, workshop invoice), as printed; vehicle_year is the year of first registration.
- contract_start_date: for a contract, the date it starts or the supply is activated ("data di attivazione", "decorrenza").
- The document can be the plain text of an email that announces a bill or an invoice without attaching it (the real one is in the supplier's customer area). Read it as that bill: kind is the kind of the bill; the issuer is the supplier named in the text, not who forwarded the email; issue_date is the date of the invoice written in the text, not the date of the email.
- After the lists there can be what was written about the file when it was uploaded: its name and its description (who uploaded or sent it, when, and a note). Use it as a hint for what the paper does not say: who made the document or who it is about, what it is, the issuer, the title. What is printed on the document wins when they disagree. Whoever uploaded or sent the file is not necessarily the person the document is about. Names made by a device or an app ("Telefono 2026-10-25 12.17", "Scanner…", "IMG_1234") say nothing.
- summary: one short sentence describing the document (e.g. "Bolletta gas bimestrale agosto-settembre 2026").
- confidence: low if the document is hard to read or you are unsure what it is.
TXT;

    /** The fields of the schema that are dates (`YYYY-MM-DD`). */
    private const array DATES = [
        'issue_date', 'due_date', 'expiry_date', 'period_start', 'period_end', 'next_checkup_date', 'warranty_expiry_date', 'contract_start_date',
    ];

    /** The fields of the schema that are whole numbers. */
    private const array INTEGERS = ['organization_id', 'expense_category_id', 'document_category_id', 'person_id', 'vehicle_year'];

    /** The fields the model always answers, never null. */
    private const array ALWAYS_ANSWERED = ['kind', 'confidence'];

    /** The ids the model picks from the lists, with the list each one comes from. */
    private const array PICKED = [
        'organization_id' => 'organizations',
        'expense_category_id' => 'expenseCategories',
        'document_category_id' => 'documentCategories',
        'person_id' => 'people',
    ];

    /**
     * The schema of the answer, for `HasStructuredOutput::schema()`: every field required, and null when the
     * paper does not say.
     *
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        $choices = Extraction::choices();
        $properties = [];

        foreach (Extraction::FIELDS as $field) {
            $type = match (true) {
                // A nullable enum lists null too: `type` alone lets null in, but strict validators check `enum` as well.
                isset($choices[$field]) => $schema->string()->enum(
                    in_array($field, self::ALWAYS_ANSWERED, true) ? $choices[$field] : [...$choices[$field], null],
                ),
                in_array($field, self::DATES, true) => $schema->string()->format('date'),
                in_array($field, self::INTEGERS, true) => $schema->integer(),
                in_array($field, ['total_amount', 'fee_amount'], true) => $schema->number(),
                $field === 'durable_good' => $schema->boolean(),
                default => $schema->string(),
            };

            // `kind` and `confidence` are always answered; every other field is null when the paper does not say.
            $properties[$field] = in_array($field, self::ALWAYS_ANSWERED, true) ? $type->required() : $type->nullable()->required();
        }

        return $properties;
    }

    /**
     * The text that goes with the file: the lists to choose from, what was written about the file, and the request.
     *
     * @param  ReadingContext  $context  the lists and the notes of the file
     * @param  string  $locale  the language of who uploaded the file (`it` or `en`): title and summary are written in it
     */
    public static function instructions(ReadingContext $context, string $locale): string
    {
        $lists = implode("\n\n", [
            self::list('Organizations', $context->organizations),
            self::list('Expense categories', $context->expenseCategories),
            self::list('Document categories', $context->documentCategories),
            self::list('People', $context->people),
        ]);

        $notes = self::fileNotes($context->fileName, $context->fileNote);
        $about = $notes === []
            ? ''
            : "\n\nWhat was written about this file when it was uploaded (file name and description):\n".implode("\n", $notes);
        // Any locale other than `it` is English: the app has only these two languages.
        $language = $locale === 'it' ? 'Italian' : 'English';

        return "{$lists}{$about}\n\nExtract the data from this document. Write `title`, `summary` and `outcome` in {$language}.";
    }

    /**
     * The lines the model gets about the file, without the empty ones.
     *
     * @param  string|null  $name  the name of the file
     * @param  string|null  $note  the note written about the file
     * @return list<string>
     */
    public static function fileNotes(?string $name, ?string $note): array
    {
        return array_values(array_filter([trim($name ?? ''), trim($note ?? '')], static fn (string $line): bool => $line !== ''));
    }

    /**
     * The extraction from the answer of the model. Ids that are not in the lists of the context (invented, or of
     * another family) become null, and so do values outside the closed choices.
     *
     * @param  array<string, mixed>|string|null  $answer  the structured answer, or its JSON text
     * @param  ReadingContext  $context  the lists the model was shown
     *
     * @throws ReadingFailed when the answer is empty or is not the JSON of the schema
     */
    public static function parse(array|string|null $answer, ReadingContext $context): Extraction
    {
        if (in_array($answer, [null, '', []], true)) {
            throw ReadingFailed::emptyAnswer();
        }

        $data = is_array($answer) ? $answer : self::decode($answer);

        try {
            $extraction = Extraction::from($data);
        } catch (Throwable $e) {
            throw ReadingFailed::invalidAnswer($e);
        }

        foreach (self::PICKED as $field => $list) {
            $extraction->{$field} = Choices::pick($context->{$list}, $extraction->{$field});
        }
        foreach (Extraction::choices() as $field => $values) {
            $extraction->{$field} = in_array($extraction->{$field}, $values, true) ? $extraction->{$field} : null;
        }

        return $extraction;
    }

    /**
     * The JSON object of the text.
     *
     * @return array<string, mixed>
     *
     * @throws ReadingFailed when the text is not a JSON object
     */
    private static function decode(string $text): array
    {
        try {
            $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ReadingFailed::invalidAnswer($e);
        }

        if (! is_array($data) || array_is_list($data)) {
            throw ReadingFailed::invalidAnswer();
        }

        return array_filter($data, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * A list of the prompt: the title, then `id: label` lines, or `(none)`.
     *
     * @param  list<array{id: int, label: string}>  $choices
     */
    private static function list(string $title, array $choices): string
    {
        $lines = implode("\n", array_map(static fn (array $choice): string => "{$choice['id']}: {$choice['label']}", $choices));

        return "{$title}:\n".($lines === '' ? '(none)' : $lines);
    }
}
