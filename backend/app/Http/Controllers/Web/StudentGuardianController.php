<?php

namespace App\Http\Controllers\Web;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Editing a guardian's own details (name, phone, email, …) plus their
 * relationship to one particular student — from the student's "Guardians"
 * tab. The guardian row itself (App\Models\Guardian) is shared across
 * every student they're linked to; the relationship/is_primary fields
 * live on the student_guardian pivot and are scoped to this one student.
 */
class StudentGuardianController extends \App\Http\Controllers\Controller
{
    public function update(Request $request, Student $student, Guardian $guardian)
    {
        $this->authorize('guardians.manage');

        abort_unless($student->guardians()->where('guardians.id', $guardian->id)->exists(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales')))],
            'relationship' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $guardian->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'address' => $data['address'] ?? null,
            'locale' => $data['locale'] ?? null,
        ]);

        $isPrimary = $request->boolean('is_primary');

        if ($isPrimary) {
            // Only one guardian can be "primary" for a given student.
            DB::table('student_guardian')
                ->where('student_id', $student->id)
                ->where('guardian_id', '!=', $guardian->id)
                ->update(['is_primary' => false]);
        }

        $student->guardians()->updateExistingPivot($guardian->id, [
            'relationship' => $data['relationship'],
            'is_primary' => $isPrimary,
        ]);

        return redirect()->route('school-admin.students.index')
            ->with('status', __(":p1's details were updated.", ['p1' => $guardian->name]));
    }
}
