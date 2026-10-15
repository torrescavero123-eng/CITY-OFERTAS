<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $kind
 * @property string $mode
 * @property string|null $error_report_path
 */
class ImportJob extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'import_jobs';

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
