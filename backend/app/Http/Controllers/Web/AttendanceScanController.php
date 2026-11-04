<?php

namespace App\Http\Controllers\Web;

use App\Services\AttendanceScanService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceScanController extends \App\Http\Controllers\Controller
{
    public function index()
    {
        $this->authorize('attendance.scan');

        return view('school-admin.attendance.scan');
    }

    /**
     * Same endpoint the app's camera scanner calls (as JSON) also backs
     * this page's plain autofocused text field — a USB/Bluetooth barcode
     * scanner is just a keyboard to the browser (it "types" the code then
     * Enter), so no camera/JS scanning library is needed on the portal.
     */
    public function store(Request $request, TenantContext $tenant, AttendanceScanService $scanner)
    {
        $this->authorize('attendance.scan');

        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);

        try {
            $result = $scanner->scan($tenant->assertBound(), $data['code'], $request->user());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $result]);
    }
}
