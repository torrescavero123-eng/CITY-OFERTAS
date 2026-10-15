<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de LECTURA sobre la vista v_replenishment (estado de stock vs. política por almacén).
 *
 * @property int $row_key
 * @property int $product_id
 * @property string $sku
 * @property string $product_name
 * @property string $status OUT | LOW | NEAR | OVER | OK
 */
class ReplenishmentSuggestion extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'v_replenishment';

    protected $primaryKey = 'row_key';

    protected $keyType = 'int';

    /** @var list<string> */
    protected $guarded = ['*'];
}
