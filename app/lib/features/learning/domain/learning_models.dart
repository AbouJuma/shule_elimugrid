/// Mirrors Api\V1\LearningController's JSON shapes.
class LessonItem {
  const LessonItem({
    required this.id,
    required this.title,
    required this.description,
    required this.subject,
    required this.videoUrl,
    required this.videoStreamUrl,
  });

  final String id;
  final String title;
  final String? description;
  final String? subject;
  // An external link (YouTube/Vimeo/…) — opened outside the app.
  final String? videoUrl;
  // A file the school uploaded, served from our own API (bearer-gated).
  final String? videoStreamUrl;

  factory LessonItem.fromJson(Map<String, dynamic> json) => LessonItem(
    id: json['id'] as String,
    title: json['title'] as String,
    description: json['description'] as String?,
    subject: json['subject'] as String?,
    videoUrl: json['video_url'] as String?,
    videoStreamUrl: json['video_stream_url'] as String?,
  );
}

class HomeworkItem {
  const HomeworkItem({
    required this.id,
    required this.title,
    required this.instructions,
    required this.dueDate,
    required this.subject,
    required this.attachmentUrl,
  });

  final String id;
  final String title;
  final String? instructions;
  final String? dueDate;
  final String? subject;
  final String? attachmentUrl;

  factory HomeworkItem.fromJson(Map<String, dynamic> json) => HomeworkItem(
    id: json['id'] as String,
    title: json['title'] as String,
    instructions: json['instructions'] as String?,
    dueDate: json['due_date'] as String?,
    subject: json['subject'] as String?,
    attachmentUrl: json['attachment_url'] as String?,
  );
}

class ExamScore {
  const ExamScore({required this.score, required this.maxScore});

  final int score;
  final int maxScore;

  factory ExamScore.fromJson(Map<String, dynamic> json) =>
      ExamScore(score: json['score'] as int, maxScore: json['max_score'] as int);
}

class OnlineExamSummary {
  const OnlineExamSummary({
    required this.id,
    required this.title,
    required this.description,
    required this.mode,
    required this.subject,
    required this.questionCount,
    required this.timeLimitMinutes,
    required this.completed,
    required this.inProgress,
    required this.lastScore,
  });

  final String id;
  final String title;
  final String? description;
  final String mode; // 'graded' | 'practice'
  final String? subject;
  final int questionCount;
  final int? timeLimitMinutes;
  // 'graded' only: a submitted attempt already exists, so it's done.
  final bool completed;
  final bool inProgress;
  final ExamScore? lastScore;

  bool get isGraded => mode == 'graded';

  factory OnlineExamSummary.fromJson(Map<String, dynamic> json) => OnlineExamSummary(
    id: json['id'] as String,
    title: json['title'] as String,
    description: json['description'] as String?,
    mode: json['mode'] as String,
    subject: json['subject'] as String?,
    questionCount: json['question_count'] as int? ?? 0,
    timeLimitMinutes: json['time_limit_minutes'] as int?,
    completed: json['completed'] as bool? ?? false,
    inProgress: json['in_progress'] as bool? ?? false,
    lastScore: json['last_score'] != null ? ExamScore.fromJson(json['last_score'] as Map<String, dynamic>) : null,
  );
}

class ExamChoiceOption {
  const ExamChoiceOption({required this.id, required this.text});

  final int id;
  final String text;

  factory ExamChoiceOption.fromJson(Map<String, dynamic> json) =>
      ExamChoiceOption(id: json['id'] as int, text: json['choice_text'] as String);
}

/// A question as sent while an exam is IN PROGRESS — deliberately carries
/// no notion of which choice is correct.
class ExamQuestionToAnswer {
  const ExamQuestionToAnswer({required this.id, required this.text, required this.choices});

  final int id;
  final String text;
  final List<ExamChoiceOption> choices;

  factory ExamQuestionToAnswer.fromJson(Map<String, dynamic> json) => ExamQuestionToAnswer(
    id: json['id'] as int,
    text: json['question_text'] as String,
    choices: (json['choices'] as List<dynamic>)
        .map((c) => ExamChoiceOption.fromJson(c as Map<String, dynamic>))
        .toList(),
  );
}

class ExamAttemptStart {
  const ExamAttemptStart({required this.attemptId, required this.examTitle, required this.timeLimitMinutes, required this.questions});

  final int attemptId;
  final String examTitle;
  final int? timeLimitMinutes;
  final List<ExamQuestionToAnswer> questions;

  factory ExamAttemptStart.fromJson(Map<String, dynamic> json) => ExamAttemptStart(
    attemptId: json['attempt_id'] as int,
    examTitle: (json['exam'] as Map<String, dynamic>)['title'] as String,
    timeLimitMinutes: (json['exam'] as Map<String, dynamic>)['time_limit_minutes'] as int?,
    questions: (json['questions'] as List<dynamic>)
        .map((q) => ExamQuestionToAnswer.fromJson(q as Map<String, dynamic>))
        .toList(),
  );
}

class ExamAnswerResult {
  const ExamAnswerResult({required this.questionId, required this.choiceId, required this.isCorrect, required this.correctChoiceId});

  final int questionId;
  final int? choiceId;
  final bool isCorrect;
  final int? correctChoiceId;

  factory ExamAnswerResult.fromJson(Map<String, dynamic> json) => ExamAnswerResult(
    questionId: json['question_id'] as int,
    choiceId: json['choice_id'] as int?,
    isCorrect: json['is_correct'] as bool,
    correctChoiceId: json['correct_choice_id'] as int?,
  );
}

class ExamSubmitResult {
  const ExamSubmitResult({required this.score, required this.maxScore, required this.answers});

  final int score;
  final int maxScore;
  final List<ExamAnswerResult> answers;

  factory ExamSubmitResult.fromJson(Map<String, dynamic> json) => ExamSubmitResult(
    score: json['score'] as int,
    maxScore: json['max_score'] as int,
    answers: (json['answers'] as List<dynamic>).map((a) => ExamAnswerResult.fromJson(a as Map<String, dynamic>)).toList(),
  );
}
