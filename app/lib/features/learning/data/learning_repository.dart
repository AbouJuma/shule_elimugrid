import '../../../core/network/api_client.dart';
import '../domain/learning_models.dart';

class LearningRepository {
  LearningRepository(this._client);

  final ApiClient _client;

  Future<List<LessonItem>> lessons(String childId) async {
    final body = await _client.get('/my/children/$childId/lessons');
    return (body['data'] as List<dynamic>).map((l) => LessonItem.fromJson(l as Map<String, dynamic>)).toList();
  }

  Future<List<HomeworkItem>> homework(String childId) async {
    final body = await _client.get('/my/children/$childId/homework');
    return (body['data'] as List<dynamic>).map((h) => HomeworkItem.fromJson(h as Map<String, dynamic>)).toList();
  }

  Future<List<OnlineExamSummary>> onlineExams(String childId) async {
    final body = await _client.get('/my/children/$childId/online-exams');
    return (body['data'] as List<dynamic>).map((e) => OnlineExamSummary.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<ExamAttemptStart> startOnlineExam(String childId, String examId) async {
    final body = await _client.post('/my/children/$childId/online-exams/$examId/start');
    return ExamAttemptStart.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<ExamSubmitResult> submitOnlineExam(
    String childId,
    String examId, {
    required int attemptId,
    required Map<int, int?> answers,
  }) async {
    final body = await _client.post('/my/children/$childId/online-exams/$examId/submit', data: {
      'attempt_id': attemptId,
      'answers': [
        for (final entry in answers.entries) {'question_id': entry.key, 'choice_id': entry.value},
      ],
    });
    return ExamSubmitResult.fromJson(body['data'] as Map<String, dynamic>);
  }
}
