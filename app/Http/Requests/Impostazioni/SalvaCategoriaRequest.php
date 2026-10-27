<?php

declare(strict_types=1);

namespace App\Http\Requests\Impostazioni;

use App\Models\DocumentCategory;
use App\Rules\OfFamily;
use App\Services\AlberoCategorie;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Una categoria dei documenti, nuova o modificata: il nome e dove sta.
 */
final class SalvaCategoriaRequest extends FormRequest
{
    /**
     * Solo un `admin` cambia le categorie.
     */
    public function authorize(): bool
    {
        $categoria = $this->route('categoria');

        return $categoria instanceof DocumentCategory
            ? $this->user()?->can('update', $categoria) ?? false
            : $this->user()?->can('create', DocumentCategory::class) ?? false;
    }

    /**
     * Le regole dei campi. Una categoria non va sotto sé stessa né sotto una sua figlia.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', new OfFamily('document_categories'), $this->nonSottoSeStessa(...)],
        ];
    }

    /**
     * I nomi dei campi nei messaggi.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nome', 'parent_id' => 'dentro'];
    }

    /**
     * Rifiuta come principale la categoria stessa o una che sta sotto di lei.
     *
     * @param  Closure(string): mixed  $fail
     */
    private function nonSottoSeStessa(string $attributo, mixed $valore, Closure $fail): void
    {
        $categoria = $this->route('categoria');

        if ($categoria instanceof DocumentCategory && in_array((int) $valore, resolve(AlberoCategorie::class)->conDiscendenti($categoria->id), true)) {
            $fail('Una categoria non può stare dentro sé stessa o dentro una sua sottocategoria.');
        }
    }
}
