import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/learning/presentation/learning_screens.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('a childs lessons, homework and online exams all render in their own tab', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/my/children/child-1/lessons': [
        {'id': 'lesson-1', 'title': 'Fractions Explained', 'description': null, 'subject': 'Mathematics', 'video_url': 'https://youtu.be/x', 'video_stream_url': null},
      ],
      '/my/children/child-1/homework': [
        {'id': 'hw-1', 'title': 'Long Division Worksheet', 'instructions': 'Do 1-10', 'due_date': '2026-03-01', 'subject': 'Mathematics', 'attachment_url': null},
      ],
      '/my/children/child-1/online-exams': [
        {
          'id': 'exam-1', 'title': 'Fractions Quiz', 'description': null, 'mode': 'graded', 'subject': 'Mathematics',
          'question_count': 2, 'time_limit_minutes': null, 'completed': false, 'in_progress': false, 'last_score': null,
        },
      ],
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: Scaffold(body: LearningHubTab(childId: 'child-1'))),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Fractions Explained'), findsOneWidget);

    await tester.tap(find.text('Homework'));
    await tester.pumpAndSettle();
    expect(find.text('Long Division Worksheet'), findsOneWidget);
    expect(find.textContaining('2026-03-01'), findsOneWidget);

    await tester.tap(find.text('Online Exams'));
    await tester.pumpAndSettle();
    expect(find.text('Fractions Quiz'), findsOneWidget);
    expect(find.text('Start'), findsOneWidget);
  });

  testWidgets('a completed graded exam shows its score and cannot be started again', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/my/children/child-1/lessons': <dynamic>[],
      '/my/children/child-1/homework': <dynamic>[],
      '/my/children/child-1/online-exams': [
        {
          'id': 'exam-1', 'title': 'Fractions Quiz', 'description': null, 'mode': 'graded', 'subject': 'Mathematics',
          'question_count': 2, 'time_limit_minutes': null, 'completed': true, 'in_progress': false,
          'last_score': {'score': 2, 'max_score': 3},
        },
      ],
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: Scaffold(body: LearningHubTab(childId: 'child-1'))),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Online Exams'));
    await tester.pumpAndSettle();

    expect(find.textContaining('2/3'), findsOneWidget);
    expect(find.text('Completed'), findsOneWidget);

    final button = tester.widget<ElevatedButton>(find.widgetWithText(ElevatedButton, 'Completed'));
    expect(button.onPressed, isNull);
  });

  testWidgets('taking an exam: start, answer, submit, and see the score', (tester) async {
    _tall(tester);
    final api = FakeApi();
    api.postResult = {
      'data': {
        'attempt_id': 42,
        'exam': {'id': 'exam-1', 'title': 'Fractions Quiz', 'time_limit_minutes': null},
        'questions': [
          {
            'id': 1, 'question_text': 'What is 1/2 + 1/4?',
            'choices': [
              {'id': 10, 'choice_text': '3/4'},
              {'id': 11, 'choice_text': '1/2'},
            ],
          },
        ],
      },
    };

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: ExamTakingScreen(childId: 'child-1', examId: 'exam-1', examTitle: 'Fractions Quiz')),
    ));
    await tester.pumpAndSettle();

    expect(find.textContaining('What is 1/2 + 1/4?'), findsOneWidget);

    await tester.tap(find.text('3/4'));
    await tester.pumpAndSettle();

    // Submit responds with the graded result.
    api.postResult = {
      'data': {
        'score': 1,
        'max_score': 1,
        'answers': [
          {'question_id': 1, 'choice_id': 10, 'is_correct': true, 'correct_choice_id': 10},
        ],
      },
    };

    await tester.tap(find.text('Submit'));
    await tester.pumpAndSettle();

    expect(find.text('1 / 1'), findsOneWidget);

    final submitCall = api.calls.firstWhere((c) => c.$2 == '/my/children/child-1/online-exams/exam-1/submit');
    expect(submitCall.$3?['attempt_id'], 42);
    expect(submitCall.$3?['answers'], [
      {'question_id': 1, 'choice_id': 10},
    ]);
  });
}
