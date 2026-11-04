@extends('layouts.portal')

@section('title', 'Scan Attendance — ElimuGrid')
@section('page-title', __('Scan Attendance'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'attendance-scan'])
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body text-center">
            <i class="bi bi-upc-scan display-4 text-secondary d-block mb-3"></i>
            <p class="text-secondary mb-3">
                {{ __('Scan a student or staff ID (a USB/Bluetooth barcode scanner works here like a keyboard), or type the admission/employee number and press Enter.') }}
            </p>
            <div class="mx-auto" style="max-width: 420px;">
                <input type="text" id="scanInput" class="form-control form-control-lg text-center" placeholder="{{ __('Scan or type a code…') }}" autofocus autocomplete="off">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">{{ __('Today') }}</div>
        <ul class="list-group list-group-flush" id="scanResults"></ul>
        <div class="card-body text-center text-secondary small" id="scanEmpty">{{ __('Nothing scanned yet.') }}</div>
    </div>

    <script>
        (function () {
            const input = document.getElementById('scanInput');
            const results = document.getElementById('scanResults');
            const empty = document.getElementById('scanEmpty');
            const csrfToken = '{{ csrf_token() }}';

            input.addEventListener('keydown', async function (e) {
                if (e.key !== 'Enter') return;
                const code = input.value.trim();
                input.value = '';
                if (!code) return;

                try {
                    const response = await fetch('{{ route('school-admin.attendance-scan.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ code: code }),
                    });
                    const body = await response.json();
                    addResult(response.ok, body);
                } catch (err) {
                    addResult(false, { message: '{{ __('Network error.') }}' });
                }

                input.focus();
            });

            function addResult(ok, body) {
                empty.classList.add('d-none');
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';

                if (ok) {
                    const d = body.data;
                    const badge = d.type === 'staff' ? 'text-bg-info' : 'text-bg-success';
                    li.innerHTML =
                        '<span><i class="bi bi-check-circle-fill text-success me-2"></i><strong>' + d.name + '</strong>' +
                        (d.sub_label ? ' <span class="text-secondary small">(' + d.sub_label + ')</span>' : '') +
                        (d.already_marked ? ' <span class="text-warning small">{{ __('already marked today') }}</span>' : '') +
                        '</span><span class="badge ' + badge + ' rounded-pill">' + d.type + '</span>';
                } else {
                    li.innerHTML = '<span><i class="bi bi-x-circle-fill text-danger me-2"></i>' + (body.message || '{{ __('Not recognised.') }}') + '</span>';
                }

                results.prepend(li);
            }
        })();
    </script>
@endsection
