import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/providers.dart';
import '../../../shared/utils/currency.dart';
import '../../../shared/widgets/pdf_viewer_screen.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../learning/presentation/learning_screens.dart' show LearningHubTab;
import '../../timetable/presentation/timetable_screen.dart' show TimetableWeekView;
import '../../transport/presentation/widgets/trip_map.dart';
import '../../transport/presentation/widgets/trip_widgets.dart';
import '../domain/parent_models.dart';
import 'parent_providers.dart';

String _fmt(num? v) => v == null ? '—' : (v % 1 == 0 ? v.toInt().toString() : v.toStringAsFixed(1));

/// A parent's children. Tapping one opens the bus, fees and attendance.
class MyChildrenScreen extends ConsumerStatefulWidget {
  const MyChildrenScreen({super.key});

  @override
  ConsumerState<MyChildrenScreen> createState() => _MyChildrenScreenState();
}

class _MyChildrenScreenState extends ConsumerState<MyChildrenScreen> {
  bool _openedOwnRecord = false;

  @override
  Widget build(BuildContext context) {
    final isStudent = ref.watch(authControllerProvider).value?.user?.hasRole('Student') ?? false;
    final async = ref.watch(myChildrenProvider);

    // A student's own login has exactly one "child" — themselves. Skip the
    // list and open their record directly; there's nothing else to pick from.
    if (isStudent && async is AsyncData<List<ChildSummary>> && async.value.length == 1) {
      final onlyChild = async.value.first;

      if (!_openedOwnRecord) {
        _openedOwnRecord = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => ChildScreen(child: onlyChild)));
        });
      }

      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(title: Text(context.tr(isStudent ? 'My results' : 'My children'))),
      body: switch (async) {
        AsyncData(:final value) when value.isEmpty => Center(child: Text(context.tr('No children are linked to your account yet.'), style: AppTypography.bodyMd)),
        AsyncData(:final value) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(myChildrenProvider),
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.md),
            itemCount: value.length,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
            itemBuilder: (context, i) => _ChildCard(child: value[i]),
          ),
        ),
        AsyncError(:final error) => Center(child: Padding(padding: const EdgeInsets.all(AppSpacing.lg), child: Text('$error', textAlign: TextAlign.center))),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _ChildCard extends StatelessWidget {
  const _ChildCard({required this.child});

  final ChildSummary child;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ChildScreen(child: child))),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              CircleAvatar(
                radius: 24,
                backgroundColor: AppColors.navy100,
                child: Text(child.name.isNotEmpty ? child.name[0].toUpperCase() : '?', style: AppTypography.headingMd.copyWith(color: AppColors.navy700)),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(child.name, style: AppTypography.headingSm),
                    if (child.classLabel.isNotEmpty) Text(child.classLabel, style: AppTypography.bodyMd),
                    if (child.route != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Row(
                          children: [
                            const Icon(Icons.directions_bus_rounded, size: 14, color: AppColors.green700),
                            const SizedBox(width: 4),
                            Flexible(child: Text([child.route!, ?child.stop].join(' · '), style: AppTypography.bodySm, overflow: TextOverflow.ellipsis)),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppColors.textSecondary),
            ],
          ),
        ),
      ),
    );
  }
}

class ChildScreen extends StatelessWidget {
  const ChildScreen({super.key, required this.child});

  final ChildSummary child;

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 6,
      child: Scaffold(
        appBar: AppBar(
          title: Text(child.name),
          bottom: TabBar(
            isScrollable: true,
            tabs: [
              Tab(icon: const Icon(Icons.directions_bus_rounded), text: context.tr('Bus')),
              Tab(icon: const Icon(Icons.payments_rounded), text: context.tr('Fees')),
              Tab(icon: const Icon(Icons.checklist_rounded), text: context.tr('Attendance')),
              Tab(icon: const Icon(Icons.emoji_events_rounded), text: context.tr('Results')),
              Tab(icon: const Icon(Icons.calendar_month_rounded), text: context.tr('Timetable')),
              Tab(icon: const Icon(Icons.play_circle_outline_rounded), text: context.tr('Learning')),
            ],
          ),
        ),
        body: TabBarView(children: [
          _BusTab(childId: child.id),
          _FeesTab(childId: child.id),
          _AttendanceTab(childId: child.id),
          _ResultsTab(childId: child.id),
          _TimetableTab(childId: child.id),
          LearningHubTab(childId: child.id),
        ]),
      ),
    );
  }
}

