<?php

namespace App\Http\Requests\Learning;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lessons.manage');
    }

    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->id();

        return [
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'subject_id' => ['nullable', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'video_url' => ['nullable', 'url', 'max:500'],
            // 200MB — generous for a classroom recording, capped so one
            // upload can't quietly fill this deployment's local disk (see
            // FILE_PLAN's storage known-deviation).
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo', 'max:204800'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('video_url') && ! $this->hasFile('video')) {
                $validator->errors()->add('video_url', __('Provide a video link or upload a video file.'));
            }
        });
    }
}
