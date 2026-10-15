<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Modelo de LECTURA sobre la vista v_report_abc. */
class AbcRow extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'v_report_abc';

    protected $primaryKey = 'product_id';

    protected $keyType = 'int';

    /** @var list<string> */
    protected $guarded = ['*'];
}
