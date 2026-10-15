<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property bool $is_active
 * @property bool $is_frozen
 * @property bool $is_staging
 */
class Location extends Model
{
    protected $table = 'locations';

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_frozen' => 'boolean', 'is_staging' => 'boolean'];
    }
}
