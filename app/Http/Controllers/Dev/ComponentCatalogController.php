<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The local-only catalogue of the `fm/` components.
 */
final class ComponentCatalogController extends Controller
{
    /**
     * Shows the catalogue, with the component named in `?component=` selected if it exists.
     */
    public function index(Request $request): Response
    {
        $selected = $request->query('component');

        return Inertia::render('dev/Components', [
            'selected' => is_string($selected) && $this->exists($selected) ? $selected : null,
        ]);
    }

    /**
     * Shows one component alone, for the frame of the catalogue.
     *
     * @throws NotFoundHttpException When the component file does not exist.
     */
    public function show(string $component): Response
    {
        abort_unless($this->exists($component), 404);

        return Inertia::render('dev/ComponentFrame', ['component' => $component]);
    }

    /**
     * Tells whether a component file exists; the pattern keeps paths out of the name.
     */
    private function exists(string $component): bool
    {
        return preg_match('/^Fm[A-Za-z]+$/', $component) === 1
            && is_file(resource_path("js/components/fm/{$component}.vue"));
    }
}
