<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IdCards\StoreIdCardTemplateRequest;
use App\Http\Requests\IdCards\UpdateIdCardTemplateRequest;
use App\Http\Resources\IdCardTemplateResource;
use App\Models\IdCardTemplate;
use App\Services\TemplateGalleryService;
use App\Support\Tenancy\TenantContext;

class IdCardTemplateController extends Controller
{
    public function index(TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $this->authorize('id_cards.view');

        $gallery->ensureIdCardDefaults($tenant->assertBound());

        return IdCardTemplateResource::collection(
            IdCardTemplate::query()->orderByDesc('is_default')->orderBy('name')->get()
        );
    }

    public function store(StoreIdCardTemplateRequest $request, TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $template = IdCardTemplate::create(['school_id' => $tenant->id(), ...$request->validated()]);

        if ($template->is_default) {
            $gallery->activate($template);
        }

        return response()->json(['data' => new IdCardTemplateResource($template)], 201);
    }

    public function update(UpdateIdCardTemplateRequest $request, IdCardTemplate $idCardTemplate, TemplateGalleryService $gallery)
    {
        $idCardTemplate->update($request->validated());

        if ($idCardTemplate->is_default) {
            $gallery->activate($idCardTemplate);
        }

        return new IdCardTemplateResource($idCardTemplate);
    }

    /** Make this the one template used whenever a card is issued without choosing one. */
    public function activate(IdCardTemplate $idCardTemplate, TemplateGalleryService $gallery)
    {
        $this->authorize('id_card_templates.manage');

        $gallery->activate($idCardTemplate);

        return new IdCardTemplateResource($idCardTemplate->fresh());
    }
}
