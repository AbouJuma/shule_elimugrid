<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamChoice extends Model
{
    protected $fillable = ['online_exam_question_id', 'choice_text', 'is_correct', 'sort_order'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(OnlineExamQuestion::class, 'online_exam_question_id');
    }
}
