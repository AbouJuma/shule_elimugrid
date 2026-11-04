<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\AssignStudentTransportRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;

class StudentTransportController extends Controller
{
    public function update(AssignStudentTransportRequest $request, Student $student)
    {
        $student->update($request->validated());

        return new StudentResource($student->fresh(['schoolClass', 'stream', 'academicYear', 'transportRoute', 'routeStop']));
    }
}
