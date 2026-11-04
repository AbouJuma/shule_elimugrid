<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place available_copies is ever written — same row-locked
 * pattern as StockService, for the same reason: concurrent borrow/return
 * actions must never race and leave the count wrong.
 *
 * Fine calculation is a fixed rate, not yet a per-school setting — a real
 * policy choice worth calling out explicitly, same as the Reports
 * module's fixed 50%-of-max-marks pass threshold.
 */
class LibraryService
{
    protected const FINE_PER_DAY_OVERDUE = 100;

    public function borrow(Book $book, Student $student, Carbon $dueDate, User $recordedBy): BookLoan
    {
        return DB::transaction(function () use ($book, $student, $dueDate, $recordedBy) {
            $locked = Book::query()->lockForUpdate()->findOrFail($book->id);

            if ($locked->available_copies <= 0) {
                throw ValidationException::withMessages([
                    'book_id' => ["\"{$locked->title}\" has no available copies right now."],
                ]);
            }

            $locked->update(['available_copies' => $locked->available_copies - 1]);

            return BookLoan::create([
                'school_id' => $book->school_id,
                'book_id' => $book->id,
                'student_id' => $student->id,
                'borrowed_date' => now(),
                'due_date' => $dueDate,
                'status' => 'borrowed',
                'recorded_by' => $recordedBy->id,
            ]);
        });
    }

    /**
     * Updates the catalog fields plus total_copies — if total_copies
     * changes, available_copies shifts by the same delta under the same
     * row lock, rather than being set directly, so a copy that's
     * currently out on loan is never silently "returned" by an edit.
     * Shrinking total_copies below what's currently borrowed is rejected.
     */
    public function updateCatalog(Book $book, array $data): Book
    {
        return DB::transaction(function () use ($book, $data) {
            $locked = Book::query()->lockForUpdate()->findOrFail($book->id);

            $delta = $data['total_copies'] - $locked->total_copies;
            $newAvailable = $locked->available_copies + $delta;

            if ($newAvailable < 0) {
                throw ValidationException::withMessages([
                    'total_copies' => ['Can\'t reduce total copies below the number currently on loan.'],
                ]);
            }

            $locked->update([
                'title' => $data['title'],
                'author' => $data['author'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'category' => $data['category'] ?? null,
                'total_copies' => $data['total_copies'],
                'available_copies' => $newAvailable,
            ]);

            return $locked;
        });
    }

    /**
     * $lost=true means the copy is gone for good — the loan is closed
     * out but the copy is deliberately NOT returned to available_copies
     * (it doesn't exist anymore).
     */
    public function returnBook(BookLoan $loan, User $recordedBy, bool $lost = false): void
    {
        if ($loan->status !== 'borrowed') {
            throw ValidationException::withMessages([
                'status' => ['This loan has already been closed out.'],
            ]);
        }

        DB::transaction(function () use ($loan, $lost) {
            $fine = null;

            if (! $lost) {
                $locked = Book::query()->lockForUpdate()->findOrFail($loan->book_id);
                $locked->update(['available_copies' => $locked->available_copies + 1]);

                $today = now()->startOfDay();
                $due = $loan->due_date->copy()->startOfDay();
                // diffInDays() is signed in this Carbon version (negative
                // when $due is before $today), not absolute by default —
                // abs() it explicitly rather than relying on sign
                // convention that's easy to get backwards.
                $daysOverdue = $today->gt($due) ? abs($today->diffInDays($due)) : 0;
                $fine = $daysOverdue > 0 ? $daysOverdue * self::FINE_PER_DAY_OVERDUE : null;
            }

            $loan->update([
                'status' => $lost ? 'lost' : 'returned',
                'returned_date' => $lost ? null : now(),
                'fine_amount' => $fine,
            ]);
        });
    }
}
