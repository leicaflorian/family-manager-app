<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Models\Family;
use RuntimeException;

/**
 * Gives a family a new address for its bills: the old one is replaced and stops receiving. Mailgun is not called:
 * the route covers the whole domain, so any address works as soon as the family has it in its settings.
 */
final readonly class RegenerateMailAddress
{
    /**
     * @param  AssignMailAddress  $assign  makes the address and writes it, like at sign-up
     */
    public function __construct(private AssignMailAddress $assign) {}

    /**
     * Writes a new address, with the same family name as the old one.
     *
     * @param  Family  $family  the family
     * @return string the new address
     *
     * @throws RuntimeException when no address could be made: no domain, or five taken in a row
     */
    public function handle(Family $family): string
    {
        return $this->assign->handle($family) ?? throw new RuntimeException('No new address could be made.');
    }
}
