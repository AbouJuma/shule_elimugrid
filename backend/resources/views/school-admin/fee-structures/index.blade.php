@extends('layouts.portal')

@section('title', 'Fee Structures — ElimuGrid')
@section('page-title', __('Fee Structures'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'fee-structures'])
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
            <form method="GET">
                <select name="school_class_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 180px;">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('school_class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </form>
            @can('fees.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newStructureModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Fee Structure') }}
                </button>
            @endcan
        </div>

        @if ($structures->isEmpty())
            <div class="card-body text-center text-secondary py-5">{{ __('No fee structures match these filters.') }}</div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th style="width: 1%;">#</th><th>{{ __('Name') }}</th><th>{{ __('Class') }}</th><th>{{ __('Academic Year') }}</th><th>{{ __('Total') }}</th><th class="text-end">{{ __('Actions') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($structures as $structure)
                        <tr>
                            <td class="text-secondary">{{ $structures->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $structure->name }}</td>
                            <td>{{ $structure->schoolClass->name }}</td>
                            <td class="text-secondary small">{{ $structure->academicYear->name }}</td>
                            <td>{{ number_format($structure->items->sum('amount'), 2) }}</td>
                            <td class="text-end">
                                @can('fees.invoice')
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#generateModal{{ $structure->id }}">
                                        {{ __('Generate Invoices') }}
                                    </button>
                                @endcan
                                @can('fees.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editStructureModal{{ $structure->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.fee-structures.destroy', $structure) }}" class="d-inline"
                                          onsubmit="return confirm('Delete fee structure \'{{ $structure->name }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $structures])
        @endif
    </div>

    @can('fees.invoice')
        @foreach ($structures as $structure)
            <div class="modal fade" id="generateModal{{ $structure->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.fee-structures.invoices.store', $structure) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5">Generate Invoices — {{ $structure->name }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-secondary small">
                                    Generates an invoice for every active student in {{ $structure->schoolClass->name }}.
                                    Students who already have an invoice from this fee structure are skipped automatically.
                                </p>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Due date') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="date" name="due_date" class="form-control">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Generate') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach
    @endcan

    @can('fees.manage')
        @foreach ($structures as $structure)
            <div class="modal fade" id="editStructureModal{{ $structure->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.fee-structures.update', $structure) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">Edit — {{ $structure->name }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $structure->name }}" required>
                                </div>
                                <p class="text-secondary small">{{ __('Class, academic year, and term can\'t be changed after creation — create a new fee structure instead if those need to change.') }}</p>

                                <label class="form-label d-block">{{ __('Fee Items') }}</label>
                                <div id="feeItemRows{{ $structure->id }}">
                                    @foreach ($structure->items as $i => $item)
                                        <div class="row g-2 mb-2 fee-item-row">
                                            <div class="col-7"><input type="text" name="items[{{ $i }}][name]" class="form-control form-control-sm" value="{{ $item->name }}" required></div>
                                            <div class="col-4"><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][amount]" class="form-control form-control-sm" value="{{ $item->amount }}" required></div>
                                            <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-fee-item-row" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm add-fee-item-row" data-target="feeItemRows{{ $structure->id }}" data-start="{{ $structure->items->count() }}">
                                    <i class="bi bi-plus-lg"></i> {{ __('Add Item') }}
                                </button>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        <script>
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.add-fee-item-row');
                if (!btn) return;
                const container = document.getElementById(btn.dataset.target);
                let index = parseInt(btn.dataset.start, 10);
                const row = document.createElement('div');
                row.className = 'row g-2 mb-2 fee-item-row';
                row.innerHTML =
                    '<div class="col-7"><input type="text" name="items[' + index + '][name]" class="form-control form-control-sm" placeholder="Item name" required></div>' +
                    '<div class="col-4"><input type="number" step="0.01" min="0.01" name="items[' + index + '][amount]" class="form-control form-control-sm" placeholder="Amount" required></div>' +
                    '<div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-fee-item-row" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>';
                container.appendChild(row);
                btn.dataset.start = index + 1;
            });

            {{-- One row must always remain per fee structure — hide (rather than disable) the remove
                 button on the last one so there is never nothing left to submit. --}}
            function refreshFeeItemRemoveButtons(container) {
                const rows = container.querySelectorAll('.fee-item-row');
                rows.forEach((row, i) => {
                    const removeBtn = row.querySelector('.remove-fee-item-row');
                    if (removeBtn) removeBtn.closest('.col-1').classList.toggle('d-none', rows.length <= 1);
                });
            }

            document.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.remove-fee-item-row');
                if (!removeBtn) return;
                const row = removeBtn.closest('.fee-item-row');
                const container = row.parentElement;
                row.remove();
                refreshFeeItemRemoveButtons(container);
            });

            document.querySelectorAll('[id^="feeItemRows"]').forEach(refreshFeeItemRemoveButtons);
            document.addEventListener('shown.bs.modal', (e) => {
                const container = e.target.querySelector('[id^="feeItemRows"]');
                if (container) refreshFeeItemRemoveButtons(container);
            });
        </script>

        <div class="modal fade" id="newStructureModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('school-admin.fee-structures.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Fee Structure') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('Form 1 Term 1 Fees') }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Class') }}</label>
                                    <select name="school_class_id" class="form-select" required>
                                        <option value="">{{ __('Select class…') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Academic Year') }}</label>
                                    <select name="academic_year_id" class="form-select" required>
                                        <option value="">{{ __('Select year…') }}</option>
                                        @foreach ($academicYears as $year)
                                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <label class="form-label d-block">{{ __('Fee Items') }}</label>
                            <div id="feeItemRows">
                                <div class="row g-2 mb-2 fee-item-row">
                                    <div class="col-7"><input type="text" name="items[0][name]" class="form-control form-control-sm" placeholder="{{ __('Tuition') }}" required></div>
                                    <div class="col-4"><input type="number" step="0.01" min="0.01" name="items[0][amount]" class="form-control form-control-sm" placeholder="{{ __('Amount') }}" required></div>
                                    <div class="col-1 d-none"><button type="button" class="btn btn-outline-danger btn-sm remove-fee-item-row" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                                </div>
                            </div>
                            <button type="button" id="addFeeItemRow" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-plus-lg"></i> {{ __('Add Item') }}
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Create Fee Structure') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        <script>
            (function () {
                var container = document.getElementById('feeItemRows');
                var addButton = document.getElementById('addFeeItemRow');
                if (!container || !addButton) return;

                var index = 1;
                addButton.addEventListener('click', function () {
                    var row = document.createElement('div');
                    row.className = 'row g-2 mb-2 fee-item-row';
                    row.innerHTML =
                        '<div class="col-7"><input type="text" name="items[' + index + '][name]" class="form-control form-control-sm" placeholder="Item name" required></div>' +
                        '<div class="col-4"><input type="number" step="0.01" min="0.01" name="items[' + index + '][amount]" class="form-control form-control-sm" placeholder="Amount" required></div>' +
                        '<div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-fee-item-row" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>';
                    container.appendChild(row);
                    index++;
                    refreshFeeItemRemoveButtons(container);
                });

                document.getElementById('newStructureModal').addEventListener('shown.bs.modal', function () {
                    refreshFeeItemRemoveButtons(container);
                });
            })();
        </script>
    @endcan
@endsection
