<?php

namespace App\Services;

use App\Models\Homework;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HomeworkService
{
    /**
     * @param  array{school_class_id: int, subject_id?: ?int, title: string, instructions?: ?string, due_date?: ?string}  $data
     */
    public function create(School $school, array $data, ?UploadedFile $attachment, User $creator): Homework
    {
        $path = $attachment?->store("homework/{$school->id}", 'public');

        return Homework::create([
            'school_id' => $school->id,
            'school_class_id' => $data['school_class_id'],
            'subject_id' => $data['subject_id'] ?? null,
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'attachment_path' => $path,
            'created_by' => $creator->id,
        ]);
    }

    public function delete(Homework $homework): void
    {
        if ($homework->attachment_path) {
            Storage::disk('public')->delete($homework->attachment_path);
        }

        $homework->delete();
    }
}