// ---- Bus ---------------------------------------------------------------------------------------------------------------------

class _BusTab extends ConsumerWidget {
  const _BusTab({required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(childBusProvider(childId))) {
      AsyncData(:final value) when !value.assigned => _Message(icon: Icons.directions_bus_outlined, text: context.tr('This child does not use the school bus.')),
      AsyncData(:final value) when !value.running => _BusIdle(bus: value),
      AsyncData(:final value) => _BusLive(bus: value),
      AsyncError(:final error) => _Message(icon: Icons.error_outline, text: '$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _BusIdle extends StatelessWidget {
  const _BusIdle({required this.bus});

  final ChildBus bus;

  @override
  Widget build(BuildContext context) {
    final pickup = bus.pickupTime?.substring(0, 5);
    final dropoff = bus.dropoffTime?.substring(0, 5);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [
                  const Icon(Icons.directions_bus_outlined, color: AppColors.textSecondary),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(child: Text(context.tr('The bus is not on a trip right now.'), style: AppTypography.headingSm)),
                ]),
                const SizedBox(height: AppSpacing.xs),
                Text(context.tr('This screen updates by itself when the driver starts the trip.'), style: AppTypography.bodySm),
                const Divider(height: AppSpacing.lg),
                if (bus.route != null) Text('${context.tr('Route')}: ${bus.route}', style: AppTypography.bodyMd),
                if (bus.stopName != null) Text('${context.tr('Stop')}: ${bus.stopName}', style: AppTypography.bodyMd),
                if (pickup != null) Text('${context.tr('Morning pick-up')}: $pickup', style: AppTypography.bodyMd),
                if (dropoff != null) Text('${context.tr('Afternoon drop-off')}: $dropoff', style: AppTypography.bodyMd),
                if (bus.vehicle != null) Text('${context.tr('Bus')}: ${bus.vehicle}', style: AppTypography.bodyMd),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _BusLive extends StatelessWidget {
  const _BusLive({required this.bus});

  final ChildBus bus;

  String _headline(BuildContext context) {
    final mine = bus.mine;
    final stop = bus.stopName ?? '';

    if (mine == null) return context.tr('The bus is on its way.');
    if (mine.passed) {
      return context.tr('The bus was at {stop} at {time}.', {'stop': stop, 'time': mine.arrivedAt != null ? formatClock(mine.arrivedAt!) : ''});
    }
    if (mine.etaMinutes == null) return context.tr('The bus is on its way to {stop}.', {'stop': stop});
    if (mine.stopsAway == 0) return context.tr('The bus is next to reach {stop} — about {n} min.', {'stop': stop, 'n': '${mine.etaMinutes}'});

    final k = mine.stopsAway ?? 0;

    return context.tr(k == 1 ? 'The bus reaches {stop} in about {n} min (1 stop before it).' : 'The bus reaches {stop} in about {n} min ({k} stops before it).', {'stop': stop, 'n': '${mine.etaMinutes}', 'k': '$k'});
  }

  @override
  Widget build(BuildContext context) {
    final trip = bus.trip!;
    final seen = trip.position?.recordedAt;
    final stale = seen != null && DateTime.now().difference(seen).inSeconds > 120;
    final passed = bus.mine?.passed ?? false;

    return Column(
      children: [
        Container(
          width: double.infinity,
          color: passed ? AppColors.charcoal : AppColors.navy800,
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(_headline(context), style: AppTypography.headingSm.copyWith(color: AppColors.white)),
              const SizedBox(height: 2),
              Text(
                [
                  '${trip.registrationNumber} · ${directionLabel(context, trip)}',
                  '${trip.visited}/${trip.total} ${context.tr('stops')}',
                ].join(' · '),
                style: AppTypography.bodySm.copyWith(color: AppColors.textOnDark),
              ),
              Text(
                seen == null
                    ? context.tr('No position received yet')
                    : '${context.tr('Last seen')} ${agoShort(seen)} ${context.tr('ago')}${stale ? ' — ${context.tr('No update for a while')}' : ''}',
                style: AppTypography.bodySm.copyWith(color: stale ? AppColors.gold500 : AppColors.textOnDark),
              ),
            ],
          ),
        ),
        LinearProgressIndicator(value: trip.progress, minHeight: 4, color: AppColors.green600, backgroundColor: AppColors.navy100),
        Expanded(flex: 5, child: TripMap(stops: trip.stops, trail: trip.trail, position: trip.position, highlightStopId: bus.stopId)),
        Expanded(flex: 3, child: TripStopsList(trip: trip)),
      ],
    );
  }
}

// ---- Fees --------------------------------------------------------------------------------------------------------------------

class _FeesTab extends ConsumerWidget {
  const _FeesTab({required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(childFeesProvider(childId))) {
      AsyncData(:final value) => RefreshIndicator(
        onRefresh: () async => ref.invalidate(childFeesProvider(childId)),
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  children: [
                    _Figure(label: context.tr('Billed'), value: '${value.currency} ${formatMoney(value.total)}'),
                    _Figure(label: context.tr('Paid'), value: '${value.currency} ${formatMoney(value.paid)}', colour: AppColors.green700),
                    _Figure(label: context.tr('Balance'), value: '${value.currency} ${formatMoney(value.balance)}', colour: value.balance > 0 ? AppColors.error600 : AppColors.green700),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            if (value.invoices.isEmpty) Padding(padding: const EdgeInsets.all(AppSpacing.lg), child: Center(child: Text(context.tr('No invoices yet.'), style: AppTypography.bodyMd))),
            for (final i in value.invoices)
              Card(
                margin: const EdgeInsets.only(bottom: AppSpacing.sm),
                child: ListTile(
                  title: Text(i.number, style: AppTypography.bodyLg),
                  subtitle: Text(
                    [
                      '${context.tr('Balance')}: ${value.currency} ${formatMoney(i.balance)}',
                      if (i.controlNumber != null) '${context.tr('Control number')}: ${i.controlNumber}',
                      if (i.dueDate != null) '${context.tr('Due')}: ${i.dueDate}',
                    ].join('\n'),
                    style: AppTypography.bodySm,
                  ),
                  isThreeLine: true,
                  trailing: Text(context.tr(i.status), style: AppTypography.label.copyWith(color: i.balance > 0 ? AppColors.gold700 : AppColors.green700)),
                ),
              ),
          ],
        ),
      ),
      AsyncError(:final error) => _Message(icon: Icons.error_outline, text: '$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _Figure extends StatelessWidget {
  const _Figure({required this.label, required this.value, this.colour});

  final String label;
  final String value;
  final Color? colour;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(label, style: AppTypography.bodySm),
          const SizedBox(height: 2),
          Text(value, textAlign: TextAlign.center, style: AppTypography.bodyMd.copyWith(fontWeight: FontWeight.w700, color: colour)),
        ],
      ),
    );
  }
}

