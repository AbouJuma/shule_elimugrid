<?php

namespace App\Http\Controllers;

use App\Models\IdCard;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Same shared-controller pattern as CertificatePdfController — one route
 * usable by both the session-authenticated portal and token-authenticated
 * API/mobile clients.
 */
class IdCardPdfController extends Controller
{
    public function show(IdCard $idCard)
    {
        $this->authorize('id_cards.view');

        $idCard->load(['student.school', 'student.schoolClass', 'template']);

        $verificationUrl = route('verify.id-card', $idCard->verification_token);
        // Small — this is a CR80 card (~243pt x 153pt total), not a page;
        // the certificate's 140px QR at this scale alone exceeded the
        // whole card's height and caused a silent page overflow.
        $qrSvg = QrCode::size(40)->generate($verificationUrl);

        // CR80 card size (85.6mm x 54mm) in points, the standard ID card
        // dimension — not an arbitrary page size. Portrait templates swap
        // the two dimensions rather than using a different physical size.
        $portrait = $idCard->template?->orientation === 'portrait';
        $dimensions = $portrait ? [0, 0, 153.07, 242.65] : [0, 0, 242.65, 153.07];

        $layout = $idCard->template?->layout ?? 'classic';
        $view = view()->exists("id-cards.layouts.{$layout}") ? "id-cards.layouts.{$layout}" : 'id-cards.layouts.classic';

        $pdf = Pdf::loadView($view, [
            'idCard' => $idCard,
            'school' => $idCard->student->school,
            'qrSvg' => $qrSvg,
            'template' => $idCard->template,
            'portrait' => $portrait,
        ])->setPaper($dimensions);

        return $pdf->stream("id-card-{$idCard->card_number}.pdf");
    }
}
