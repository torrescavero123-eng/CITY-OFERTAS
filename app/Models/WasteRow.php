<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Modelo de LECTURA sobre la vista v_report_waste. */
class WasteRow extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'v_report_waste';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    /** @var list<string> */
    protected $guarded = ['*'];
}