// ---- Attendance --------------------------------------------------------------------------------------------------------------

class _AttendanceTab extends ConsumerWidget {
  const _AttendanceTab({required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(childAttendanceProvider(childId))) {
      AsyncData(:final value) when value.days == 0 => _Message(icon: Icons.checklist_rounded, text: context.tr('No attendance recorded in the last 30 days.')),
      AsyncData(:final value) => RefreshIndicator(
        onRefresh: () async => ref.invalidate(childAttendanceProvider(childId)),
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Column(
                  children: [
                    Text('${value.rate ?? 0}%', style: AppTypography.displayLg.copyWith(color: (value.rate ?? 0) >= 85 ? AppColors.green700 : AppColors.gold700)),
                    Text(context.tr('present in the last 30 days'), style: AppTypography.bodySm),
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        _Figure(label: context.tr('Present'), value: '${value.present}', colour: AppColors.green700),
                        _Figure(label: context.tr('Late'), value: '${value.late}', colour: AppColors.gold700),
                        _Figure(label: context.tr('Absent'), value: '${value.absent}', colour: AppColors.error600),
                        _Figure(label: context.tr('Excused'), value: '${value.excused}'),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            for (final r in value.recent)
              ListTile(
                dense: true,
                title: Text(r.date),
                trailing: Text(context.tr(r.status[0].toUpperCase() + r.status.substring(1)), style: AppTypography.label.copyWith(color: _statusColour(r.status))),
              ),
          ],
        ),
      ),
      AsyncError(:final error) => _Message(icon: Icons.error_outline, text: '$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }

  Color _statusColour(String s) => switch (s) {
    'present' => AppColors.green700,
    'late' => AppColors.gold700,
    'absent' => AppColors.error600,
    _ => AppColors.textSecondary,
  };
}

// ---- Results -------------------------------------------------------------------------------------------------------------

class _ResultsTab extends ConsumerStatefulWidget {
  const _ResultsTab({required this.childId});

  final String childId;

  @override
  ConsumerState<_ResultsTab> createState() => _ResultsTabState();
}

class _ResultsTabState extends ConsumerState<_ResultsTab> {
  int? _termId;

  @override
  Widget build(BuildContext context) {
    return switch (ref.watch(childResultsProvider((widget.childId, _termId)))) {
      AsyncData(:final value) when !value.published && value.terms.isEmpty =>
        _Message(icon: Icons.emoji_events_outlined, text: context.tr('No results have been published yet.')),
      AsyncData(:final value) => _ResultsBody(results: value, onTermChanged: (id) => setState(() => _termId = id)),
      AsyncError(:final error) => _Message(icon: Icons.error_outline, text: '$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _ResultsBody extends ConsumerWidget {
  const _ResultsBody({required this.results, required this.onTermChanged});

  final ChildResults results;
  final void Function(int termId) onTermChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r = results.result;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        if (results.terms.length > 1)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.md),
            child: DropdownButtonFormField<int>(
              initialValue: results.term?.id,
              isExpanded: true,
              decoration: InputDecoration(labelText: context.tr('Term'), isDense: true),
              items: results.terms.map((t) => DropdownMenuItem(value: t.id, child: Text(t.label, overflow: TextOverflow.ellipsis))).toList(),
              onChanged: (v) {
                if (v != null) onTermChanged(v);
              },
            ),
          ),
        if (!results.published || r == null)
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.lg),
            child: Center(child: Text(context.tr('No published results for this term yet.'), style: AppTypography.bodyMd)),
          )
        else ...[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                children: [
                  _Figure(label: context.tr('Position'), value: r.position == null ? '—' : '${r.position}/${results.classSize ?? '—'}'),
                  _Figure(label: context.tr('Average'), value: r.average == null ? '—' : '${_fmt(r.average)}%'),
                  _Figure(label: context.tr('Grade'), value: r.grade ?? '—', colour: AppColors.gold700),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Card(
            child: Column(children: [
              for (final l in r.lines)
                ListTile(
                  dense: true,
                  title: Text(l.subject),
                  subtitle: l.classAverage != null ? Text('${context.tr('Class average')} ${_fmt(l.classAverage)}%', style: AppTypography.bodySm) : null,
                  trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                    Text(l.percent == null ? '—' : '${_fmt(l.percent)}%', style: AppTypography.bodyMd),
                    const SizedBox(width: AppSpacing.md),
                    Text(l.grade ?? '—', style: AppTypography.headingSm),
                  ]),
                ),
            ]),
          ),
          if (r.pdfUrl.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            FilledButton.icon(
              icon: const Icon(Icons.picture_as_pdf_outlined),
              label: Text(context.tr('View report card (PDF)')),
              onPressed: () => openPdf(context, title: 'Report card', loader: () => ref.read(apiClientProvider).getBytes(r.pdfUrl)),
            ),
          ],
        ],
      ],
    );
  }
}

// ---- Timetable -----------------------------------------------------------------------------------------------------------

class _TimetableTab extends ConsumerWidget {
  const _TimetableTab({required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(childTimetableProvider(childId))) {
      AsyncData(:final value) when value.lessons.isEmpty =>
        _Message(icon: Icons.calendar_month_outlined, text: context.tr('No lessons scheduled yet.')),
      AsyncData(:final value) => TimetableWeekView(data: value, showClass: false),
      AsyncError(:final error) => _Message(icon: Icons.error_outline, text: '$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: AppColors.textSecondary),
            const SizedBox(height: AppSpacing.sm),
            Text(text, style: AppTypography.bodyMd, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
