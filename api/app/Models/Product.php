<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'external_id',
        'category_id',
        'title',
        'description',
        'price',
        'image_url',
        'rating_rate',
        'rating_count',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'rating_rate' => 'decimal:2',
            'rating_count' => 'integer',
            'imported_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
