@extends('layouts.portal')

@section('title', 'Online Exams — ElimuGrid')
@section('page-title', __('Online Exams'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'online-exams'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="school_class_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 180px;">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('school_class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </form>
            @can('online_exams.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newExamModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('Create Online Exam') }}
                </button>
            @endcan
        </div>

        @if ($exams->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-ui-checks display-6 d-block mb-2"></i>
                {{ __('No online exams yet.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Class / Subject') }}</th>
                        <th>{{ __('Mode') }}</th>
                        <th>{{ __('Questions') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exams as $exam)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('school-admin.online-exams.show', $exam) }}'" style="cursor:pointer">
                            <td class="fw-semibold">{{ $exam->title }}</td>
                            <td class="text-secondary small">
                                {{ $exam->schoolClass?->name ?? '—' }}
                                @if ($exam->subject) / {{ $exam->subject->name }} @endif
                            </td>
                            <td class="text-secondary small">{{ ucfirst($exam->mode) }}</td>
                            <td class="text-secondary small">{{ $exam->questions_count }}</td>
                            <td>
                                <span class="badge {{ $exam->status === 'published' ? 'badge-status-active' : 'badge-status-pending' }} rounded-pill px-3 py-2">
                                    {{ ucfirst($exam->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $exams])
        @endif
    </div>

    @can('online_exams.manage')
        <div class="modal fade" id="newExamModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('school-admin.online-exams.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Create Online Exam') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Title') }}</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Description') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-4">
                                    <label class="form-label">{{ __('Class') }}</label>
                                    <select name="school_class_id" class="form-select" required>
                                        <option value="">{{ __('Select class…') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">{{ __('Subject') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select name="subject_id" class="form-select">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">{{ __('Mode') }}</label>
                                    <select name="mode" class="form-select" required>
                                        <option value="graded">{{ __('Graded — one attempt') }}</option>
                                        <option value="practice">{{ __('Practice — unlimited retakes') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3" style="max-width: 220px;">
                                <label class="form-label">{{ __('Time limit (minutes)') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <input type="number" name="time_limit_minutes" class="form-control" min="1" max="600">
                            </div>

                            <hr>
                            <label class="form-label d-block">{{ __('Questions') }}</label>
                            <div class="small text-secondary mb-2">{{ __('Tick the checkbox next to the correct choice for each question.') }}</div>

                            <div id="questionBlocks"></div>
                            <button type="button" id="addQuestionBtn" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-plus-lg"></i> {{ __('Add Question') }}
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create as Draft') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        <template id="questionBlockTemplate">
            <div class="question-block border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="question-number"></strong>
                    <button type="button" class="btn btn-outline-danger btn-sm remove-question"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm question-text-input" placeholder="{{ __('Question text') }}" required>
                </div>
                <div class="mb-2" style="max-width: 140px;">
                    <input type="number" class="form-control form-control-sm points-input" min="1" max="100" value="1" placeholder="{{ __('Points') }}">
                </div>
                <div class="choice-rows"></div>
                <button type="button" class="btn btn-link btn-sm p-0 add-choice">{{ __('+ Add choice') }}</button>
            </div>
        </template>

        <template id="choiceRowTemplate">
            <div class="row g-2 mb-1 choice-row align-items-center">
                <div class="col-auto">
                    <input type="checkbox" class="form-check-input correct-checkbox" title="{{ __('Correct answer') }}">
                </div>
                <div class="col">
                    <input type="text" class="form-control form-control-sm choice-text-input" placeholder="{{ __('Choice text') }}" required>
                </div>
                <div class="col-auto remove-choice-wrap d-none">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-choice"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </template>

        <script>
            (function () {
                var form = document.querySelector('#newExamModal form');
                var container = document.getElementById('questionBlocks');
                var questionTpl = document.getElementById('questionBlockTemplate');
                var choiceTpl = document.getElementById('choiceRowTemplate');
                if (!form || !container) return;

                function addChoice(block) {
                    var row = choiceTpl.content.cloneNode(true).firstElementChild;
                    block.querySelector('.choice-rows').appendChild(row);
                    row.querySelector('.correct-checkbox').addEventListener('change', function () {
                        if (!this.checked) return;
                        block.querySelectorAll('.correct-checkbox').forEach(function (cb) {
                            if (cb !== row.querySelector('.correct-checkbox')) cb.checked = false;
                        });
                    });
                    row.querySelector('.remove-choice').addEventListener('click', function () {
                        row.remove();
                        refreshChoiceButtons(block);
                    });
                    refreshChoiceButtons(block);
                }

                function refreshChoiceButtons(block) {
                    var rows = block.querySelectorAll('.choice-row');
                    rows.forEach(function (row) {
                        row.querySelector('.remove-choice-wrap').classList.toggle('d-none', rows.length <= 2);
                    });
                }

                function refreshQuestionNumbers() {
                    var blocks = container.querySelectorAll('.question-block');
                    blocks.forEach(function (block, i) {
                        block.querySelector('.question-number').textContent = '{{ __('Question') }} ' + (i + 1);
                        block.querySelector('.remove-question').classList.toggle('d-none', blocks.length <= 1);
                    });
                }

                function addQuestion() {
                    var block = questionTpl.content.cloneNode(true).firstElementChild;
                    container.appendChild(block);
                    addChoice(block);
                    addChoice(block);
                    block.querySelector('.add-choice').addEventListener('click', function () { addChoice(block); });
                    block.querySelector('.remove-question').addEventListener('click', function () {
                        block.remove();
                        refreshQuestionNumbers();
                    });
                    refreshQuestionNumbers();
                }

                document.getElementById('addQuestionBtn').addEventListener('click', addQuestion);
                document.getElementById('newExamModal').addEventListener('shown.bs.modal', function () {
                    if (!container.children.length) addQuestion();
                });

                // Field names carry real array indices only at submit time, so
                // removing/reordering blocks in the DOM never leaves gaps.
                form.addEventListener('submit', function () {
                    container.querySelectorAll('.question-block').forEach(function (block, qi) {
                        block.querySelector('.question-text-input').name = 'questions[' + qi + '][question_text]';
                        block.querySelector('.points-input').name = 'questions[' + qi + '][points]';
                        block.querySelectorAll('.choice-row').forEach(function (row, ci) {
                            row.querySelector('.choice-text-input').name = 'questions[' + qi + '][choices][' + ci + '][choice_text]';
                            var cb = row.querySelector('.correct-checkbox');
                            cb.name = 'questions[' + qi + '][choices][' + ci + '][is_correct]';
                            cb.value = '1';
                        });
                    });
                });
            })();
        </script>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newExamModal')).show();
                });
            </script>
        @endif
    @endcan
@endsection
