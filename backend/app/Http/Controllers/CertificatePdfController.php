<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Shared between the portal (session auth) and API/mobile clients (token
 * auth) — registered once on a route using both guards, rather than
 * duplicated per entry point like the early Phase 1 controllers were.
 */
class CertificatePdfController extends Controller
{
    public function show(Certificate $certificate)
    {
        $this->authorize('certificates.view');

        $certificate->load(['student.school', 'template', 'issuedBy']);

        $verificationUrl = route('verify.certificate', $certificate->verification_token);
        $qrSvg = QrCode::size(140)->generate($verificationUrl);

        $orientation = $certificate->template?->orientation === 'portrait' ? 'portrait' : 'landscape';

        $layout = $certificate->template?->layout ?? 'classic';
        $view = view()->exists("certificates.layouts.{$layout}") ? "certificates.layouts.{$layout}" : 'certificates.layouts.classic';

        $pdf = Pdf::loadView($view, [
            'certificate' => $certificate,
            'school' => $certificate->student->school,
            'qrSvg' => $qrSvg,
            'verificationUrl' => $verificationUrl,
            'template' => $certificate->template,
        ])->setPaper('a4', $orientation);

        return $pdf->stream("certificate-{$certificate->certificate_number}.pdf");
    }
}
