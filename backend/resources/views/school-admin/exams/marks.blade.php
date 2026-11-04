@extends('layouts.portal')

@section('title', "{$subject->name} Marks — ElimuGrid")
@section('page-title', "{$exam->name}: {$subject->name}")

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'exams'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <a href="{{ route('school-admin.exams.show', $exam) }}" class="d-inline-block mb-3 small">
        <i class="bi bi-arrow-left"></i> Back to {{ $exam->name }}
    </a>

    <div class="card">
        <div class="card-header bg-white">
            {{ $class->name }} — out of {{ $maxMarks }} marks
        </div>

        @if ($rows->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No students in this class yet.') }}</div>
        @else
            <form method="POST" action="{{ route('school-admin.exams.marks.store', [$exam, $subject]) }}">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $class->id }}">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Admission No.') }}</th>
                            <th>{{ __('Student') }}</th>
                            <th style="width: 140px;">{{ __('Marks') }}</th>
                            <th>{{ __('Remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr>
                                <td class="text-secondary small">{{ $row['student']->admission_number }}</td>
                                <td>{{ $row['student']->fullName() }}</td>
                                <td>
                                    <input type="hidden" name="records[{{ $i }}][student_id]" value="{{ $row['student']->id }}">
                                    <input type="number" step="0.01" min="0" max="{{ $maxMarks }}"
                                           name="records[{{ $i }}][marks_obtained]" class="form-control form-control-sm"
                                           value="{{ $row['marks_obtained'] }}" placeholder="—">
                                </td>
                                <td>
                                    <input type="text" name="records[{{ $i }}][remarks]" class="form-control form-control-sm"
                                           value="{{ $row['remarks'] }}" placeholder="{{ __('Optional') }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-gold">{{ __('Save Marks') }}</button>
                </div>
            </form>
        @endif
    </div>
@endsection
