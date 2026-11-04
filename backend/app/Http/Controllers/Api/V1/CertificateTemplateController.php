<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\StoreCertificateTemplateRequest;
use App\Http\Requests\Certificates\UpdateCertificateTemplateRequest;
use App\Http\Resources\CertificateTemplateResource;
use App\Models\CertificateTemplate;
use App\Services\TemplateGalleryService;
use App\Support\Tenancy\TenantContext;

class CertificateTemplateController extends Controller
{
    public function index(TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $this->authorize('certificates.view');

        $gallery->ensureCertificateDefaults($tenant->assertBound());

        return CertificateTemplateResource::collection(
            CertificateTemplate::query()->orderByDesc('is_default')->orderBy('name')->get()
        );
    }

    public function store(StoreCertificateTemplateRequest $request, TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $template = CertificateTemplate::create(['school_id' => $tenant->id(), ...$request->validated()]);

        if ($template->is_default) {
            $gallery->activate($template);
        }

        return response()->json(['data' => new CertificateTemplateResource($template)], 201);
    }

    public function update(UpdateCertificateTemplateRequest $request, CertificateTemplate $certificateTemplate, TemplateGalleryService $gallery)
    {
        $certificateTemplate->update($request->validated());

        if ($certificateTemplate->is_default) {
            $gallery->activate($certificateTemplate);
        }

        return new CertificateTemplateResource($certificateTemplate);
    }

    /** Make this the one template used whenever a certificate is issued without choosing one. */
    public function activate(CertificateTemplate $certificateTemplate, TemplateGalleryService $gallery)
    {
        $this->authorize('certificate_templates.manage');

        $gallery->activate($certificateTemplate);

        return new CertificateTemplateResource($certificateTemplate->fresh());
    }
}
