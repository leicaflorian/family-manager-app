<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finché la famiglia non ha finito il *Benvenuto*, le pagine dell'app portano lì.
 */
final class PortaAlBenvenuto
{
    /**
     * Manda al *Benvenuto* chi entra in una famiglia non ancora accolta.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $utente = $request->user();

        if ($utente instanceof User && ! $utente->family->accolta()) {
            return to_route('benvenuto');
        }

        return $next($request);
    }
}
