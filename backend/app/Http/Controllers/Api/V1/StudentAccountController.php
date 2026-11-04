<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A login for a student (Student role), so they can see their own results,
 * timetable, fees and attendance. Linked through students.user_id; what they
 * can see is decided by that link, not by a school-wide permission.
 *
 * Students often have no email address, so the school supplies one (a family
 * address is fine). The temporary password is shown once to whoever creates the
 * login — it is not texted, because a student has no phone of their own.
 */
class StudentAccountController extends Controller
{
    public function store(Request $request, Student $student, TenantContext $tenant)
    {
        $this->authorize('students.update');
        $school = $tenant->assertBound();

        abort_if($student->user_id !== null, 422, __('This student already has a login.'));

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        $password = Str::password(12, symbols: false);

        $user = User::create([
            'school_id' => $school->id,
            'name' => $student->fullName(),
            'email' => $data['email'],
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        setPermissionsTeamId($school->id);
        $user->assignRole('Student');

        $student->update(['user_id' => $user->id]);

        return response()->json(['data' => $this->resource($student->fresh('user')), 'temporary_password' => $password], 201);
    }

    public function reset(Student $student)
    {
        $this->authorize('students.update');

        abort_unless($student->user, 404);

        $password = Str::password(12, symbols: false);
        $student->user->update(['password' => Hash::make($password)]);
        $student->user->tokens()->delete();

        return response()->json(['data' => $this->resource($student->fresh('user')), 'temporary_password' => $password]);
    }

    protected function resource(Student $s): array
    {
        return ['id' => $s->uuid, 'name' => $s->fullName(), 'has_login' => $s->user_id !== null, 'login_email' => $s->user?->email];
    }
}
