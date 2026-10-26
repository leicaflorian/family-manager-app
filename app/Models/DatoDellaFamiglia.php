<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use App\Models\Concerns\RegistraAutori;
use Illuminate\Database\Eloquent\Model;

/**
 * La base di ogni modello con i dati di una famiglia: si legge solo la
 * famiglia corrente e si registra chi crea e chi modifica.
 */
abstract class DatoDellaFamiglia extends Model
{
    use BelongsToFamily;
    use RegistraAutori;
}
