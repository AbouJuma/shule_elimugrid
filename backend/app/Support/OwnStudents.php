<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The students a signed-in user may see without any school-wide permission:
 * the children of the guardian record linked to their login, or the student
 * record linked to their own (student) login.
 */
class OwnStudents
{
    /** @return Builder<Student> */
    public static function query(User $user): Builder
    {
        $guardianIds = Guardian::query()->where('user_id', $user->id)->pluck('id');

        return Student::query()->where(function ($q) use ($user, $guardianIds) {
            $q->where('students.user_id', $user->id)
                ->orWhereHas('guardians', fn ($g) => $g->whereIn('guardians.id', $guardianIds));
        });
    }

    public static function owns(User $user, Student $student): bool
    {
        return self::query($user)->whereKey($student->id)->exists();
    }
}
