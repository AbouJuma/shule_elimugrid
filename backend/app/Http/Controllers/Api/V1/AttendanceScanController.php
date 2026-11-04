<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AttendanceScanService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceScanController extends Controller
{
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
