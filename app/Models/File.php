<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Un file caricato (`files`, al posto di `directus_files`). Il file vero sta sul disco privato.
 *
 * @property int $id
 * @property int $family_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 *
 * @method static FileFactory factory($count = null, $state = [])
 * @method static Builder<static>|File newModelQuery()
 * @method static Builder<static>|File newQuery()
 * @method static Builder<static>|File query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size'])]
final class File extends DatoDellaFamiglia
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
