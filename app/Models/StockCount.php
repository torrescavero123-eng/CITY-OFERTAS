<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $status
 * @property string $scope_type
 * @property string|null $scope_value
 */
class StockCount extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'stock_counts';

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
