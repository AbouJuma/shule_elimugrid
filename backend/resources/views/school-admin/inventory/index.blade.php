@extends('layouts.portal')

@section('title', 'Inventory — ElimuGrid')
@section('page-title', __('Inventory'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'inventory'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->has('category'))
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->get('category') as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif
    @if ($errors->has('item'))
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->get('item') as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'items' ? 'active' : '' }}" href="{{ route('school-admin.inventory.index') }}">{{ __('Items') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'categories' ? 'active' : '' }}" href="{{ route('school-admin.inventory.index', ['tab' => 'categories']) }}">{{ __('Categories') }} <span class="badge bg-light text-secondary border">{{ $categories->count() }}</span></a></li>
    </ul>

    @if ($tab === 'categories')
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>{{ __('Item categories') }}</span>
                @can('inventory.manage')
                    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newCategoryModal"><i class="bi bi-plus-lg me-1"></i> {{ __('New Category') }}</button>
                @endcan
            </div>
            @if ($categories->isEmpty())
                <div class="card-body text-center text-secondary py-5">{{ __('No categories yet — add one, then choose it when creating items.') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>{{ __('Category') }}</th><th>{{ __('Description') }}</th><th>{{ __('Items') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="fw-semibold">{{ $category->name }}</td>
                                    <td class="text-secondary small">{{ $category->description ?? '—' }}</td>
                                    <td><a href="{{ route('school-admin.inventory.index', ['category_id' => $category->id]) }}">{{ $category->items_count }}</a></td>
                                    <td class="text-end">
                                        @can('inventory.manage')
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $category->id }}"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" action="{{ route('school-admin.inventory.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this category?') }}');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 190px;">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $filterCategory)
                        <option value="{{ $filterCategory->id }}" @selected(request('category_id') == $filterCategory->id)>{{ $filterCategory->name }}</option>
                    @endforeach
                </select>
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search item…') }}" value="{{ request('search') }}" style="max-width: 200px;">
                <div class="form-check form-check-inline ms-2">
                    <input type="checkbox" name="low_stock" value="1" class="form-check-input" id="lowStock"
                           onchange="this.form.submit()" @checked(request()->boolean('low_stock'))>
                    <label class="form-check-label small" for="lowStock">{{ __('Low stock only') }}</label>
                </div>
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            </form>
            @can('inventory.manage')
                <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newItemModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('New Item') }}
                </button>
            @endcan
        </div>

        @if ($items->isEmpty())
            <div class="card-body text-center text-secondary py-5">
                <i class="bi bi-box-seam display-6 d-block mb-2"></i>
                {{ __('No inventory items match these filters.') }}
            </div>
        @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 1%;">#</th>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('On Hand') }}</th>
                        <th>{{ __('Reorder Level') }}</th>
                        <th>{{ __('Unit Cost') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td class="text-secondary">{{ $items->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td class="text-secondary small">{{ $item->categoryName() ?? '—' }}</td>
                            <td>
                                {{ number_format($item->quantity_on_hand, 2) }} {{ $item->unit }}
                                @if ($item->isBelowReorderLevel())
                                    <span class="badge badge-status-pending rounded-pill px-2 py-1 ms-1">{{ __('Low') }}</span>
                                @endif
                            </td>
                            <td class="text-secondary small">{{ number_format($item->reorder_level, 2) }}</td>
                            <td class="text-secondary small">{{ $item->unit_cost ? number_format($item->unit_cost, 2) : '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('school-admin.inventory.movements', $item) }}" class="btn btn-outline-secondary btn-sm">
                                    {{ __('Movements') }}
                                </a>
                                @can('inventory.manage')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('school-admin.inventory.destroy', $item) }}" class="d-inline"
                                          onsubmit="return confirm('Delete item \'{{ $item->name }}\'?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('school-admin.partials.table-pagination', ['paginator' => $items])
        @endif
    </div>
    @endif

    @can('inventory.manage')
        <div class="modal fade" id="newItemModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('school-admin.inventory.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('New Inventory Item') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" placeholder="{{ __('A4 Paper Ream') }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Category') }}</label>
                                    <select name="inventory_category_id" class="form-select">
                                        <option value="">{{ __('No category') }}</option>
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Unit') }}</label>
                                    <input type="text" name="unit" class="form-control" value="pcs" required>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ __('Reorder Level') }}</label>
                                    <input type="number" step="0.01" min="0" name="reorder_level" class="form-control" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Unit Cost') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                    <input type="number" step="0.01" min="0" name="unit_cost" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Opening Quantity') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                <input type="number" step="0.01" min="0" name="opening_quantity" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-gold">{{ __('Add Item') }}</button>
                        </div>
                    </form>
            </div>
        </div>

        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('newItemModal')).show();
                });
            </script>
        @endif

        @foreach ($items as $item)
            <div class="modal fade" id="editItemModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.inventory.update', $item) }}">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5">Edit — {{ $item->name }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $item->name }}" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Category') }}</label>
                                        <select name="inventory_category_id" class="form-select">
                                            <option value="">{{ __('No category') }}</option>
                                            @foreach ($categories as $cat)
                                                <option value="{{ $cat->id }}" @selected($item->inventory_category_id === $cat->id)>{{ $cat->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Unit') }}</label>
                                        <input type="text" name="unit" class="form-control" value="{{ $item->unit }}" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Reorder Level') }}</label>
                                        <input type="number" step="0.01" min="0" name="reorder_level" class="form-control" value="{{ $item->reorder_level }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ __('Unit Cost') }} <span class="text-secondary">{{ __('(optional)') }}</span></label>
                                        <input type="number" step="0.01" min="0" name="unit_cost" class="form-control" value="{{ $item->unit_cost }}">
                                    </div>
                                </div>
                                <p class="text-secondary small mt-3 mb-0">{{ __('Quantity on hand isn\'t editable here — record a stock movement instead, so the ledger keeps explaining every unit.') }}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                </div>
            </div>
        @endforeach

        <div class="modal fade" id="newCategoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('school-admin.inventory.categories.store') }}">
                    @csrf
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('New Category') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">{{ __('Name') }}</label><input type="text" name="name" class="form-control" placeholder="{{ __('Stationery') }}" required></div>
                        <div class="mb-1"><label class="form-label">{{ __('Description') }} <span class="text-secondary">({{ __('optional') }})</span></label><input type="text" name="description" class="form-control"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-gold">{{ __('Add Category') }}</button></div>
                </form></div>
        </div>

        @foreach ($categories as $category)
            <div class="modal fade" id="editCategoryModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('school-admin.inventory.categories.update', $category) }}">
                        @csrf @method('PUT')
                        <div class="modal-header"><h2 class="modal-title h5">{{ __('Edit Category') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">{{ __('Name') }}</label><input type="text" name="name" class="form-control" value="{{ $category->name }}" required></div>
                            <div class="mb-1"><label class="form-label">{{ __('Description') }}</label><input type="text" name="description" class="form-control" value="{{ $category->description }}"></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-gold">{{ __('Save Changes') }}</button></div>
                    </form></div>
            </div>
        @endforeach
    @endcan
@endsection
