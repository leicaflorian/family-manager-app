<?php

declare(strict_types=1);

namespace App\Http\Requests\Reading;

use App\Models\Document;
use App\Support\DocumentRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Many files of *To sort* made documents at once: for each, the file (of the family and still waiting) and
 * the fields of its document, with the same rules as the form of one document.
 */
final class ImportRequest extends FormRequest
{
    /** Where the document of each file sits in the body. */
    private const string DOCUMENT = 'files.%d.document.';

    /**
     * Whoever adds documents imports them.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * The rules of the files and, under each, those of its document.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // A list, so every entry has a numeric place and the rules of its document are built for it
            'files' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'files.*' => ['required', 'array:file_id,document'],
            'files.*.file_id' => [...DocumentRules::fileId(), 'distinct'],
            'files.*.document' => ['required', 'array'],
        ];

        foreach (array_keys($this->array('files')) as $index) {
            $rules += DocumentRules::rules($this->input(sprintf('files.%s.document.links', $index)), sprintf(self::DOCUMENT, $index));
        }

        return $rules;
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'files.*.file_id' => __('file'),
            ...DocumentRules::attributes('files.*.document.'),
        ];
    }

    /**
     * What to save, in the order of the request.
     *
     * @return list<array{file_id: int, data: array{fields: array<string, mixed>, links: list<array{type: string, id: int}>}}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->safe()->array('files') as $entry) {
            if (! is_array($entry) || ! is_array($entry['document'] ?? null) || ! is_numeric($entry['file_id'] ?? null)) {
                continue;
            }

            $entries[] = [
                'file_id' => (int) $entry['file_id'],
                'data' => [
                    'fields' => array_intersect_key($entry['document'], array_flip(DocumentRules::FIELDS)),
                    'links' => DocumentRules::links($entry['document']['links'] ?? []),
                ],
            ];
        }

        return $entries;
    }
}
