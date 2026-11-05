@can('hr.view')
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">{{ __('Who holds which role') }}</span>
            <a href="{{ route('school-admin.staff.index') }}" class="small">{{ __('Full staff list') }} &rarr;</a>
        </div>

        @if ($staff->isEmpty())
            <div class="card-body text-center text-secondary py-4">{{ __('No staff accounts yet.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr>
                                <td class="fw-semibold">{{ $member->name }}</td>
                                <td class="text-secondary small">{{ $member->email }}</td>
                                <td class="text-secondary small">{{ $member->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td class="text-end">
                                    @can('hr.manage')
                                        @if ($member->id === auth()->id())
                                            <span class="text-secondary small">{{ __("You can't change your own role.") }}</span>
                                        @else
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#assignRoleModal{{ $member->id }}">
                                                {{ __('Change role') }}
                                            </button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @can('hr.manage')
        @foreach ($staff as $member)
            @continue($member->id === auth()->id())
            <div class="modal fade" id="assignRoleModal{{ $member->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('school-admin.staff.update', $member->staffProfile) }}">
                        @csrf
                        @method('PATCH')
                        {{-- UpdateStaffProfileRequest requires these two even when only the role is changing. --}}
                        <input type="hidden" name="employment_type" value="{{ $member->staffProfile->employment_type }}">
                        <input type="hidden" name="date_joined" value="{{ $member->staffProfile->date_joined?->toDateString() }}">
                        <div class="modal-header">
                            <h2 class="modal-title h5">{{ __('Change role') }} — {{ $member->name }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">{{ __('Role') }}</label>
                            <select name="role" class="form-select" required>
                                @foreach ($allRoles as $role)
                                    <option value="{{ $role->name }}" @selected($member->roles->pluck('name')->contains($role->name))>{{ $role->name }}</option>
                                @endforeach
                            </select>
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
@endcan
