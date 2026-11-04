<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Library\BorrowBookRequest;
use App\Http\Requests\Library\StoreBookRequest;
use App\Http\Requests\Library\UpdateBookRequest;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Student;
use App\Services\LibraryService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class LibraryController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('library.view');

        $books = Book::query()
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('author', 'like', '%'.$request->string('search').'%')
            ))
            ->orderBy('title')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.library.index', [
            'books' => $books,
            'students' => Student::query()->where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }

    public function store(StoreBookRequest $request, TenantContext $tenant)
    {
        $data = $request->validated();

        Book::create([
            'school_id' => $tenant->id(),
            ...$data,
            'available_copies' => $data['total_copies'],
        ]);

        return redirect()->route('school-admin.library.index')->with('status', __('":p1" added to the catalog.', ['p1' => $data['title']]));
    }

    public function update(UpdateBookRequest $request, Book $book, LibraryService $library)
    {
        $library->updateCatalog($book, $request->validated());

        return redirect()->route('school-admin.library.index')->with('status', __('":p1" updated.', ['p1' => $book->title]));
    }

    public function destroy(Book $book)
    {
        $this->authorize('library.manage');

        if ($book->available_copies < $book->total_copies) {
            return back()->withErrors(['book' => 'This book has copies currently on loan — it can\'t be deleted.']);
        }

        $book->delete();

        return redirect()->route('school-admin.library.index')->with('status', __('":p1" deleted.', ['p1' => $book->title]));
    }

    public function borrow(BorrowBookRequest $request, Book $book, LibraryService $library)
    {
        $student = Student::findOrFail($request->validated()['student_id']);

        $library->borrow($book, $student, \Illuminate\Support\Carbon::parse($request->validated()['due_date']), $request->user());

        return redirect()->route('school-admin.library.index')->with('status', __('":p1" issued to :p2.', ['p1' => $book->title, 'p2' => $student->fullName()]));
    }

    public function loans(Request $request)
    {
        $this->authorize('library.view');

        $loans = BookLoan::query()
            ->with(['book', 'student'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('borrowed_date')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.library.loans', ['loans' => $loans]);
    }

    public function returnLoan(BookLoan $loan, LibraryService $library)
    {
        $this->authorize('library.manage');

        $library->returnBook($loan, request()->user());

        return redirect()->route('school-admin.library.loans')->with('status', 'Book marked returned.');
    }

    public function loseLoan(BookLoan $loan, LibraryService $library)
    {
        $this->authorize('library.manage');

        $library->returnBook($loan, request()->user(), lost: true);

        return redirect()->route('school-admin.library.loans')->with('status', 'Book marked lost.');
    }
}
