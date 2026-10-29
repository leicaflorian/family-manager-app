<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses and planned expenses refer to a card with one morph pair (`card_type`, `card_id`) instead of the seven
 * columns a planned expense had, one per type; the expense categories get the type of card they stand for.
 */
return new class extends Migration
{
    /**
     * The seven columns of a planned expense and their tables, by card type. When a row has more than one, the
     * first in this order wins.
     *
     * @var array<string, string>
     */
    private const array CARDS = [
        'home' => 'homes',
        'utility' => 'utilities',
        'vehicle' => 'vehicles',
        'insurance_policy' => 'insurance_policies',
        'loan' => 'loans',
        'enrollment' => 'enrollments',
        'subscription' => 'subscriptions',
    ];

    /**
     * Adds the pairs and the type of the categories, moves the cards of the planned expenses into the pair and
     * drops the seven columns.
     */
    public function up(): void
    {
        foreach (['expenses', 'planned_expenses'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('card_type')->nullable()->after('person_id');
                $table->unsignedBigInteger('card_id')->nullable()->after('card_type');
                $table->index(['card_type', 'card_id']);
            });
        }

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->string('card_type')->nullable()->comment('The type of card the expenses of the category refer to');
        });

        foreach (array_keys(self::CARDS) as $type) {
            DB::table('planned_expenses')
                ->whereNull('card_type')
                ->whereNotNull("{$type}_id")
                ->update(['card_type' => $type, 'card_id' => DB::raw("{$type}_id")]);
        }

        Schema::table('planned_expenses', function (Blueprint $table): void {
            foreach (array_keys(self::CARDS) as $type) {
                $table->dropConstrainedForeignId("{$type}_id");
            }
        });
    }

    /**
     * Puts the seven columns back, fills them from the pair and drops the pairs and the type of the categories.
     */
    public function down(): void
    {
        Schema::table('planned_expenses', function (Blueprint $table): void {
            foreach (self::CARDS as $type => $foreignTable) {
                Columns::optional($table, "{$type}_id", $foreignTable);
            }
        });

        // A pair has no foreign key: a card deleted meanwhile would break the one of its column, so it is left out
        foreach (self::CARDS as $type => $foreignTable) {
            DB::table('planned_expenses')
                ->where('card_type', $type)
                ->whereIn('card_id', DB::table($foreignTable)->select('id'))
                ->update(["{$type}_id" => DB::raw('card_id')]);
        }

        foreach (['expenses', 'planned_expenses'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['card_type', 'card_id']);
                $table->dropColumn(['card_type', 'card_id']);
            });
        }

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropColumn('card_type');
        });
    }
};
