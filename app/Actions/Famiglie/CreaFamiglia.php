<?php

declare(strict_types=1);

namespace App\Actions\Famiglie;

use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\Person;
use App\Models\User;
use App\Support\CategoriePredefinite;
use App\Support\CurrentFamily;
use App\Support\Iniziali;
use Illuminate\Support\Facades\DB;

/**
 * Iscrive una famiglia nuova: la famiglia, il suo primo account e quello che le serve per partire.
 */
final class CreaFamiglia
{
    /**
     * Crea in una transazione la famiglia, l'account `admin`, la sua persona, le
     * categorie di partenza e la riga delle impostazioni.
     *
     * @param  string  $nomeFamiglia  il nome della famiglia (di solito il cognome)
     * @param  string  $nome  il nome di chi si iscrive
     * @param  string  $cognome  il cognome di chi si iscrive
     * @param  string  $email  l'email dell'account
     * @param  string  $password  la password in chiaro (il modello la cifra)
     * @return User l'account `admin` appena creato
     */
    public function handle(string $nomeFamiglia, string $nome, string $cognome, string $email, string $password): User
    {
        return DB::transaction(function () use ($nomeFamiglia, $nome, $cognome, $email, $password): User {
            $famiglia = Family::query()->create(['name' => $nomeFamiglia]);

            return CurrentFamily::per($famiglia->id, function () use ($famiglia, $nome, $cognome, $email, $password): User {
                $account = User::query()->create([
                    'family_id' => $famiglia->id,
                    'name' => trim($nome.' '.$cognome),
                    'email' => $email,
                    'password' => $password,
                    'role' => Ruolo::Admin,
                ]);

                Person::query()->create([
                    'first_name' => $nome,
                    'last_name' => $cognome,
                    'initials' => Iniziali::di($nome, $cognome),
                    'role' => RuoloPersona::Parent,
                    'user_id' => $account->id,
                ]);

                $this->creaAlbero(DocumentCategory::class, CategoriePredefinite::documenti());
                $this->creaAlbero(ExpenseCategory::class, CategoriePredefinite::spese());
                FamilySetting::query()->create([]);

                return $account;
            });
        });
    }

    /**
     * Crea un albero di categorie nella famiglia corrente, nell'ordine dato.
     *
     * @param  class-string<DocumentCategory|ExpenseCategory>  $modello  il modello delle categorie
     * @param  array<string, list<string>>  $albero  principale => sottocategorie
     */
    private function creaAlbero(string $modello, array $albero): void
    {
        $ordine = 0;

        foreach ($albero as $principale => $sottocategorie) {
            $padre = $modello::query()->create(['name' => $principale, 'sort' => ++$ordine]);

            foreach ($sottocategorie as $posizione => $nome) {
                $modello::query()->create(['name' => $nome, 'parent_id' => $padre->id, 'sort' => $posizione + 1]);
            }
        }
    }
}
