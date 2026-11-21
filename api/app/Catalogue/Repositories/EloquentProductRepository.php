<?php

declare(strict_types=1);

namespace App\Catalogue\Repositories;

use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Data\Category;
use App\Catalogue\Data\Product;
use App\Models\Category as CategoryModel;
use App\Models\Product as ProductModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentProductRepository implements ProductRepository
{
    public function find(int $id): ?Product
    {
        $model = $this->query()->find($id);

        return $model instanceof ProductModel ? $this->toDto($model) : null;
    }

    public function featured(array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }

        $byExternalId = $this->query()
            ->whereIn('external_id', $externalIds)
            ->get()
            ->keyBy('external_id');

        $products = [];
        foreach ($externalIds as $externalId) {
            $model = $byExternalId->get($externalId);
            if ($model instanceof ProductModel) {
                $products[] = $this->toDto($model);
            }
        }

        return $products;
    }

    public function countProducts(): int
    {
        return ProductModel::query()->count();
    }

    public function countCategories(): int
    {
        return CategoryModel::query()->count();
    }

    public function lastImportedAt(): ?CarbonImmutable
    {
        $latest = ProductModel::query()->max('imported_at');

        return $latest === null ? null : CarbonImmutable::parse((string) $latest);
    }

    public function findCategory(string $slug): ?Category
    {
        $model = CategoryModel::query()->where('slug', $slug)->first();

        return $model === null ? null : new Category($model->slug, $model->name);
    }

    public function byCategory(string $slug): array
    {
        return $this->toDtos(
            $this->query()
                ->whereHas('category', fn (Builder $q) => $q->where('slug', $slug))
                ->orderByDesc('rating_rate')
                ->orderBy('id')
                ->get()
        );
    }

    public function categories(): array
    {
        return CategoryModel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (CategoryModel $c): Category => new Category($c->slug, $c->name))
            ->values()
            ->all();
    }

    public function search(string $term): array
    {
        // Escape LIKE wildcards so the term is matched literally; whereLike binds it as a parameter.
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        return $this->toDtos(
            $this->query()
                ->where(fn (Builder $q) => $q
                    ->whereLike('title', $pattern)
                    ->orWhereLike('description', $pattern)
                    ->orWhereHas('category', fn (Builder $c) => $c->whereLike('name', $pattern)))
                ->orderBy('title')
                ->orderBy('id')
                ->get()
        );
    }

    /** @return Builder<ProductModel> */
    private function query(): Builder
    {
        return ProductModel::query()->with('category');
    }

    /**
     * @param  iterable<ProductModel>  $models
     * @return list<Product>
     */
    private function toDtos(iterable $models): array
    {
        $products = [];
        foreach ($models as $model) {
            $products[] = $this->toDto($model);
        }

        return $products;
    }

    private function toDto(ProductModel $model): Product
    {
        /** @var CategoryModel $category */
        $category = $model->category;

        return new Product(
            id: $model->id,
            source: $model->source,
            externalId: $model->external_id,
            title: $model->title,
            description: $model->description,
            price: (float) $model->price,
            imageUrl: $model->image_url,
            categorySlug: $category->slug,
            categoryName: $category->name,
            ratingRate: (float) $model->rating_rate,
            ratingCount: $model->rating_count,
        );
    }
}
