<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\File;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The files chosen in *To sort* to be deleted: at least one, all still waiting in the family. They are looked up
 * in `authorize()`, so a wrong id answers 404 before the permission (403) and the body (422).
 */
final class DeleteToSortRequest extends FormRequest
{
    /** @var Collection<int, File>|null */
    private ?Collection $resolvedFiles = null;

    /**
     * Resolves the files (404 if any id is not a file waiting in the family), then asks if the person may delete many.
     *
     * @throws NotFoundHttpException if an id is not a file of the family waiting for its document
     */
    public function authorize(): bool
    {
        $this->files();

        return Gate::allows('deleteMany', File::class);
    }

    /**
     * The ids of the files.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'list', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * The files chosen, copies included.
     *
     * @return Collection<int, File>
     *
     * @throws NotFoundHttpException if an id is not a file of the family waiting for its document
     */
    public function files(): Collection
    {
        if ($this->resolvedFiles instanceof Collection) {
            return $this->resolvedFiles;
        }

        $ids = $this->ids();
        $files = File::query()->toSort()->whereKey($ids)->get();

        abort_if($files->count() !== count($ids), 404);

        return $this->resolvedFiles = $files;
    }

    /**
     * The integer ids of the input list, without repeats. Anything else is left to the rules (422).
     *
     * @return list<int>
     */
    private function ids(): array
    {
        $input = $this->input('ids');

        if (! is_array($input) || ! array_is_list($input)) {
            return [];
        }

        $ids = [];
        foreach ($input as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
