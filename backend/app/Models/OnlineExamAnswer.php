<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamAnswer extends Model
{
    protected $fillable = ['online_exam_attempt_id', 'online_exam_question_id', 'online_exam_choice_id', 'is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OnlineExamAttempt::class, 'online_exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(OnlineExamQuestion::class, 'online_exam_question_id');
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(OnlineExamChoice::class, 'online_exam_choice_id');
    }
}
