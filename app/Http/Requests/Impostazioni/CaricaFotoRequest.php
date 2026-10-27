<?php

declare(strict_types=1);

namespace App\Http\Requests\Impostazioni;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Cambia la foto*: un'immagine fino a 10 MB.
 */
final class CaricaFotoRequest extends FormRequest
{
    /**
     * Le regole dei campi.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240']];
    }
}
