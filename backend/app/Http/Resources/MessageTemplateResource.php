<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MessageTemplate */
class MessageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'body' => $this->body,
            'event' => $this->event,
            'locale' => $this->locale,
            'is_system' => $this->isSystem(),
            'tokens' => $this->when($this->isSystem(), fn () => collect(config('message_templates.events.'.$this->event.'.tokens', []))->map(fn ($t) => ['token' => $t, 'meaning' => __(config('message_templates.tokens.'.$t, $t))])->values()),
            'preview' => $this->when($this->isSystem(), fn () => app(\App\Services\FeeMessageService::class)->preview(app(\App\Support\Tenancy\TenantContext::class)->assertBound(), $this->event, $this->locale)),
        ];
    }
}
