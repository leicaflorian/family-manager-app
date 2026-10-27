<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use App\Services\ArchivioFile;
use Illuminate\Http\UploadedFile;

/**
 * Mette la foto nuova nella persona dell'account e toglie quella di prima.
 */
final readonly class CambiaFoto
{
    public function __construct(private ArchivioFile $archivio) {}

    /**
     * Salva la foto. Un account senza persona non ha dove tenerla.
     *
     * @param  User  $utente  l'account
     * @param  UploadedFile  $foto  l'immagine caricata
     */
    public function handle(User $utente, UploadedFile $foto): void
    {
        $persona = $utente->person;

        if ($persona === null) {
            return;
        }

        $prima = $persona->photo()->first();
        $persona->update(['photo_id' => $this->archivio->salva($foto)->id]);

        if ($prima !== null) {
            $this->archivio->elimina($prima);
        }
    }
}
