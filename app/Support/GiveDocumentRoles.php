<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentCategoryRole;
use Illuminate\Support\Facades\DB;

/**
 * Gives a family the document categories with a fixed role it does not have yet: today *Vehicle registration
 * document*, under its *Vehicles* (the main category of the vehicle card), otherwise at the top of the list, named in
 * the language of its first admin. A category already called so (in any case) is not duplicated: it takes the role and
 * stays where it is.
 *
 * It works on the tables with the family told by id, without a current family, so that a migration and the copy from
 * Directus can call it; a family that already has the role is left as it is.
 */
final class GiveDocumentRoles
{
    /**
     * Gives the family its registration document category, if it has no category with the role yet.
     *
     * @param  int  $familyId  the family
     */
    public static function family(int $familyId): void
    {
        $role = DocumentCategoryRole::VehicleRegistration->value;

        if (DB::table('document_categories')->where('family_id', $familyId)->where('role', $role)->exists()) {
            return;
        }

        $locale = DB::table('users')->where('family_id', $familyId)->where('role', 'admin')->orderBy('id')->value('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : config()->string('app.locale');
        $name = __('Vehicle registration document', [], $locale);

        $existing = DB::table('document_categories')->where('family_id', $familyId)->orderBy('id')->get(['id', 'name'])
            ->first(static fn (object $category): bool => mb_strtolower((string) $category->name) === mb_strtolower($name));

        if ($existing !== null) {
            DB::table('document_categories')->where('id', $existing->id)->update(['role' => $role]);

            return;
        }

        $parent = DB::table('document_categories')->where('family_id', $familyId)->whereNull('parent_id')->where('card_type', 'vehicle')->orderBy('id')->value('id');
        $siblings = DB::table('document_categories')->where('family_id', $familyId)->where('parent_id', $parent);
        // Under Vehicles it goes last; without Vehicles it goes before every main category
        $sort = $parent !== null ? self::number($siblings->max('sort'), 0) + 1 : self::number($siblings->min('sort'), 1) - 1;

        DB::table('document_categories')->insert([
            'family_id' => $familyId,
            'name' => $name,
            'parent_id' => $parent,
            'sort' => $sort,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A number read from the database, or the fallback when there is none.
     *
     * @param  mixed  $value  the value read
     * @param  int  $fallback  the number without a value
     */
    private static function number(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? (int) $value : $fallback;
    }
}
