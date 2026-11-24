<?php

declare(strict_types=1);

use App\Enums\DocumentCategoryRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A document category can have a fixed role (`role`), kept when the family renames or moves it, one per family.
 * Every family gets the category *Vehicle registration document* with its role: under its *Vehicles* (the main
 * category of the vehicle card), otherwise at the top of the list, named in the language of its first admin. A category
 * already called so (in any case) is not duplicated: it takes the role and stays where it is. Only a nullable column
 * and new rows, so the code of the previous release keeps working.
 */
return new class extends Migration
{
    /**
     * Adds the column with its unique index and gives each family the category.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('document_categories', 'role')) {
            Schema::table('document_categories', function (Blueprint $table): void {
                $table->string('role')->nullable()->after('card_type')->comment('What the app means by the category, once per family');
                $table->unique(['family_id', 'role']);
            });
        }

        foreach (DB::table('families')->orderBy('id')->pluck('id') as $familyId) {
            $this->give((int) $familyId);
        }
    }

    /**
     * Drops the index and the column.
     */
    public function down(): void
    {
        if (Schema::hasColumn('document_categories', 'role')) {
            Schema::table('document_categories', function (Blueprint $table): void {
                // The foreign key of the family may lean on the unique index (MySQL drops its own index when a wider one covers it): give it one back first
                if (! Schema::hasIndex('document_categories', ['family_id'])) {
                    $table->index('family_id');
                }

                $table->dropUnique(['family_id', 'role']);
                $table->dropColumn('role');
            });
        }
    }

    /**
     * Gives a family its registration document category, if it has no category with the role yet.
     *
     * @param  int  $familyId  the family
     */
    private function give(int $familyId): void
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
        $sort = $parent !== null ? (int) $siblings->max('sort') + 1 : (int) ($siblings->min('sort') ?? 1) - 1;

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
};
