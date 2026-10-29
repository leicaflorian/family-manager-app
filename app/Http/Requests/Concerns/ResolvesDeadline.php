<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\Deadlines;
use App\Support\Deadline;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * For the requests of a due date page: resolves the address (`kind`, `id`, `date`) to the due date once,
 * in `authorize()`, so a wrong address answers 404 before the body is validated.
 */
trait ResolvesDeadline
{
    private ?Deadline $resolvedDeadline = null;

    /**
     * The due date of the address of the request.
     *
     * @throws NotFoundHttpException if the address is not a due date of the family
     */
    public function deadline(): Deadline
    {
        $kind = $this->route('kind');
        $id = $this->route('id');
        $date = $this->route('date');

        if (! is_string($kind) || ! is_string($id) || ! is_string($date)) {
            throw new NotFoundHttpException;
        }

        return $this->resolvedDeadline ??= resolve(Deadlines::class)->findByAddress($kind, $id, $date);
    }
}
