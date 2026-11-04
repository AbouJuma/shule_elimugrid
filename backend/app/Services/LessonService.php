<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LessonService
{
    /**
     * @param  array{school_class_id: int, subject_id?: ?int, title: string, description?: ?string, video_url?: ?string}  $data
     */
    public function create(School $school, array $data, ?UploadedFile $video, User $creator): Lesson
    {
        $path = $video?->store("lessons/{$school->id}", 'public');

        return Lesson::create([
            'school_id' => $school->id,
            'school_class_id' => $data['school_class_id'],
            'subject_id' => $data['subject_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'video_path' => $path,
            'created_by' => $creator->id,
        ]);
    }

    public function delete(Lesson $lesson): void
    {
        if ($lesson->video_path) {
            Storage::disk('public')->delete($lesson->video_path);
        }

        $lesson->delete();
    }
}
