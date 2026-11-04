import '../../../core/network/api_client.dart';
import '../domain/exam_models.dart';

class ExamRepository {
  ExamRepository(this._client);

  final ApiClient _client;

  Future<List<ExamModel>> list() async {
    final body = await _client.get('/exams');
    return (body['data'] as List<dynamic>).map((e) => ExamModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<ExamModel> show(int examId) async {
    final body = await _client.get('/exams/$examId');
    return ExamModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<MarksRegister> marksRegister({required int examId, required int subjectId, required int classId}) async {
    final body = await _client.get(
      '/exams/$examId/subjects/$subjectId/marks',
      query: {'school_class_id': classId},
    );
    return MarksRegister.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<void> recordMarks({
    required int examId,
    required int subjectId,
    required int classId,
    required List<MarkEntry> entries,
  }) async {
    await _client.post('/exams/$examId/subjects/$subjectId/marks', data: {
      'school_class_id': classId,
      'records': entries
          .map((e) => {'student_id': e.studentId, 'marks_obtained': e.marksObtained})
          .toList(),
    });
  }
}
