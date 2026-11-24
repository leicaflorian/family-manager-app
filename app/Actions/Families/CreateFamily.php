<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\Actions\Mail\AssignMailAddress;
use App\Actions\People\SavePerson;
use App\Enums\PersonRole;
use App\Enums\Role;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\DefaultCategories;
use App\Support\DefaultReminderGroups;
use Illuminate\Support\Facades\DB;

/**
 * Registers a new family: the family, its first account and what it needs to start.
 */
final readonly class CreateFamily
{
    /**
     * @param  AssignMailAddress  $assignMailAddress  Gives the family its address for the bills.
     * @param  SavePerson  $savePerson  Saves the first person of the family.
     */
    public function __construct(private AssignMailAddress $assignMailAddress, private SavePerson $savePerson) {}

    /**
     * Creates in one transaction the family, the `admin` account, its person, the
     * starting categories, the settings row and the alert groups.
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

                $this->savePerson->handle([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'role' => PersonRole::Parent,
                    'user_id' => $account->id,
                ]);

                $this->createTree(DocumentCategory::class, DefaultCategories::documents(), DefaultCategories::documentLooks(), DefaultCategories::documentCardTypes(), DefaultCategories::documentGalleries());
                $this->createTree(ExpenseCategory::class, DefaultCategories::expenses());
                FamilySetting::query()->create([]);
                $this->assignMailAddress->handle($family);

                foreach (DefaultReminderGroups::all() as $group) {
                    ReminderGroup::query()->create($group);
                }

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
     * @param  array<string, string>  $cardTypes  the card type of the categories, by English name
     * @param  list<string>  $galleries  the English names of the categories shown as the gallery
     */
    private function createTree(string $model, array $tree, array $looks = [], array $cardTypes = [], array $galleries = []): void
    {
        $sort = 0;

        foreach ($tree as $main => $subcategories) {
            $parent = $model::query()->create(['name' => __($main), 'sort' => ++$sort, ...$looks[$main] ?? [], ...isset($cardTypes[$main]) ? ['card_type' => $cardTypes[$main]] : []]);

            foreach ($subcategories as $position => $name) {
                $model::query()->create(['name' => __($name), 'parent_id' => $parent->id, 'sort' => $position + 1, ...isset($cardTypes[$name]) ? ['card_type' => $cardTypes[$name]] : [], ...in_array($name, $galleries, true) ? ['gallery' => true] : []]);
            }
        }
    }
}
