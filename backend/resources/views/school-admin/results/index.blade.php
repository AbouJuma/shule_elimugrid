@extends('layouts.portal')

@section('title', __('Results & Report Cards').' — ElimuGrid')
@section('page-title', __('Results & Report Cards'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'results'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">{{ __('Term') }}</label>
                    <select name="term_id" class="form-select" required>
                        <option value="">{{ __('Select term…') }}</option>
                        @foreach ($terms as $t)
                            <option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->academicYear?->name }} — {{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">{{ __('Class') }}</label>
                    <select name="school_class_id" class="form-select" required>
                        <option value="">{{ __('Select class…') }}</option>
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected($class?->id === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-gold w-100">{{ __('Load Results') }}</button></div>
                <div class="col-md-2">
                    @can('results.manage')
                        <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#gradeScaleModal">
                            <i class="bi bi-sliders me-1"></i> {{ __('Grade Scale') }}
                        </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>

    @if ($terms->isEmpty())
        <div class="alert alert-warning">{{ __('No terms exist yet.') }} <a href="{{ route('school-admin.terms.index') }}">{{ __('Set up terms first.') }}</a></div>
    @endif

    @if ($report)
        @if ($report['exams']->isEmpty())
            <div class="alert alert-info">{{ __('No exams are attached to this term for this class yet. Create an exam and choose this term, then record marks.') }}</div>
        @else
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Students') }}</div><div class="value">{{ $report['class_size'] }}</div></div></div></div>
                <div class="col-6 col-md-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Exams in term') }}</div><div class="value">{{ $report['exams']->count() }}</div></div></div></div>
                <div class="col-6 col-md-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Class average') }}</div><div class="value">{{ $report['class_average'] !== null ? $report['class_average'].'%' : '—' }}</div></div></div></div>
                <div class="col-6 col-md-3"><div class="card card-metric"><div class="card-body"><div class="text-secondary small">{{ __('Subjects') }}</div><div class="value">{{ $report['subjects']->count() }}</div></div></div></div>
            </div>

            <form method="POST" action="{{ route('school-admin.results.remarks') }}">
                @csrf
                <input type="hidden" name="term_id" value="{{ $term->id }}">
                <input type="hidden" name="school_class_id" value="{{ $class->id }}">

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>{{ $class->name }} — {{ $term->name }} ({{ $term->academicYear?->name }})</span>
                        <div class="d-flex gap-2">
                            @can('results.manage')
                                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chat-left-text me-1"></i> {{ __('Save Remarks') }}</button>
                            @endcan
                            <a href="{{ route('school-admin.results.class-pdf', ['term_id' => $term->id, 'school_class_id' => $class->id]) }}" target="_blank" class="btn btn-gold btn-sm">
                                <i class="bi bi-file-earmark-pdf me-1"></i> {{ __('All Report Cards (PDF)') }}
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle small">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Pos.') }}</th>
                                    <th>{{ __('Student') }}</th>
                                    @foreach ($report['subjects'] as $subject)
                                        <th class="text-center">{{ $subject->name }}</th>
                                    @endforeach
                                    <th class="text-center">{{ __('Total') }}</th>
                                    <th class="text-center">{{ __('Average') }}</th>
                                    <th class="text-center">{{ __('Grade') }}</th>
                                    <th style="min-width: 220px;">{{ __('Class teacher remark') }}</th>
                                    <th style="min-width: 220px;">{{ __('Head teacher remark') }}</th>
                                    <th class="text-end">{{ __('Report') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['cards']->sortBy(fn ($c) => $c['position'] ?? PHP_INT_MAX)->values() as $card)
                                    <tr>
                                        <td class="fw-bold">{{ $card['position'] ?? '—' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $card['student']->fullName() }}</div>
                                            <div class="text-secondary">{{ $card['student']->admission_number }}@if ($card['student']->stream) · {{ $card['student']->stream->name }}@endif</div>
                                        </td>
                                        @foreach ($card['lines'] as $line)
                                            <td class="text-center">
                                                @if ($line['percent'] !== null)
                                                    {{ $line['percent'] }} <span class="text-secondary">({{ $line['grade'] }})</span>
                                                @else — @endif
                                            </td>
                                        @endforeach
                                        <td class="text-center">{{ $card['total'] }}</td>
                                        <td class="text-center fw-semibold">{{ $card['average'] !== null ? $card['average'].'%' : '—' }}</td>
                                        <td class="text-center"><span class="badge badge-status-active rounded-pill px-2 py-1">{{ $card['grade'] }}</span></td>
                                        <td>
                                            @can('results.manage')
                                                <input type="text" class="form-control form-control-sm" maxlength="500"
                                                       name="remarks[{{ $card['student']->id }}][class_teacher_remark]" value="{{ $card['class_teacher_remark'] }}">
                                            @else
                                                {{ $card['class_teacher_remark'] }}
                                            @endcan
                                        </td>
                                        <td>
                                            @can('results.manage')
                                                <input type="text" class="form-control form-control-sm" maxlength="500"
                                                       name="remarks[{{ $card['student']->id }}][head_teacher_remark]" value="{{ $card['head_teacher_remark'] }}">
                                            @else
                                                {{ $card['head_teacher_remark'] }}
                                            @endcan
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('school-admin.results.student-pdf', [$card['student'], 'term_id' => $term->id]) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        @endif
    @endif

    @can('results.manage')
        <div class="modal fade" id="gradeScaleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.results.grade-scale') }}">
                        @csrf @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Grade Scale') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-secondary small">{{ __('A score earns the grade with the highest starting % it reaches. One grade must start at 0.') }}</p>
                            <div id="scaleRows">
                                @foreach ($scale as $i => $row)
                                    <div class="row g-2 mb-2 scale-row">
                                        <div class="col-2"><input type="text" name="scale[{{ $i }}][grade]" class="form-control" value="{{ $row->grade }}" placeholder="A" required></div>
                                        <div class="col-2"><input type="number" min="0" max="100" name="scale[{{ $i }}][min_percent]" class="form-control" value="{{ $row->min_percent }}" placeholder="%" required></div>
                                        <div class="col-3"><input type="text" name="scale[{{ $i }}][remark]" class="form-control" value="{{ $row->remark }}" placeholder="{{ __('Remark') }}"></div>
                                        <div class="col-4"><input type="text" name="scale[{{ $i }}][remark_sw]" class="form-control" value="{{ $row->remark_sw }}" placeholder="{{ __('Remark in Swahili (optional)') }}"></div>
                                        <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-scale-row"><i class="bi bi-x-lg"></i></button></div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="addScaleRow"><i class="bi bi-plus-lg"></i> {{ __('Add grade') }}</button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                        </div>
                    </form>
            </div>
        </div>
        <script>
            (function () {
                let index = {{ $scale->count() }};
                document.getElementById('addScaleRow')?.addEventListener('click', () => {
                    const row = document.createElement('div');
                    row.className = 'row g-2 mb-2 scale-row';
                    row.innerHTML =
                        '<div class="col-3"><input type="text" name="scale[' + index + '][grade]" class="form-control" required></div>' +
                        '<div class="col-3"><input type="number" min="0" max="100" name="scale[' + index + '][min_percent]" class="form-control" required></div>' +
                        '<div class="col-5"><input type="text" name="scale[' + index + '][remark]" class="form-control"></div>' +
                        '<div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-scale-row"><i class="bi bi-x-lg"></i></button></div>';
                    document.getElementById('scaleRows').appendChild(row);
                    index++;
                });
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('.remove-scale-row');
                    if (btn && document.querySelectorAll('.scale-row').length > 2) btn.closest('.scale-row').remove();
                });
            })();
        </script>
    @endcan
@endsection
