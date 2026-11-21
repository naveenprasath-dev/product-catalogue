<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A currency the shop can show prices in; `rate` = units per 1 unit of the base currency (USD). */
final class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'symbol', 'rate', 'enabled', 'is_default', 'rate_updated_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'rate_updated_at' => 'immutable_datetime',
        ];
    }
}
