<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\Enums\PersonRole;
use App\Enums\Role;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\DefaultCategories;
use App\Support\Initials;
use Illuminate\Support\Facades\DB;

/**
 * Registers a new family: the family, its first account and what it needs to start.
 */
final class CreateFamily
{
    /**
     * Creates in one transaction the family, the `admin` account, its person, the
     * starting categories and the settings row.
     *
     * @param  string  $familyName  the name of the family (usually the last name)
     * @param  string  $firstName  the first name of whoever registers
     * @param  string  $lastName  the last name of whoever registers
     * @param  string  $email  the email of the account
     * @param  string  $password  the plain password (the model hashes it)
     * @return User the `admin` account just created
     */
    public function handle(string $familyName, string $firstName, string $lastName, string $email, string $password): User
    {
        return DB::transaction(function () use ($familyName, $firstName, $lastName, $email, $password): User {
            $family = Family::query()->create(['name' => $familyName]);

            return CurrentFamily::using($family->id, function () use ($family, $firstName, $lastName, $email, $password): User {
                $account = User::query()->create([
                    'family_id' => $family->id,
                    'name' => trim($firstName.' '.$lastName),
                    'email' => $email,
                    'password' => $password,
                    'role' => Role::Admin,
                ]);

                Person::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'initials' => Initials::of($firstName, $lastName),
                    'role' => PersonRole::Parent,
                    'user_id' => $account->id,
                ]);

                $this->createTree(DocumentCategory::class, DefaultCategories::documents(), DefaultCategories::documentLooks());
                $this->createTree(ExpenseCategory::class, DefaultCategories::expenses());
                FamilySetting::query()->create([]);

                return $account;
            });
        });
    }

    /**
     * Creates a tree of categories in the current family, in the given order,
     * with the names in the current language.
     *
     * @param  class-string<DocumentCategory|ExpenseCategory>  $model  the model of the categories
     * @param  array<string, list<string>>  $tree  main category => subcategories
     * @param  array<string, array{icon: string, color: string}>  $looks  icon and colour of the main categories
     */
    private function createTree(string $model, array $tree, array $looks = []): void
    {
        $sort = 0;

        foreach ($tree as $main => $subcategories) {
            $parent = $model::query()->create(['name' => __($main), 'sort' => ++$sort, ...$looks[$main] ?? []]);

            foreach ($subcategories as $position => $name) {
                $model::query()->create(['name' => __($name), 'parent_id' => $parent->id, 'sort' => $position + 1]);
            }
        }
    }
}
