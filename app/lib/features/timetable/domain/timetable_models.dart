/// Mirrors App\Http\Controllers\Api\V1\TimetableController's payload.
class TimetablePeriod {
  const TimetablePeriod({
    required this.id,
    required this.name,
    required this.startTime,
    required this.endTime,
    required this.isBreak,
  });

  final int id;
  final String name;
  final String startTime;
  final String endTime;
  final bool isBreak;

  factory TimetablePeriod.fromJson(Map<String, dynamic> json) => TimetablePeriod(
    id: json['id'] as int,
    name: json['name'] as String,
    startTime: json['start_time'] as String,
    endTime: json['end_time'] as String,
    isBreak: json['is_break'] as bool? ?? false,
  );
}

class TimetableLesson {
  const TimetableLesson({
    required this.dayOfWeek,
    required this.periodId,
    required this.subject,
    this.teacher,
    required this.className,
    this.stream,
    this.room,
  });

  final int dayOfWeek;
  final int periodId;
  final String subject;
  final String? teacher;
  final String className;
  final String? stream;
  final String? room;

  factory TimetableLesson.fromJson(Map<String, dynamic> json) => TimetableLesson(
    dayOfWeek: json['day_of_week'] as int,
    periodId: json['period_id'] as int,
    subject: json['subject'] as String,
    teacher: json['teacher'] as String?,
    className: json['class'] as String,
    stream: json['stream'] as String?,
    room: json['room'] as String?,
  );
}

class TimetableData {
  const TimetableData({required this.periods, required this.lessons, required this.days});

  final List<TimetablePeriod> periods;
  final List<TimetableLesson> lessons;

  /// Weekdays the school runs (1 = Monday … 7 = Sunday), as configured on the portal.
  final List<int> days;

  TimetableLesson? lessonAt(int day, int periodId) {
    for (final l in lessons) {
      if (l.dayOfWeek == day && l.periodId == periodId) return l;
    }
    return null;
  }

  factory TimetableData.fromJson(Map<String, dynamic> json) => TimetableData(
    periods: (json['periods'] as List<dynamic>).map((p) => TimetablePeriod.fromJson(p as Map<String, dynamic>)).toList(),
    lessons: (json['entries'] as List<dynamic>).map((e) => TimetableLesson.fromJson(e as Map<String, dynamic>)).toList(),
    days: (json['days'] as List<dynamic>? ?? const [1, 2, 3, 4, 5]).map((d) => (d as num).toInt()).toList(),
  );
}
