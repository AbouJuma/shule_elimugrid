<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\BorrowBookRequest;
use App\Http\Requests\Library\StoreBookRequest;
use App\Http\Requests\Library\UpdateBookRequest;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Student;
use App\Services\LibraryService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** The library catalogue and loans for the mobile app. */
class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('library.view');

        $books = Book::query()
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('author', 'like', '%'.$request->string('search').'%')))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $books->getCollection()->map(fn ($b) => $this->book($b))->values(),
            'meta' => ['current_page' => $books->currentPage(), 'last_page' => $books->lastPage(), 'total' => $books->total()],
        ]);
    }

    public function store(StoreBookRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();
        $book = Book::create(['school_id' => $tenant->id(), ...$data, 'available_copies' => $data['total_copies']]);

        return response()->json(['data' => $this->book($book->fresh())], 201);
    }

    public function update(UpdateBookRequest $request, int $book, LibraryService $library)
    {
        $model = Book::query()->findOrFail($book);
        $library->updateCatalog($model, $request->validated());

        return response()->json(['data' => $this->book($model->fresh())]);
    }

    public function destroy(int $book)
    {
        $this->authorize('library.manage');
        $model = Book::query()->findOrFail($book);

        if ($model->available_copies < $model->total_copies) {
            $msg = __('This book has copies currently on loan — it can\'t be deleted.');

            return response()->json(['message' => $msg, 'errors' => ['book' => [$msg]]], 422);
        }

        $model->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function borrow(BorrowBookRequest $request, int $book, LibraryService $library)
    {
        $model = Book::query()->findOrFail($book);
        $student = Student::query()->findOrFail($request->validated()['student_id']);

        $loan = $library->borrow($model, $student, Carbon::parse($request->validated()['due_date']), $request->user());

        return response()->json(['data' => $this->loan($loan->load(['book', 'student']))], 201);
    }

    public function loans(Request $request)
    {
        $this->authorize('library.view');

        $loans = BookLoan::query()->with(['book', 'student'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('borrowed_date')->latest('id')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $loans->getCollection()->map(fn ($l) => $this->loan($l))->values(),
            'meta' => ['current_page' => $loans->currentPage(), 'last_page' => $loans->lastPage(), 'total' => $loans->total()],
        ]);
    }

    public function returnLoan(Request $request, int $loan, LibraryService $library)
    {
        $this->authorize('library.manage');
        $model = BookLoan::query()->findOrFail($loan);
        $library->returnBook($model, $request->user());

        return response()->json(['data' => $this->loan($model->fresh(['book', 'student']))]);
    }

    public function loseLoan(Request $request, int $loan, LibraryService $library)
    {
        $this->authorize('library.manage');
        $model = BookLoan::query()->findOrFail($loan);
        $library->returnBook($model, $request->user(), lost: true);

        return response()->json(['data' => $this->loan($model->fresh(['book', 'student']))]);
    }

    protected function book(Book $b): array
    {
        return [
            'id' => $b->id, 'title' => $b->title, 'author' => $b->author, 'isbn' => $b->isbn, 'category' => $b->category,
            'total_copies' => $b->total_copies, 'available_copies' => $b->available_copies,
        ];
    }

    protected function loan(BookLoan $l): array
    {
        return [
            'id' => $l->id,
            'book' => $l->book?->title,
            'student' => $l->student?->fullName(),
            'borrowed_date' => $l->borrowed_date?->toDateString(),
            'due_date' => $l->due_date?->toDateString(),
            'returned_date' => $l->returned_date?->toDateString(),
            'status' => $l->status,
            'fine_amount' => $l->fine_amount !== null ? (string) $l->fine_amount : null,
        ];
    }
}
