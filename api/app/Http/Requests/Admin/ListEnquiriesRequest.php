<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EnquiryStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * List filters are read leniently: an unknown `status` means "all" and a bad `page` means page 1, and the
 * list still renders (200). A failed GET validation would redirect "back" to an unrelated page (QA B5).
 */
final class ListEnquiriesRequest extends FormRequest
{
    private const int MAX_PAGE = 100000;

    /** Access is enforced by the route group (auth + can:admin). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function status(): ?EnquiryStatus
    {
        $status = $this->query('status');

        return is_string($status) ? EnquiryStatus::tryFrom($status) : null;
    }

    public function page(): int
    {
        $page = filter_var($this->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_PAGE]]);

        return is_int($page) ? $page : 1;
    }
}
