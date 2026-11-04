{{-- Expects: $permissionGroups (grouped Permission collection), $selected (array of permission names), $fieldPrefix (unique id prefix per modal) --}}
@foreach ($permissionGroups as $group => $permissions)
    <div class="mb-3">
        <div class="text-secondary text-uppercase small fw-semibold mb-1">{{ $group }}</div>
        <div class="row row-cols-1 row-cols-md-2 g-1">
            @foreach ($permissions as $permission)
                <div class="col">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]"
                               value="{{ $permission->name }}" id="{{ $fieldPrefix }}-{{ $permission->id }}"
                               {{ in_array($permission->name, $selected, true) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="{{ $fieldPrefix }}-{{ $permission->id }}">
                            {{ $permission->name }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endforeach
