<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in shop user. is_admin, password and tokens are never exposed.
 *
 * @property User $resource
 */
final class UserResource extends JsonResource
{
    /** @return array{id: int, name: string, email: string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
        ];
    }
}
