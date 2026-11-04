<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\SendSmsRequest;
use App\Http\Resources\SmsLogResource;
use App\Models\SchoolClass;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\SmsService;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('communications.view');

        $logs = SmsLog::query()
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('category')->isNotEmpty(), fn ($q) => $q->where('category', $request->string('category')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return SmsLogResource::collection($logs);
    }

    public function store(SendSmsRequest $request, SmsService $sms)
    {
        $data = $request->validated();
        $category = $data['category'] ?? 'general';

        if ($data['recipient_type'] === 'student') {
            $student = Student::findOrFail($data['student_id']);
            $log = $sms->sendToStudent($student, $data['message'], $category, $request->user());

            return response()->json(['data' => new SmsLogResource($log)], 201);
        }

        $class = SchoolClass::findOrFail($data['school_class_id']);
        $students = Student::where('school_class_id', $class->id)->where('status', 'active')->get();
        $result = $sms->sendToStudents($students, $data['message'], $category, $request->user());

        return response()->json([
            'data' => SmsLogResource::collection($result['sent']),
            'meta' => ['sent' => $result['sent']->count(), 'skipped' => $result['skipped']->count()],
        ], 201);
    }
}
