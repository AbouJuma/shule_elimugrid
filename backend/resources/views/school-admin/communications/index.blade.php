@extends('layouts.portal')

@section('title', 'Communications — ElimuGrid')
@section('page-title', __('Communications'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'communications'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    @if (config('services.sms.driver') !== 'beem')
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ __('No live SMS provider is configured — messages are logged as') }} <strong>{{ __('simulated') }}</strong> {{ __('and not actually delivered. Set') }} <code>SMS_DRIVER=beem</code> {{ __('and the Beem credentials in') }} <code>{{ __('.env') }}</code> {{ __('to send real messages.') }}
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">{{ __('Send a Message') }}</h2>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
                    @endif
                    @can('communications.send')
                        <form method="POST" action="{{ route('school-admin.communications.store') }}" id="composeForm">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label d-block">{{ __('Send to') }}</label>
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="recipient_type" id="recipientStudent" value="student" checked>
                                    <label class="btn btn-outline-secondary btn-sm" for="recipientStudent">{{ __('One student') }}</label>
                                    <input type="radio" class="btn-check" name="recipient_type" id="recipientClass" value="class">
                                    <label class="btn btn-outline-secondary btn-sm" for="recipientClass">{{ __('Whole class') }}</label>
                                </div>
                            </div>
                            <div class="mb-3" id="studentField">
                                <label class="form-label">{{ __('Student') }}</label>
                                <select name="student_id" class="form-select">
                                    <option value="">{{ __('Select student…') }}</option>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}">{{ $student->fullName() }} ({{ $student->admission_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3 d-none" id="classField">
                                <label class="form-label">{{ __('Class') }}</label>
                                <select name="school_class_id" class="form-select">
                                    <option value="">{{ __('Select class…') }}</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('Sends to every active student\'s primary guardian in this class.') }}</div>
                            </div>
                            @if ($templates->isNotEmpty())
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Insert a template') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <select class="form-select form-select-sm" id="templatePicker">
                                        <option value="">{{ __('— None —') }}</option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->body }}" data-category="{{ $template->category }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">{{ __('Message') }}</label>
                                <textarea name="message" id="messageBody" class="form-control" rows="4" maxlength="1000" required></textarea>
                                <div class="form-text"><span id="charCount">0</span>/1000</div>
                            </div>
                            <input type="hidden" name="category" id="categoryField" value="general">
                            <button type="submit" class="btn btn-gold">
                                <i class="bi bi-send me-1"></i> {{ __('Send') }}
                            </button>
                        </form>
                    @else
                        <p class="text-secondary small mb-0">{{ __('You do not have permission to send messages.') }}</p>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">{{ __('Templates') }}</h2>
                    @can('communications.manage_templates')
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#newTemplateModal">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    @endcan
                </div>
                <div class="card-body" style="max-height: 320px; overflow-y: auto;">
                    @if ($templates->isEmpty())
                        <p class="text-secondary small mb-0">{{ __('No templates yet.') }}</p>
                    @else
                        @foreach ($templates as $template)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="fw-semibold small">{{ $template->name }}</span>
                                    <span class="badge bg-light text-secondary">{{ str_replace('_', ' ', $template->category) }}</span>
                                </div>
                                <p class="text-secondary small mb-1">{{ \Illuminate\Support\Str::limit($template->body, 80) }}</p>
                                @can('communications.manage_templates')
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-bs-toggle="modal" data-bs-target="#editTemplateModal{{ $template->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('school-admin.communications.templates.destroy', $template) }}"
                                              onsubmit="return confirm('Delete template \'{{ $template->name }}\'?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($systemTemplates->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h2 class="h6 mb-1">{{ __('Automatic messages') }}</h2>
                <p class="text-secondary small mb-0">{{ __('Sent by the system on its own — for example to a parent when a student is admitted. Each has an English and a Kiswahili wording; a parent gets the language set on their record, otherwise the school\'s message language.') }}</p>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Message') }}</th>
                            <th>{{ __('Language') }}</th>
                            <th>{{ __('What the parent receives (sample)') }}</th>
                            @can('communications.manage_templates')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($systemTemplates as $t)
                            @php
                                $len = mb_strlen($previews[$t->id] ?? '');
                                $sms = $len <= 160 ? 1 : (int) ceil($len / 153);
                            @endphp
                            <tr>
                                <td style="min-width: 200px;">
                                    <div class="fw-semibold">{{ __($events[$t->event]['name']) }}</div>
                                    <div class="text-secondary small">{{ __($events[$t->event]['description']) }}</div>
                                </td>
                                <td><span class="badge bg-light text-secondary">{{ config('app.supported_locales')[$t->locale] ?? $t->locale }}</span></td>
                                <td class="small" style="min-width: 300px;">
                                    {{ $previews[$t->id] ?? '' }}
                                    <div class="text-secondary mt-1">{{ $len }} {{ __('characters') }} · {{ $sms }} {{ __('SMS') }}</div>
                                </td>
                                @can('communications.manage_templates')
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editSystemTemplate{{ $t->id }}"><i class="bi bi-pencil"></i> {{ __('Edit') }}</button>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-white">
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px;">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="pending" @selected(request('status')==='pending')>{{ __('Pending') }}</option>
                    <option value="sent" @selected(request('status')==='sent')>{{ __('Sent') }}</option>
                    <option value="simulated" @selected(request('status')==='simulated')>{{ __('Simulated') }}</option>
                    <option value="failed" @selected(request('status')==='failed')>{{ __('Failed') }}</option>
                </select>
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 170px;">
                    <option value="">{{ __('All categories') }}</option>
                    <option value="general" @selected(request('category')==='general')>{{ __('General') }}</option>
                    <option value="fee_reminder" @selected(request('category')==='fee_reminder')>{{ __('Fee reminder') }}</option>
                    <option value="attendance" @selected(request('category')==='attendance')>{{ __('Attendance') }}</option>
                    <option value="exam_result" @selected(request('category')==='exam_result')>{{ __('Exam result') }}</option>
                </select>
            </form>
        </div>

        @if ($logs->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-chat-dots display-6 d-block mb-2"></i>
                {{ __('No messages match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Recipient') }}</th>
                        <th>{{ __('Message') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Sent') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-secondary">{{ $logs->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="fw-semibold">{{ $log->recipient_name ?? $log->recipient_phone }}</div>
                                <div class="text-secondary small">{{ $log->recipient_phone }}</div>
                            </td>
                            <td class="text-secondary small">{{ \Illuminate\Support\Str::limit($log->message, 60) }}</td>
                            <td class="text-secondary small">{{ str_replace('_', ' ', $log->category) }}</td>
                            <td>
                                @php
                                    $badge = match ($log->status) {
                                        'sent' => 'badge-status-active',
                                        'simulated' => 'bg-info-subtle text-info-emphasis',
                                        'failed' => 'badge-status-suspended',
                                        default => 'badge-status-pending',
                                    };
                                @endphp
                                <span class="badge {{ $badge }} rounded-pill px-3 py-2">{{ ucfirst($log->status) }}</span>
                                @if ($log->provider_message_id)
                                    <div class="text-secondary small mt-1" title="{{ __('Beem request ID — look this up in the Beem dashboard\'s SMS report to see real delivery status (their \'sent\' just means they accepted it).') }}">{{ $log->provider_message_id }}</div>
                                @endif
                                @if ($log->status === 'failed' && $log->error)
                                    <div class="text-danger small mt-1">{{ \Illuminate\Support\Str::limit($log->error, 60) }}</div>
                                @endif
                            </td>
                            <td class="text-secondary small">{{ $log->sent_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $logs])
        @endif
    </div>

    @can('communications.manage_templates')
        <div class="modal fade" id="newTemplateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.communications.templates.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Template') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('Fee Balance Reminder') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Category') }}</label>
                                <select name="category" class="form-select" required>
                                    <option value="general">{{ __('General') }}</option>
                                    <option value="fee_reminder">{{ __('Fee reminder') }}</option>
                                    <option value="attendance">{{ __('Attendance') }}</option>
                                    <option value="exam_result">{{ __('Exam result') }}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Language') }}</label>
                                <select name="locale" class="form-select">
                                    @foreach (config('app.supported_locales') as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Message body') }}</label>
                                <textarea name="body" class="form-control" rows="4" maxlength="1000" required placeholder="{{ __('Dear parent, ...') }}"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create Template') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @foreach ($systemTemplates as $t)
            <div class="modal fade" id="editSystemTemplate{{ $t->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.communications.templates.update', $t) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __($events[$t->event]['name']) }} — {{ config('app.supported_locales')[$t->locale] ?? $t->locale }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label">{{ __('Message body') }}</label>
                                <textarea name="body" class="form-control system-template-body" rows="6" maxlength="1000" required>{{ $t->body }}</textarea>
                                <div class="form-text mb-3">{{ __('SMS is billed per 160 characters. Click a placeholder to insert it where the cursor is.') }}</div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($events[$t->event]['tokens'] as $token)
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 insert-token" data-token="{{ '{'.$token.'}' }}" title="{{ __($tokenHelp[$token] ?? $token) }}">{{ '{'.$token.'}' }}</button>
                                    @endforeach
                                </div>
                                @if (! empty($requiredTokens[$t->event]))
                                    <div class="form-text mt-2">{{ __('Must keep:') }} {{ collect($requiredTokens[$t->event])->map(fn ($x) => '{'.$x.'}')->implode(', ') }}</div>
                                @endif
                            </div>
                            <div class="modal-footer justify-content-between">
                                <button type="submit" form="resetSystemTemplate{{ $t->id }}" class="btn btn-outline-secondary" onclick="return confirm('{{ __('Go back to the default wording?') }}');">{{ __('Reset to default') }}</button>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                                </div>
                            </div>
                    </form>
                </div>
                {{-- Not inside .modal-content: a second <form> there would stop it being modal-content's
                     sole flex child, breaking the scrollable layout (see the fix applied portal-wide for
                     the same reason). A button elsewhere can still submit this one via its `form` attribute. --}}
                <form id="resetSystemTemplate{{ $t->id }}" method="POST" action="{{ route('school-admin.communications.templates.reset', $t) }}">@csrf</form>
            </div>
        @endforeach

        @foreach ($templates as $template)
            <div class="modal fade" id="editTemplateModal{{ $template->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.communications.templates.update', $template) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">{{ __('Edit Template') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $template->name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Category') }}</label>
                                    <select name="category" class="form-select" required>
                                        <option value="general" @selected($template->category === 'general')>{{ __('General') }}</option>
                                        <option value="fee_reminder" @selected($template->category === 'fee_reminder')>{{ __('Fee reminder') }}</option>
                                        <option value="attendance" @selected($template->category === 'attendance')>{{ __('Attendance') }}</option>
                                        <option value="exam_result" @selected($template->category === 'exam_result')>{{ __('Exam result') }}</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Language') }}</label>
                                    <select name="locale" class="form-select">
                                        @foreach (config('app.supported_locales') as $code => $label)
                                            <option value="{{ $code }}" @selected($template->locale === $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Message body') }}</label>
                                    <textarea name="body" class="form-control" rows="4" maxlength="1000" required>{{ $template->body }}</textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach
    @endcan

    <script>
        document.querySelectorAll('.insert-token').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var box = btn.closest('.modal-content').querySelector('.system-template-body');
                var start = box.selectionStart, end = box.selectionEnd;
                box.value = box.value.slice(0, start) + btn.dataset.token + box.value.slice(end);
                box.focus();
                box.selectionStart = box.selectionEnd = start + btn.dataset.token.length;
            });
        });
    </script>

    <script>
        (function () {
            var studentRadio = document.getElementById('recipientStudent');
            var classRadio = document.getElementById('recipientClass');
            var studentField = document.getElementById('studentField');
            var classField = document.getElementById('classField');
            if (!studentRadio) return;

            function toggleRecipientFields() {
                var isClass = classRadio.checked;
                studentField.classList.toggle('d-none', isClass);
                classField.classList.toggle('d-none', !isClass);
            }
            studentRadio.addEventListener('change', toggleRecipientFields);
            classRadio.addEventListener('change', toggleRecipientFields);

            var templatePicker = document.getElementById('templatePicker');
            var messageBody = document.getElementById('messageBody');
            var categoryField = document.getElementById('categoryField');
            if (templatePicker) {
                templatePicker.addEventListener('change', function () {
                    if (this.value) {
                        messageBody.value = this.value;
                        categoryField.value = this.selectedOptions[0].dataset.category || 'general';
                        messageBody.dispatchEvent(new Event('input'));
                    }
                });
            }

            var charCount = document.getElementById('charCount');
            messageBody.addEventListener('input', function () {
                charCount.textContent = messageBody.value.length;
            });
        })();
    </script>
@endsection
