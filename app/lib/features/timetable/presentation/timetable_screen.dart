import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/timetable_models.dart';
import 'timetable_controller.dart';

const _days = <int, String>{1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun'};

/// The same day-at-a-time timetable view, embeddable without its own AppBar
/// (e.g. as one tab of a child's screen).
class TimetableWeekView extends StatelessWidget {
  const TimetableWeekView({super.key, required this.data, this.showClass = true});

  final TimetableData data;
  final bool showClass;

  @override
  Widget build(BuildContext context) => _WeekView(data: data, showClass: showClass);
}

class TimetableScreen extends ConsumerWidget {
  const TimetableScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;
    final canSeeClasses = user?.can('timetable.view') ?? false;

    if (!canSeeClasses) {
      return Scaffold(
        appBar: AppBar(title: Text(context.tr('Timetable'))),
        body: const _MyTimetable(),
      );
    }

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.tr('Timetable')),
          bottom: TabBar(tabs: [Tab(text: context.tr('My timetable')), Tab(text: context.tr('Class timetable'))]),
        ),
        body: const TabBarView(children: [_MyTimetable(), _ClassTimetable()]),
      ),
    );
  }
}

class _MyTimetable extends ConsumerWidget {
  const _MyTimetable();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(myTimetableProvider)) {
      AsyncData(:final value) when value.lessons.isEmpty => Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Text(
            context.tr('No lessons are assigned to you yet.'),
            textAlign: TextAlign.center,
            style: AppTypography.bodyMd,
          ),
        ),
      ),
      AsyncData(:final value) => _WeekView(data: value, showClass: true),
      AsyncError(:final error) => Center(child: Text('${context.tr('Could not load the timetable.')}\n$error', textAlign: TextAlign.center)),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _ClassTimetable extends ConsumerStatefulWidget {
  const _ClassTimetable();

  @override
  ConsumerState<_ClassTimetable> createState() => _ClassTimetableState();
}

class _ClassTimetableState extends ConsumerState<_ClassTimetable> {
  int? _classId;

  @override
  Widget build(BuildContext context) {
    final classes = ref.watch(timetableClassesProvider);

    return switch (classes) {
      AsyncData(:final value) when value.isEmpty => Center(child: Text(context.tr('No classes have been set up yet.'))),
      AsyncData(:final value) => Builder(builder: (context) {
        final id = _classId ?? value.first.id;

        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: DropdownButtonFormField<int>(
                initialValue: id,
                isExpanded: true,
                decoration: InputDecoration(labelText: context.tr('Class'), isDense: true),
                items: value.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                onChanged: (v) => setState(() => _classId = v),
              ),
            ),
            Expanded(
              child: switch (ref.watch(classTimetableProvider(id))) {
                AsyncData(:final value) when value.lessons.isEmpty =>
                  Center(child: Text(context.tr('No lessons scheduled for this class yet.'))),
                AsyncData(:final value) => _WeekView(data: value, showClass: false),
                AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
                _ => const Center(child: CircularProgressIndicator()),
              },
            ),
          ],
        );
      }),
      AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

/// One day at a time (phones are too narrow for a week grid): pick the day,
/// see each period with its lesson, breaks shown muted.
class _WeekView extends StatefulWidget {
  const _WeekView({required this.data, required this.showClass});

  final TimetableData data;
  final bool showClass;

  @override
  State<_WeekView> createState() => _WeekViewState();
}

class _WeekViewState extends State<_WeekView> {
  late int _day = widget.data.days.contains(DateTime.now().weekday) ? DateTime.now().weekday : (widget.data.days.isEmpty ? 1 : widget.data.days.first);

  @override
  Widget build(BuildContext context) {
    final data = widget.data;
    final activeDays = {...data.days, ...data.lessons.map((l) => l.dayOfWeek)}.where(_days.containsKey).toList()..sort();

    return Column(
      children: [
        SizedBox(
          height: 52,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
            children: [
              for (final d in activeDays)
                Padding(
                  padding: const EdgeInsets.only(right: AppSpacing.sm),
                  child: ChoiceChip(
                    label: Text(context.tr(_days[d]!)),
                    selected: _day == d,
                    onSelected: (_) => setState(() => _day = d),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.md),
            itemCount: data.periods.length,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
            itemBuilder: (context, index) {
              final period = data.periods[index];
              final lesson = period.isBreak ? null : data.lessonAt(_day, period.id);

              return _PeriodTile(period: period, lesson: lesson, showClass: widget.showClass);
            },
          ),
        ),
      ],
    );
  }
}

class _PeriodTile extends StatelessWidget {
  const _PeriodTile({required this.period, required this.lesson, required this.showClass});

  final TimetablePeriod period;
  final TimetableLesson? lesson;
  final bool showClass;

  @override
  Widget build(BuildContext context) {
    final time = '${period.startTime} – ${period.endTime}';

    if (period.isBreak) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(AppSpacing.radiusSm)),
        child: Row(children: [
          const Icon(Icons.free_breakfast_outlined, size: 18, color: AppColors.textSecondary),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: Text('${period.name} · $time', style: AppTypography.bodySm)),
        ]),
      );
    }

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Row(
          children: [
            SizedBox(
              width: 92,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(period.name, style: AppTypography.bodyMd.copyWith(fontWeight: FontWeight.w600)),
                Text(time, style: AppTypography.bodySm),
              ]),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: lesson == null
                  ? Text(context.tr('Free period'), style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary))
                  : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(lesson!.subject, style: AppTypography.headingSm),
                      Text(
                        [
                          if (showClass) '${lesson!.className}${lesson!.stream != null ? ' ${lesson!.stream}' : ''}',
                          if (!showClass && lesson!.teacher != null) lesson!.teacher!,
                          if (lesson!.room != null) '${context.tr('Room')} ${lesson!.room}',
                        ].join(' · '),
                        style: AppTypography.bodySm,
                      ),
                    ]),
            ),
          ],
        ),
      ),
    );
  }
}
