<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExamQuestion extends Model
{
    // No BelongsToTenant/school_id of its own — always reached through its
    // exam (which is tenant-scoped), same as ExamMark reaching its school
    // through exam_id for anything that needs it.
    protected $fillable = ['online_exam_id', 'question_text', 'points', 'sort_order'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(OnlineExam::class, 'online_exam_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(OnlineExamChoice::class)->orderBy('sort_order');
    }

    public function correctChoice(): ?OnlineExamChoice
    {
        return $this->choices->firstWhere('is_correct', true);
    }
}
