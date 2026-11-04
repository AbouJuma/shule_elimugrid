<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\IdCard;

/**
 * Deliberately unauthenticated — this is the page a QR code scan lands
 * on. Looked up by verification_token (48 random chars), never by the
 * numeric id, certificate_number, or card_number. Certificate
 * verification omits the admission number (not relevant to proving an
 * award is genuine); ID card verification shows it, since confirming the
 * admission number is exactly the point of scanning a student ID. Both
 * omit date of birth and guardian info regardless.
 */
class VerificationController extends Controller
{
    public function certificate(string $token)
    {
        $certificate = Certificate::allTenants()
            ->where('verification_token', $token)
            ->with(['student.school'])
            ->first();

        return view('public.verify-certificate', ['certificate' => $certificate]);
    }

    public function idCard(string $token)
    {
        $idCard = IdCard::allTenants()
            ->where('verification_token', $token)
            ->with(['student.school', 'student.schoolClass'])
            ->first();

        return view('public.verify-id-card', ['idCard' => $idCard]);
    }
}
