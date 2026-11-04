<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Certificates\IssueCertificateRequest;
use App\Http\Requests\Certificates\RevokeCertificateRequest;
use App\Http\Requests\Certificates\StoreCertificateTemplateRequest;
use App\Http\Requests\Certificates\UpdateCertificateTemplateRequest;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Services\CertificateService;
use App\Services\TemplateGalleryService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CertificateController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $this->authorize('certificates.view');

        $gallery->ensureCertificateDefaults($tenant->assertBound());

        return view('school-admin.certificates.index', [
            'certificates' => Certificate::query()
                ->with(['student', 'template'])
                ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'templates' => CertificateTemplate::query()->orderBy('is_default', 'desc')->orderBy('name')->get(),
            'layouts' => config('template_layouts.certificate'),
            'students' => Student::query()->where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }

    public function store(IssueCertificateRequest $request, TenantContext $tenant, CertificateService $certificates)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);

        $certificate = $certificates->issue($tenant->assertBound(), $student, $data, $request->user());

        return redirect()
            ->route('school-admin.certificates.index')
            ->with('status', __('Certificate :p1 issued to :p2.', ['p1' => $certificate->certificate_number, 'p2' => $student->fullName()]));
    }

    public function revoke(RevokeCertificateRequest $request, Certificate $certificate, CertificateService $certificates)
    {
        $certificates->revoke($certificate, $request->validated()['reason'] ?? null);

        return redirect()->route('school-admin.certificates.index')->with('status', 'Certificate revoked.');
    }

    public function storeTemplate(StoreCertificateTemplateRequest $request, TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $template = CertificateTemplate::create(['school_id' => $tenant->id(), ...$request->validated()]);

        if ($template->is_default) {
            $gallery->activate($template);
        }

        return redirect()->route('school-admin.certificates.index')->with('status', __('Certificate template created.'));
    }

    public function updateTemplate(UpdateCertificateTemplateRequest $request, CertificateTemplate $certificateTemplate, TemplateGalleryService $gallery)
    {
        $certificateTemplate->update($request->validated());

        if ($certificateTemplate->is_default) {
            $gallery->activate($certificateTemplate);
        }

        return redirect()->route('school-admin.certificates.index')->with('status', __('Certificate template updated.'));
    }

    /** Make this the one template used whenever a certificate is issued without choosing one. */
    public function activateTemplate(CertificateTemplate $certificateTemplate, TemplateGalleryService $gallery)
    {
        $this->authorize('certificate_templates.manage');

        $gallery->activate($certificateTemplate);

        return redirect()->route('school-admin.certificates.index')->with('status', __(':p1 is now the active certificate template.', ['p1' => $certificateTemplate->name]));
    }

    public function destroyTemplate(CertificateTemplate $certificateTemplate)
    {
        $this->authorize('certificate_templates.manage');

        $certificateTemplate->delete();

        return redirect()->route('school-admin.certificates.index')->with('status', 'Certificate template deleted.');
    }
}
