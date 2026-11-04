{{-- Expects: $paginator (a LengthAwarePaginator) --}}
@if ($paginator->total() > 0)
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="text-secondary small">
                Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
            </span>
            <select
                class="form-select form-select-sm page-size-select"
                style="width: auto;"
                data-page-param="{{ $paginator->getPageName() }}"
                aria-label="Rows per page"
            >
                @php($resolvedPerPage = \App\Support\Pagination\PageSize::resolve(request()))
                @foreach (\App\Support\Pagination\PageSize::ALLOWED as $size)
                    <option value="{{ $size }}" @selected($resolvedPerPage === $size)>{{ $size }} / page</option>
                @endforeach
            </select>
        </div>
        {{ $paginator->onEachSide(1)->links() }}
    </div>
@endif
