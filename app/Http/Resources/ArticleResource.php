<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_article ?? $this->getKey(),
            'user_id' => $this->id_user,
            'content' => $this->content,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
        ];
    }
}
