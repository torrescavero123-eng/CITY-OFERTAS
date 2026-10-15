<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
class Brand extends Model
{
    use RecordsAudit;

    protected $table = 'brands';

    /** @var list<string> */
    protected $fillable = ['name'];

    protected static function auditEntity(): string
    {
        return 'brand';
    }
}
