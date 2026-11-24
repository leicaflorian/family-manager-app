<?php

declare(strict_types=1);

namespace App\Actions\Families;

use Illuminate\Support\Facades\DB;

/**
 * The data step of the migration that adds `family_settings.checklist_done`: a family already welcomed before the
 * release has finished the old *To begin* for good, so it gets every step written as done and the list does not come
 * back for the steps added later (`people`, `mail`). A family not welcomed yet is left alone and gets the list.
 *
 * It goes through the query builder, without the family scope, because it runs in a migration with nobody signed in.
 */
final readonly class KeepChecklistDone
{
    /** The keys of every step of *To begin*, as `HomeSummary` offers them. */
    public const array STEPS = ['people', 'utilities', 'first_bill', 'card', 'mail', 'invite'];

    /**
     * Writes the steps as done in the settings of every welcomed family, creating the settings when they are missing.
     * Settings that already remember some steps are not changed.
     */
    public function __invoke(): void
    {
        $steps = json_encode(self::STEPS, JSON_THROW_ON_ERROR);
        $now = now();

        $welcomed = array_values(array_filter(DB::table('families')->whereNotNull('welcomed_at')->pluck('id')->all(), is_int(...)));

        foreach (array_chunk($welcomed, 500) as $ids) {
            DB::table('family_settings')->insertOrIgnore(array_map(static fn (int $id): array => [
                'family_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids));

            DB::table('family_settings')->whereIn('family_id', $ids)->whereNull('checklist_done')->update(['checklist_done' => $steps]);
        }
    }
}
