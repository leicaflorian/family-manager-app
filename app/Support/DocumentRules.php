<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The rules of the fields of a document and of its links, in one place for the form of one document
 * (`SaveDocumentRequest`) and for many at once (`ImportRequest`, where each sits under a prefix).
 */
final class DocumentRules
{
    /** The columns of the document the form fills. */
    public const array FIELDS = ['title', 'category_id', 'person_id', 'organization_id', 'date', 'expiry_date', 'status', 'tags', 'notes'];

    /**
     * The rules of the fields and the links.
     *
     * @param  mixed  $links  the `links` as sent, to know the type of each id
     * @param  string  $prefix  where the document sits in the request, `files.0.document.` or empty
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(mixed $links, string $prefix = ''): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', new OfFamily('document_categories')],
            'person_id' => ['nullable', 'integer', new OfFamily('people')],
            'organization_id' => ['nullable', 'integer', new OfFamily('organizations')],
            'date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(DocumentStatus::class)],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'links' => ['nullable', 'array', 'max:50'],
            'links.*' => ['required', 'array:type,id'],
            'links.*.type' => ['required', 'string', Rule::in(array_keys(Linkables::WITH_DOCUMENTS))],
            ...self::linkIdRules($links),
        ];

        $prefixed = [];

        foreach ($rules as $key => $rule) {
            $prefixed[$prefix.$key] = $rule;
        }

        return $prefixed;
    }

    /**
     * The rule of the file a new document is made from: one of the family still waiting for its document.
     *
     * @return list<ValidationRule|string|Exists>
     */
    public static function fileId(): array
    {
        return ['required', 'integer', new OfFamily('files'), Rule::exists('files', 'id')->where('to_sort', true)];
    }

    /**
     * The names of the fields in the messages.
     *
     * @param  string  $prefix  where the document sits in the request
     * @return array<string, string>
     */
    public static function attributes(string $prefix = ''): array
    {
        $attributes = [
            'title' => __('title'),
            'category_id' => __('category'),
            'person_id' => __('person'),
            'organization_id' => __('organization'),
            'date' => __('date'),
            'expiry_date' => __('expiry date'),
            'status' => __('status'),
            'tags.*' => __('tag'),
            'links.*.type' => __('link'),
            'links.*.id' => __('link'),
        ];

        $prefixed = [];

        foreach ($attributes as $key => $name) {
            $prefixed[$prefix.$key] = $name;
        }

        return $prefixed;
    }

    /**
     * The links validated, ready for `DocumentLinks::sync()`.
     *
     * @param  mixed  $links  the validated `links`
     * @return list<array{type: string, id: int}>
     */
    public static function links(mixed $links): array
    {
        $clean = [];

        foreach (is_array($links) ? $links : [] as $link) {
            if (is_array($link) && is_string($link['type'] ?? null) && is_numeric($link['id'] ?? null)) {
                $clean[] = ['type' => $link['type'], 'id' => (int) $link['id']];
            }
        }

        return $clean;
    }

    /**
     * The rule of each link's id: a record of the family, in the table of its type. A link with a wrong
     * type gets only `required`: its type is the error.
     *
     * @param  mixed  $links  the `links` as sent
     * @return array<string, list<ValidationRule|string>>
     */
    private static function linkIdRules(mixed $links): array
    {
        $rules = [];

        foreach (is_array($links) ? $links : [] as $index => $link) {
            $type = is_array($link) ? $link['type'] ?? null : null;
            $rules["links.{$index}.id"] = is_string($type) && isset(Linkables::WITH_DOCUMENTS[$type])
                ? ['required', 'integer', new OfFamily((new (Linkables::TYPES[$type]))->getTable())]
                : ['required'];
        }

        return $rules;
    }
}
