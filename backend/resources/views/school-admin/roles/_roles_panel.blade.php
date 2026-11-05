<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="GET" class="d-flex" style="max-width: 280px;">
            <input type="search" name="search" class="form-control form-control-sm"
                   placeholder="{{ __('Search roles…') }}" value="{{ request('search') }}">
        </form>
        @can('roles.manage')
            <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#newRoleModal">
                <i class="bi bi-plus-lg me-1"></i> {{ __('New Role') }}
            </button>
        @endcan
    </div>

    @if ($roles->isEmpty())
        <div class="card-body text-center text-secondary py-5">
            @if (request('search'))
                No roles match "{{ request('search') }}".
            @else
                No roles yet.
            @endif
        </div>
    @else
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 1%;">#</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Permissions') }}</th>
                    <th>{{ __('Users') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td class="text-secondary">{{ $roles->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">
                            {{ $role->name }}
                            @if (in_array($role->name, config('roles.default_school_roles'), true))
                                <i class="bi bi-star-fill text-gold small ms-1" title="{{ __('Default role') }}"></i>
                            @endif
                        </td>
                        <td class="small" style="max-width: 420px;">
                            @php $perms = $role->permissions->pluck('name'); @endphp
                            @forelse ($perms->take(6) as $perm)
                                <span class="badge bg-light text-secondary border fw-normal">{{ $perm }}</span>
                            @empty
                                <span class="text-secondary">{{ __('None yet') }}</span>
                            @endforelse
                            @if ($perms->count() > 6)
                                <span class="badge bg-secondary-subtle text-secondary fw-normal" title="{{ $perms->slice(6)->join(', ') }}">+{{ $perms->count() - 6 }} more</span>
                            @endif
                        </td>
                        <td>{{ $userCounts[$role->id] ?? 0 }}</td>
                        <td class="text-end">
                            @can('roles.manage')
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#editRoleModal{{ $role->id }}">{{ __('Edit') }}</button>
                                <form method="POST" action="{{ route('school-admin.roles.destroy', $role) }}" class="d-inline"
                                      onsubmit="return confirm('Delete the {{ $role->name }} role?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @include('school-admin.partials.table-pagination', ['paginator' => $roles])
    @endif
</div>

@can('roles.manage')
    <div class="modal fade" id="newRoleModal" tabindex="-1" aria-labelledby="newRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('school-admin.roles.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="newRoleModalLabel">{{ __('New Role') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="form-label">{{ __('Role name') }}</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <label class="form-label d-block mb-2">{{ __('Permissions') }}</label>
                        @include('school-admin.roles._permission_fields', [
                            'permissionGroups' => $permissionGroups,
                            'selected' => [],
                            'fieldPrefix' => 'new-role',
                        ])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-gold">{{ __('Create Role') }}</button>
                    </div>
                </form>
        </div>
    </div>

    @foreach ($roles as $role)
        <div class="modal fade" id="editRoleModal{{ $role->id }}" tabindex="-1" aria-labelledby="editRoleModalLabel{{ $role->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('school-admin.roles.update', $role) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="editRoleModalLabel{{ $role->id }}">Edit "{{ $role->name }}"</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-4">
                                <label class="form-label">{{ __('Role name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ $role->name }}" required>
                            </div>
                            <label class="form-label d-block mb-2">{{ __('Permissions') }}</label>
                            @include('school-admin.roles._permission_fields', [
                                'permissionGroups' => $permissionGroups,
                                'selected' => $role->permissions->pluck('name')->all(),
                                'fieldPrefix' => 'edit-role-'.$role->id,
                            ])
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
