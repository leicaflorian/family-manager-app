<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Model;

/**
 * The base of every model holding a family's data: only the current family
 * is read, and who creates and who changes a row is recorded.
 *
 * @property int $family_id
 */
abstract class FamilyModel extends Model
{
    use BelongsToFamily;
    use TracksAuthors;
}
