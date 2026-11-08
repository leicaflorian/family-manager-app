<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Area;
use App\Models\FamilySetting;

/**
 * The areas of the current family: the order it chose and the ones it hid
 * (`family_settings.areas`, `{"order": [...], "hidden": [...]}` or null).
 */
final class FamilyAreas
{
    /**
     * Every area, in the order of the family.
     *
     * @return list<Area>
     */
    public function ordered(): array
    {
        $order = $this->parse($this->stored()['order'] ?? []);

        foreach (Area::cases() as $area) {
            if (in_array($area, $order, true)) {
                continue;
            }

            // A missing area goes right after the one of the areas that precede it by default which sits last in the list.
            $position = 0;
            foreach (Area::cases() as $earlier) {
                if ($earlier === $area) {
                    break;
                }
                $found = array_search($earlier, $order, true);
                if ($found !== false) {
                    $position = max($position, $found + 1);
                }
            }
            array_splice($order, $position, 0, [$area]);
        }

        return $order;
    }

    /**
     * The areas that are not hidden, in the order of the family.
     *
     * @return list<Area>
     */
    public function visible(): array
    {
        $hidden = $this->hiddenSet();

        return array_values(array_filter($this->ordered(), static fn (Area $area): bool => ! in_array($area, $hidden, true)));
    }

    /**
     * The hidden areas, in the order of the family.
     *
     * @return list<Area>
     */
    public function hidden(): array
    {
        $hidden = $this->hiddenSet();

        return array_values(array_filter($this->ordered(), static fn (Area $area): bool => in_array($area, $hidden, true)));
    }

    /**
     * Tells if the family hid an area.
     *
     * @param  Area  $area  the area
     */
    public function isHidden(Area $area): bool
    {
        return in_array($area, $this->hiddenSet(), true);
    }

    /**
     * Saves the choice of the family.
     *
     * @param  list<Area>  $order  the areas in the chosen order
     * @param  list<Area>  $hidden  the hidden areas
     */
    public function save(array $order, array $hidden): void
    {
        $this->setting()->update(['areas' => [
            'order' => array_map(static fn (Area $area): string => $area->value, $order),
            'hidden' => array_map(static fn (Area $area): string => $area->value, $hidden),
        ]]);
    }

    /**
     * Forgets the choice: default order, nothing hidden.
     */
    public function reset(): void
    {
        $this->setting()->update(['areas' => null]);
    }

    /**
     * Shows an area again.
     *
     * @param  Area  $area  the area
     * @return bool true only when the area was hidden
     */
    public function reveal(Area $area): bool
    {
        if (! $this->isHidden($area)) {
            return false;
        }

        $this->save($this->ordered(), array_values(array_filter(
            $this->hiddenSet(),
            static fn (Area $hidden): bool => $hidden !== $area,
        )));

        return true;
    }

    /**
     * The hidden areas as saved, without the values that are not areas.
     *
     * @return list<Area>
     */
    private function hiddenSet(): array
    {
        return $this->parse($this->stored()['hidden'] ?? []);
    }

    /**
     * Turns saved values into areas, dropping the ones that are not areas and the repeated ones.
     *
     * @param  mixed  $values  the saved values
     * @return list<Area>
     */
    private function parse(mixed $values): array
    {
        $areas = [];

        foreach (is_array($values) ? $values : [] as $value) {
            $area = is_string($value) ? Area::tryFrom($value) : null;

            if ($area !== null && ! in_array($area, $areas, true)) {
                $areas[] = $area;
            }
        }

        return $areas;
    }

    /**
     * The saved choice of the family.
     *
     * @return array<array-key, mixed>
     */
    private function stored(): array
    {
        return $this->setting()->areas ?? [];
    }

    /**
     * The settings row of the current family, created when missing.
     */
    private function setting(): FamilySetting
    {
        return FamilySetting::query()->firstOrCreate([]);
    }
}
