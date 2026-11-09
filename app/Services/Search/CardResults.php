<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\Search\SearchResultData;
use App\Enums\SearchGroup;
use App\Services\CardSummaries;
use App\Support\ModelId;
use App\Support\SearchTerms;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The search over the cards of the family: every card type is looked up by its name or title, by the
 * relations its label reads (organization, home, person) and, when it has a `type` choice, by the label of
 * the type («gas» finds the gas utilities).
 */
final readonly class CardResults implements GroupResults
{
    /** The column of each related row the words are looked up in. */
    private const array RELATION_COLUMNS = [
        'organization' => 'organization.name',
        'home' => 'home.name',
        'person' => 'person.first_name',
    ];

    public function __construct(private CardSummaries $summaries) {}

    /**
     * {@inheritDoc}
     */
    public function group(): SearchGroup
    {
        return SearchGroup::Cards;
    }

    /**
     * {@inheritDoc}
     */
    public function count(SearchTerms $terms): int
    {
        return array_sum(array_map(
            fn (CardType $type): int => $this->query($type, $terms)->count(),
            CardTypes::all(),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * Every matching card is read and sorted here, because a family has few cards of each type.
     */
    public function results(SearchTerms $terms, int $limit): array
    {
        $rows = [];

        foreach (CardTypes::all() as $type) {
            $cards = $this->query($type, $terms)->with($type->listRelations())->get();

            foreach ($cards as $card) {
                $rows[] = ['closed' => $type->isClosed($card), 'title' => $this->summaries->label($card), 'type' => $type, 'card' => $card];
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['closed'], mb_strtolower($a['title'])] <=> [$b['closed'], mb_strtolower($b['title'])]);

        return array_map(
            fn (array $row): SearchResultData => $this->result($row['type'], $row['card'], $row['title'], $row['closed']),
            array_slice($rows, 0, max(0, $limit)),
        );
    }

    /**
     * The result row of one card.
     *
     * @param  CardType  $type  the type of the card
     * @param  Model  $card  the card
     * @param  string  $title  the label of the card
     * @param  bool  $closed  whether the card is closed
     */
    private function result(CardType $type, Model $card, string $title, bool $closed): SearchResultData
    {
        $id = ModelId::of($card);

        return new SearchResultData(
            key: 'card-'.$type->type().'-'.$id,
            group: SearchGroup::Cards,
            title: $title,
            subtitle: $type->area()->label(),
            date: null,
            amount: null,
            href: route('cards.show', [$type->type(), $id]),
            area: $type->area()->value,
            icon: null,
            tone: null,
            flag: $closed ? (string) __('Closed') : null,
            due_in: null,
        );
    }

    /**
     * The cards of one type where every word is in a column, in a related name or in the label of the type.
     *
     * @param  CardType  $type  the card type to look in
     * @param  SearchTerms  $terms  what the person typed
     * @return Builder<Model>
     */
    private function query(CardType $type, SearchTerms $terms): Builder
    {
        $model = $type->model();
        $query = $model::query();
        $columns = $this->columns($type);
        $typeEnum = $this->typeEnum($type);

        foreach ($terms->words as $word) {
            $matchingTypes = $typeEnum === null ? [] : $this->typesLabelled($typeEnum, $word);

            $query->where(function (Builder $group) use ($word, $columns, $matchingTypes): void {
                if ($columns !== []) {
                    $group->orWhere(fn (Builder $inColumns): Builder => SearchTerms::fromQuery($word)->whereWords($inColumns, $columns));
                }

                if ($matchingTypes !== []) {
                    $group->orWhereIn('type', $matchingTypes);
                }

                if ($columns === [] && $matchingTypes === []) {
                    $group->whereRaw('1 = 0');
                }
            });
        }

        return $query;
    }

    /**
     * The columns the words are looked up in: the name or title of the card and the related names its label reads.
     *
     * @return list<string>
     */
    private function columns(CardType $type): array
    {
        $model = $type->model();
        $fields = array_map(static fn (CardField $field): string => $field->column, $type->fields());
        $columns = array_values(array_intersect(['name', 'title'], $fields));
        $relations = method_exists($model, 'labelRelations') ? $model::labelRelations() : [];

        foreach ($relations as $relation) {
            if (isset(self::RELATION_COLUMNS[$relation])) {
                $columns[] = self::RELATION_COLUMNS[$relation];
            }
        }

        return $columns;
    }

    /**
     * The enum of the `type` field of the card type, or null when it has none with labels.
     *
     * @return class-string<BackedEnum>|null
     */
    private function typeEnum(CardType $type): ?string
    {
        foreach ($type->fields() as $field) {
            if ($field->column === 'type' && $field->enum !== null && method_exists($field->enum, 'label')) {
                return $field->enum;
            }
        }

        return null;
    }

    /**
     * The values of the enum whose translated label contains the word, ignoring case and accents.
     *
     * @param  class-string<BackedEnum>  $enum  an enum with `HasLabel`
     * @param  string  $word  the word typed
     * @return list<string|int>
     */
    private function typesLabelled(string $enum, string $word): array
    {
        $needle = Str::of($word)->ascii()->lower()->toString();
        $values = [];

        foreach ($enum::cases() as $case) {
            // `label()` comes from the `HasLabel` trait, checked in `typeEnum()`
            if (method_exists($case, 'label') && str_contains(Str::of((string) $case->label())->ascii()->lower()->toString(), $needle)) {
                $values[] = $case->value;
            }
        }

        return $values;
    }
}
