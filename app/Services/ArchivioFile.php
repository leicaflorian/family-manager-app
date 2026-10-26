<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * L'archivio dei file di una famiglia, sul disco privato `local`.
 *
 * Ogni famiglia ha la sua cartella, `families/<family_id>/`, e ogni file un nome
 * nuovo: il nome originale resta nella riga di `files`, per lo scaricamento.
 */
final class ArchivioFile
{
    /** Il disco dei file: privato, si legge solo passando dall'app. */
    private const string DISCO = 'local';

    /**
     * Salva un file nella cartella della famiglia corrente e ne crea la riga.
     *
     * @param  UploadedFile  $file  il file caricato
     * @return File la riga del file salvato
     *
     * @throws RuntimeException se manca la famiglia o il disco non scrive
     */
    public function salva(UploadedFile $file): File
    {
        $famiglia = CurrentFamily::id() ?? throw new RuntimeException('Manca la famiglia per salvare il file.');
        $estensione = Str::lower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));

        $percorso = $file->storeAs("families/{$famiglia}", Str::uuid()->toString().'.'.$estensione, self::DISCO)
            ?: throw new RuntimeException('Il file non si è salvato.');

        return File::query()->create([
            'disk' => self::DISCO,
            'path' => $percorso,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
        ]);
    }

    /**
     * Elimina un file: la riga e il file sul disco.
     *
     * @param  File  $file  il file da eliminare
     */
    public function elimina(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->delete();
            Storage::disk($file->disk)->delete($file->path);
        });
    }
}
