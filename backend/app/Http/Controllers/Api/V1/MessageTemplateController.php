<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\StoreMessageTemplateRequest;
use App\Http\Requests\Communications\UpdateMessageTemplateRequest;
use App\Http\Resources\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Services\MessageTemplateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class MessageTemplateController extends Controller
{
    /** The school's templates, including the automatic messages (created from the defaults on first use). */
    public function index(TenantContext $tenant, MessageTemplateService $templates)
    {
        $this->authorize('communications.view');

        $templates->ensureDefaults($tenant->assertBound());

        return MessageTemplateResource::collection(
            MessageTemplate::query()->orderByRaw('event IS NULL')->orderBy('event')->orderBy('locale')->orderBy('name')->get()
        );
    }

    public function store(StoreMessageTemplateRequest $request, TenantContext $tenant)
    {
        $template = MessageTemplate::create(['school_id' => $tenant->id(), ...$request->validated(), 'locale' => $request->validated('locale') ?? 'en']);

        return response()->json(['data' => new MessageTemplateResource($template)], 201);
    }

    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate)
    {
        $data = $request->validated();

        if (! $messageTemplate->isSystem()) {
            $data['locale'] = $data['locale'] ?? $messageTemplate->locale;
        }

        $messageTemplate->update($data);

        return new MessageTemplateResource($messageTemplate);
    }

    /** Put an automatic message back to the wording it shipped with. */
    public function reset(MessageTemplate $messageTemplate)
    {
        $this->authorize('communications.manage_templates');

        $default = $messageTemplate->isSystem() ? config("message_templates.events.{$messageTemplate->event}.bodies.{$messageTemplate->locale}") : null;

        if ($default === null) {
            throw ValidationException::withMessages(['template' => [__('Only the automatic messages have a default to go back to.')]]);
        }

        $messageTemplate->update(['body' => $default]);

        return new MessageTemplateResource($messageTemplate);
    }

    public function destroy(MessageTemplate $messageTemplate)
    {
        $this->authorize('communications.manage_templates');

        if ($messageTemplate->isSystem()) {
            throw ValidationException::withMessages(['template' => [__('Automatic messages cannot be deleted — edit their wording instead.')]]);
        }

        $messageTemplate->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
