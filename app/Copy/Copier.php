<?php

declare(strict_types=1);

namespace App\Copy;

use App\Enums\Role;
use App\Services\FileStore;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies a family from the database of Directus into the app, table by table
 * in the order of `DirectusMap`, all in one transaction.
 *
 * Directus is read through the `directus` connection, which is read only. A
 * field pointing to a table copied later (a category's parent) is written
 * once that table is there. Only the files that documents, people and
 * organizations use are copied; a file missing in the uploads keeps its row,
 * without content, and is listed. The accounts are those of the people:
 * administrators with a random password, who enter with *Forgot password*.
 */
final class Copier
{
    /** The connection of Directus, read only. */
    private const string SOURCE = 'directus';

    /** @var array<string, array<int|string, int>> the new id of each copied row, by Directus table and old id */
    private array $ids = [];

    /** @var array<string, true> the Directus tables already copied */
    private array $copied = [];

    /** @var list<array{string, int, string, string, int|string}> the fields to write at the end: app table, new id, column, Directus table it points to, old id */
    private array $deferred = [];

    /** @var array<string, list<string>> the columns of each table of the app */
    private array $columns = [];

    /** @var array<string, list<string>> the links each table of the app cannot be without */
    private array $required = [];

    /** @var array<string, int> */
    private array $rows = [];

    /** @var array<string, list<string>> */
    private array $ignored = [];

    /** @var list<string> */
    private array $missing = [];

    /** @var list<string> */
    private array $withoutAccount = [];

    /** @var array<string, int> */
    private array $leftOut = [];

    public function __construct(private readonly FileStore $files) {}

    /**
     * Copies the family of Directus into the app, with the given name.
     *
     * @param  string  $family  the name of the family in the app
     * @param  bool  $replace  true deletes the family of that name, with its files, and makes it again
     * @param  bool  $withFiles  false writes the rows of the files without copying their content
     *
     * @throws CopyRefused if the family is already there without `$replace`, or an email has an account in another family
     */
    public function copy(string $family, bool $replace, bool $withFiles): CopyReport
    {
        $started = hrtime(true);
        $this->ids = $this->copied = $this->deferred = $this->rows = $this->ignored = $this->leftOut = [];
        $this->missing = $this->withoutAccount = [];
        $source = DB::connection(self::SOURCE);
        $existing = DB::table('families')->where('name', $family)->value('id');
        $existing = is_int($existing) ? $existing : null;

        if ($existing !== null && ! $replace) {
            throw new CopyRefused("The family {$family} is already in the app: add --replace to make it again.");
        }

        $accounts = $this->accounts($source);
        $taken = DB::table('users')->whereIn('email', array_column($accounts, 'email'))
            ->when($existing !== null, fn ($query) => $query->where('family_id', '!=', $existing))
            ->pluck('email')->map(fn (mixed $email): string => $this->text($email))->all();

        if ($taken !== []) {
            throw new CopyRefused('These emails already have an account in the app: '.implode(', ', $taken).'.');
        }

        DB::transaction(function () use ($source, $family, $existing, $accounts, $withFiles): void {
            if ($existing !== null) {
                DB::table('families')->where('id', $existing)->delete();
            }

            $familyId = DB::table('families')->insertGetId(['name' => $family, 'welcomed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            try {
                foreach (DirectusMap::order() as $table) {
                    $this->copyTable($source, $table, $familyId, $accounts, $withFiles);
                    $this->copied[$table] = true;
                }

                $this->writeDeferred();
            } catch (Throwable $exception) {
                $this->files->deleteFamily($familyId);

                throw $exception;
            }
        });

        if ($existing !== null) {
            $this->files->deleteFamily($existing);
        }

        return new CopyReport($this->rows, $this->ignored, $this->missing, $this->leftOut, $this->withoutAccount, (hrtime(true) - $started) / 1e9);
    }

    /**
     * Copies the rows of a Directus table, if Directus has it.
     *
     * @param  array<string, array{email: string, name: string}>  $accounts  the accounts to create, by Directus user
     */
    private function copyTable(Connection $source, string $table, int $familyId, array $accounts, bool $withFiles): void
    {
        if (! Schema::connection(self::SOURCE)->hasTable($table)) {
            return;
        }

        $target = DirectusMap::target($table);
        $columns = $this->columnsOf($target);
        $this->rows[$table] = 0;

        foreach ($this->sourceRows($source, $table, array_keys($accounts)) as $row) {
            $copy = DirectusMap::row($table, $row, $this->newId(...));

            if (array_any($this->requiredOf($target), fn (string $column): bool => ($copy[$column] ?? null) === null)) {
                $this->leftOut[$table] = ($this->leftOut[$table] ?? 0) + 1;

                continue;
            }

            if (! in_array('id', $columns, true) || ! array_key_exists('id', $row)) {
                DB::table($target)->insert($copy);
                $this->rows[$table]++;

                continue;
            }

            $copy = match ($target) {
                'users' => $this->account($copy, $accounts[$this->key($row['id'] ?? null)] ?? null),
                'files' => $this->file($copy, $row, $familyId, $withFiles),
                default => $copy,
            };

            $this->ignore($table, array_diff(array_keys($copy), $columns));
            $copy = array_intersect_key($copy, array_flip($columns));

            if (in_array('family_id', $columns, true)) {
                $copy['family_id'] = $familyId;
            }

            $newId = DB::table($target)->insertGetId($copy);
            $this->ids[$table][$this->key($row['id'] ?? null)] = $newId;
            $this->defer($table, $target, $newId, $row);
            $this->rows[$table]++;
        }
    }

    /**
     * The rows of a Directus table to copy: of the accounts and the files only
     * those the family uses.
     *
     * @param  list<string>  $users  the Directus users of the people
     * @return list<array<string, mixed>>
     */
    private function sourceRows(Connection $source, string $table, array $users): array
    {
        $query = $source->table($table)->orderBy('id');

        if ($table === 'directus_users') {
            $query->whereIn('id', $users);
        } elseif ($table === 'directus_files') {
            $query->whereIn('id', $this->usedFiles($source));
        }

        return array_values(array_map(get_object_vars(...), $query->get()->all()));
    }

    /**
     * The Directus files the documents, the people and the organizations use.
     *
     * @return list<mixed>
     */
    private function usedFiles(Connection $source): array
    {
        $used = [];

        foreach (['documents' => 'file', 'people' => 'photo', 'organizations' => 'logo'] as $table => $field) {
            if (Schema::connection(self::SOURCE)->hasTable($table)) {
                $used = [...$used, ...$source->table($table)->whereNotNull($field)->pluck($field)->all()];
            }
        }

        return array_values(array_unique($used, SORT_REGULAR));
    }

    /**
     * The accounts to create: one for each Directus user a person has, with the
     * email of Directus and the name of the person. A person whose Directus
     * user has no email is remembered, and copied without an account.
     *
     * @return array<string, array{email: string, name: string}> by Directus user
     */
    private function accounts(Connection $source): array
    {
        $accounts = [];
        $people = $source->table('people')->whereNotNull('user')->get(['user', 'first_name', 'last_name']);
        $emails = $source->table('directus_users')->whereIn('id', $people->pluck('user'))->pluck('email', 'id');

        foreach ($people as $person) {
            $user = $this->key($person->user ?? null);
            $email = $emails->get($user);
            $name = trim($this->text($person->first_name ?? null).' '.$this->text($person->last_name ?? null));

            if (is_string($email) && $email !== '') {
                $accounts[(string) $user] = ['email' => $email, 'name' => $name];
            } else {
                $this->withoutAccount[] = $name;
            }
        }

        return $accounts;
    }

    /**
     * The row of an account of the app.
     *
     * @param  array<string, mixed>  $copy  the row of `DirectusMap`: the email
     * @param  array{email: string, name: string}|null  $account  the account, with the name of its person
     * @return array<string, mixed>
     */
    private function account(array $copy, ?array $account): array
    {
        return [
            ...$copy,
            'name' => $account['name'] ?? $this->text($copy['email'] ?? null),
            'role' => Role::Admin->value,
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * The row of a file of the app, with its content copied from the uploads.
     *
     * @param  array<string, mixed>  $copy  the row of `DirectusMap`: name, type, size, date
     * @param  array<string, mixed>  $row  the row of `directus_files`
     * @return array<string, mixed>
     */
    private function file(array $copy, array $row, int $familyId, bool $withFiles): array
    {
        $name = $this->text($copy['original_name'] ?? null) ?: $this->text($row['filename_disk'] ?? null);
        $source = config()->string('directus.uploads').'/'.$this->text($row['filename_disk'] ?? null);
        $present = is_string($row['filename_disk'] ?? null) && is_file($source);

        if ($withFiles && ! $present) {
            $storage = $this->text($row['storage'] ?? null);
            $this->missing[] = "{$name} ({$this->key($row['id'] ?? null)}".(in_array($storage, ['', 'local'], true) ? '' : ", on {$storage}").')';
        }

        return [
            ...$copy,
            'disk' => FileStore::DISK,
            'path' => $this->files->import($familyId, $withFiles && $present ? $source : null, $name),
            'original_name' => $name,
            'mime_type' => $this->text($copy['mime_type'] ?? null) ?: 'application/octet-stream',
            'size' => is_numeric($copy['size'] ?? null) ? (int) $copy['size'] : 0,
            'to_sort' => false,
            'updated_at' => $copy['created_at'] ?? null,
        ];
    }

    /**
     * Remembers the fields of a row that point to a table not copied yet, to
     * write them at the end.
     *
     * @param  array<string, mixed>  $row  the row as Directus has it
     */
    private function defer(string $table, string $target, int $newId, array $row): void
    {
        foreach (DirectusMap::relations($table) as $field => $pointed) {
            $old = $row[$field] ?? null;

            if (! isset($this->copied[$pointed]) && (is_int($old) || is_string($old))) {
                $this->deferred[] = [$target, $newId, "{$field}_id", $pointed, $old];
            }
        }
    }

    /**
     * Writes the fields that pointed to a table not copied yet.
     */
    private function writeDeferred(): void
    {
        foreach ($this->deferred as [$target, $newId, $column, $pointed, $old]) {
            $id = $this->newId($pointed, $old);

            if ($id !== null) {
                DB::table($target)->where('id', $newId)->update([$column => $id]);
            }
        }
    }

    /**
     * The new id of a row of a Directus table, or null if it was not copied.
     *
     * @param  string  $table  the Directus table
     * @param  int|string  $old  the id in Directus
     */
    private function newId(string $table, int|string $old): ?int
    {
        return $this->ids[$table][$old] ?? null;
    }

    /**
     * Remembers the Directus fields the app has no column for.
     *
     * @param  array<int, string>  $fields  the fields left out
     */
    private function ignore(string $table, array $fields): void
    {
        if ($fields !== []) {
            $this->ignored[$table] = array_values(array_unique([...$this->ignored[$table] ?? [], ...$fields]));
        }
    }

    /**
     * The columns of a table of the app.
     *
     * @return list<string>
     */
    private function columnsOf(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    /**
     * The links a row of a table of the app cannot be without (a document
     * without its file, an exception without its planned expense): a row of
     * Directus that leaves one empty is left out.
     *
     * @return list<string> the required `_id` and `_type` columns, but `family_id`
     */
    private function requiredOf(string $table): array
    {
        return $this->required[$table] ??= array_values(array_map(
            fn (array $column): string => $column['name'],
            array_filter(Schema::getColumns($table), fn (array $column): bool => ! $column['nullable']
                && $column['name'] !== 'family_id'
                && (str_ends_with($column['name'], '_id') || str_ends_with($column['name'], '_type'))),
        ));
    }

    /**
     * An id as a key of the id map: a number or a uuid.
     */
    private function key(mixed $id): int|string
    {
        return is_int($id) || is_string($id) ? $id : '';
    }

    /**
     * A value as text, empty if it is not one.
     */
    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
