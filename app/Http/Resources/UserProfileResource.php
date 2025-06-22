<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'preferences' => $this->preferences,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}