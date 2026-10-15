<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $kind
 * @property string $status
 * @property int $requested_by
 */
class StockAdjustmentRequest extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'stock_adjustment_requests';

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
