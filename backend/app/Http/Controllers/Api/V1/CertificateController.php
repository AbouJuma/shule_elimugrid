<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\IssueCertificateRequest;
use App\Http\Requests\Certificates\RevokeCertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Student;
use App\Services\CertificateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('certificates.view');

        return CertificateResource::collection(
            Certificate::query()
                ->with(['student', 'template'])
                ->when($request->integer('student_id'), fn ($q, $id) => $q->where('student_id', $id))
                ->latest()
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(IssueCertificateRequest $request, TenantContext $tenant, CertificateService $certificates)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);

        $certificate = $certificates->issue($tenant->assertBound(), $student, $data, $request->user());

        return response()->json([
            'data' => new CertificateResource($certificate->load(['student', 'template'])),
        ], 201);
    }

    public function show(Certificate $certificate)
    {
        $this->authorize('certificates.view');

        return new CertificateResource($certificate->load(['student', 'template', 'issuedBy']));
    }

    public function revoke(RevokeCertificateRequest $request, Certificate $certificate, CertificateService $certificates)
    {
        $certificates->revoke($certificate, $request->validated()['reason'] ?? null);

        return new CertificateResource($certificate->fresh(['student', 'template']));
    }
}
