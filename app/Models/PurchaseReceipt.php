<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de LECTURA: las altas y los cambios pasan por los casos de uso de Operations.
 *
 * @property int $id
 * @property string $code
 * @property string $status
 * @property string $doc_type
 * @property string $doc_number
 */
class PurchaseReceipt extends Model
{
    protected $table = 'purchase_receipts';

    /** @var list<string> */
    protected $guarded = ['*'];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
