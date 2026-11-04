<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\StoreStreamRequest;
use App\Http\Requests\Academics\UpdateStreamRequest;
use App\Http\Resources\StreamResource;
use App\Models\Stream;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('classes.view');

        return StreamResource::collection(
            Stream::query()
                ->when($request->integer('school_class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreStreamRequest $request, TenantContext $tenant)
    {
        $stream = Stream::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return response()->json(['data' => new StreamResource($stream)], 201);
    }

    public function update(UpdateStreamRequest $request, Stream $stream)
    {
        $stream->update($request->validated());

        return new StreamResource($stream->fresh());
    }

    public function destroy(Stream $stream)
    {
        $this->authorize('classes.manage');

        if ($stream->students()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['stream' => [__('This stream still has students enrolled — move or archive them first.')]]);
        }

        $stream->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }}
