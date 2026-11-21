<?php

declare(strict_types=1);

namespace App\Catalogue\Repositories;

use App\Catalogue\Contracts\CurrencyRepository;
use App\Catalogue\Data\Currency;
use App\Catalogue\Data\CurrencyInput;
use App\Models\Currency as CurrencyModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentCurrencyRepository implements CurrencyRepository
{
    public function __construct(private ConnectionInterface $db) {}

    public function all(): array
    {
        return $this->ordered(CurrencyModel::query());
    }

    public function enabled(): array
    {
        return $this->ordered(CurrencyModel::query()->where('enabled', true));
    }

    public function find(string $code): ?Currency
    {
        $model = CurrencyModel::query()->find($code);

        return $model === null ? null : self::toDto($model);
    }

    public function default(): Currency
    {
        $model = CurrencyModel::query()->where('is_default', true)->orderBy('code')->first()
            ?? throw new \LogicException('No default currency is configured.');

        return self::toDto($model);
    }

    public function ratesUpdatedAt(): ?CarbonImmutable
    {
        $latest = CurrencyModel::query()->max('rate_updated_at');

        return $latest === null ? null : CarbonImmutable::parse((string) $latest);
    }

    public function create(CurrencyInput $input, CarbonImmutable $now): Currency
    {
        $model = CurrencyModel::query()->create([
            'code' => $input->code,
            'name' => $input->name,
            'symbol' => $input->symbol,
            'rate' => $input->rate,
            'enabled' => $input->enabled,
            'is_default' => false,
            'rate_updated_at' => $now,
        ]);

        return self::toDto($model);
    }

    public function update(string $code, CurrencyInput $input, CarbonImmutable $now): void
    {
        $model = CurrencyModel::query()->findOrFail($code);
        $rateChanged = abs((float) $model->rate - $input->rate) >= 0.000000005;

        $model->fill([
            'name' => $input->name,
            'symbol' => $input->symbol,
            'rate' => $input->rate,
            'enabled' => $input->enabled,
        ]);
        if ($rateChanged) {
            $model->rate_updated_at = $now;
        }
        $model->save();
    }

    public function makeDefault(string $code): void
    {
        $this->db->transaction(function () use ($code): void {
            CurrencyModel::query()->where('code', '!=', $code)->update(['is_default' => false]);
            CurrencyModel::query()->whereKey($code)->update(['is_default' => true]);
        });
    }

    public function delete(string $code): void
    {
        CurrencyModel::query()->whereKey($code)->delete();
    }

    public function updateRates(array $rates, CarbonImmutable $now): int
    {
        return $this->db->transaction(function () use ($rates, $now): int {
            $updated = 0;
            foreach ($rates as $code => $rate) {
                $updated += CurrencyModel::query()->whereKey($code)->update([
                    'rate' => $rate,
                    'rate_updated_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $updated;
        });
    }

    /**
     * @param  Builder<CurrencyModel>  $query
     * @return list<Currency>
     */
    private function ordered(Builder $query): array
    {
        return $query->orderByDesc('is_default')->orderBy('code')->get()
            ->map(fn (CurrencyModel $model): Currency => self::toDto($model))
            ->values()
            ->all();
    }

    private static function toDto(CurrencyModel $model): Currency
    {
        return new Currency(
            code: $model->code,
            name: $model->name,
            symbol: $model->symbol,
            rate: (float) $model->rate,
            enabled: $model->enabled,
            isDefault: $model->is_default,
            rateUpdatedAt: $model->rate_updated_at,
        );
    }
}
