<?php

declare(strict_types=1);

namespace App\Catalogue\Repositories;

use App\Catalogue\Contracts\EnquiryRepository;
use App\Catalogue\Data\EnquiryData;
use App\Catalogue\Data\StoredEnquiry;
use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class EloquentEnquiryRepository implements EnquiryRepository
{
    public function createPending(EnquiryData $data, string $productTitle): int
    {
        return Enquiry::query()->create([
            'product_id' => $data->productId,
            'user_id' => $data->userId,
            'product_title' => $productTitle,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'message' => $data->message,
            'status' => EnquiryStatus::Pending,
        ])->id;
    }

    public function markSent(int $id): void
    {
        Enquiry::query()->whereKey($id)->update([
            'status' => EnquiryStatus::Sent,
            'mailed_at' => now(),
        ]);
    }

    public function markFailed(int $id): void
    {
        Enquiry::query()->whereKey($id)->update(['status' => EnquiryStatus::Failed]);
    }

    public function find(int $id): ?StoredEnquiry
    {
        $model = Enquiry::query()->find($id);

        return $model instanceof Enquiry ? $this->toDto($model) : null;
    }

    public function paginate(?EnquiryStatus $status, int $page, int $perPage): LengthAwarePaginator
    {
        return Enquiry::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            // id breaks ties so pages are stable when several enquiries share a timestamp.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page)
            ->through(fn (Enquiry $model): StoredEnquiry => $this->toDto($model));
    }

    public function countsByStatus(): array
    {
        $counts = Enquiry::query()->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];
        foreach (EnquiryStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    private function toDto(Enquiry $model): StoredEnquiry
    {
        return new StoredEnquiry(
            id: $model->id,
            productId: (int) $model->product_id,
            productTitle: (string) $model->product_title,
            userId: $model->user_id === null ? null : (int) $model->user_id,
            name: (string) $model->name,
            email: (string) $model->email,
            phone: $model->phone,
            message: $model->message,
            status: $model->status,
            createdAt: CarbonImmutable::instance($model->created_at),
            mailedAt: $model->mailed_at,
        );
    }
}
