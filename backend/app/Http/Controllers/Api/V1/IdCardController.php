<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IdCards\IssueIdCardRequest;
use App\Http\Requests\IdCards\RevokeIdCardRequest;
use App\Http\Resources\IdCardResource;
use App\Models\IdCard;
use App\Models\Student;
use App\Services\IdCardService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class IdCardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('id_cards.view');

        return IdCardResource::collection(
            IdCard::query()
                ->with(['student', 'template'])
                ->when($request->integer('student_id'), fn ($q, $id) => $q->where('student_id', $id))
                ->latest()
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(IssueIdCardRequest $request, TenantContext $tenant, IdCardService $idCards)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);

        $idCard = $idCards->issue($tenant->assertBound(), $student, $data, $request->user());

        return response()->json(['data' => new IdCardResource($idCard->load(['student', 'template']))], 201);
    }

    public function show(IdCard $idCard)
    {
        $this->authorize('id_cards.view');

        return new IdCardResource($idCard->load(['student', 'template', 'issuedBy']));
    }

    public function revoke(RevokeIdCardRequest $request, IdCard $idCard, IdCardService $idCards)
    {
        $idCards->revoke($idCard, $request->validated()['reason'] ?? null);

        return new IdCardResource($idCard->fresh(['student', 'template']));
    }
}
