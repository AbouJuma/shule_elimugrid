import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/platform_models.dart';
import 'platform_providers.dart';

/// What a Super Admin sees instead of the module grid — they belong to no
/// school, so nothing there applies to them. Mirrors the portal's Super
/// Admin dashboard (same numbers, same shape) so the two feel like one product.
class PlatformDashboardBody extends ConsumerWidget {
  const PlatformDashboardBody({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;
    final asyncDashboard = ref.watch(platformDashboardProvider);
    final asyncSchools = ref.watch(platformSchoolsProvider);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(platformDashboardProvider);
        ref.invalidate(platformSchoolsProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 26,
                    backgroundColor: AppColors.navy100,
                    child: Text(user?.name.isNotEmpty == true ? user!.name[0].toUpperCase() : '?', style: AppTypography.headingMd.copyWith(color: AppColors.navy700)),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(user?.name ?? '', style: AppTypography.headingSm),
                        Text(context.tr('Platform Super Admin'), style: AppTypography.bodySm),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          switch (asyncDashboard) {
            AsyncData(:final value) => _Stats(stats: value.stats),
            AsyncError(:final error) => _ErrorCard(error: error),
            _ => const Padding(padding: EdgeInsets.all(AppSpacing.lg), child: Center(child: CircularProgressIndicator())),
          },
          if (asyncDashboard.value case final d?) ...[
            const SizedBox(height: AppSpacing.md),
            _OnboardedChart(points: d.onboardedByMonth),
            if (d.schoolsByPackage.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _PackageChart(points: d.schoolsByPackage),
            ],
          ],
          const SizedBox(height: AppSpacing.lg),
          Text(context.tr('Schools'), style: AppTypography.label),
          const SizedBox(height: AppSpacing.sm),
          switch (asyncSchools) {
            AsyncData(:final value) when value.isEmpty => Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Center(child: Text(context.tr('No schools yet.'), style: AppTypography.bodyMd)),
            ),
            AsyncData(:final value) => Column(children: [for (final s in value) _SchoolCard(school: s)]),
            AsyncError(:final error) => _ErrorCard(error: error),
            _ => const Padding(padding: EdgeInsets.all(AppSpacing.lg), child: Center(child: CircularProgressIndicator())),
          },
        ],
      ),
    );
  }
}

class _Stats extends StatelessWidget {
  const _Stats({required this.stats});

  final PlatformStats stats;

  @override
  Widget build(BuildContext context) {
    final cards = [
      (context.tr('Total Schools'), '${stats.totalSchools}', Icons.apartment_rounded, AppColors.navy700, AppColors.navy100),
      (context.tr('Active'), '${stats.activeSchools}', Icons.check_circle_rounded, AppColors.green700, AppColors.green100),
      (context.tr('Pending'), '${stats.pendingSchools}', Icons.hourglass_bottom_rounded, AppColors.warning600, AppColors.warning100),
      (context.tr('Suspended'), '${stats.suspendedSchools}', Icons.block_rounded, AppColors.error600, AppColors.error100),
      (context.tr('Active Students'), '${stats.totalStudents}', Icons.people_alt_rounded, AppColors.gold700, AppColors.gold100),
      (context.tr('Staff Accounts'), '${stats.totalStaff}', Icons.badge_rounded, AppColors.info600, AppColors.info100),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: cards.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: AppSpacing.sm, crossAxisSpacing: AppSpacing.sm, mainAxisExtent: 92),
      itemBuilder: (context, i) {
        final (label, value, icon, fg, bg) = cards[i];

        return Card(
          margin: EdgeInsets.zero,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.sm),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(AppSpacing.radiusSm)),
                  child: Icon(icon, color: fg, size: 20),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(label, style: AppTypography.bodySm, maxLines: 1, overflow: TextOverflow.ellipsis),
                      Text(value, style: AppTypography.headingMd),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _OnboardedChart extends StatelessWidget {
  const _OnboardedChart({required this.points});

  final List<ChartPoint> points;

  @override
  Widget build(BuildContext context) {
    final maxValue = points.map((p) => p.value).fold(1, (a, b) => b > a ? b : a);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(context.tr('Schools onboarded — last 6 months'), style: AppTypography.label),
            const SizedBox(height: AppSpacing.md),
            SizedBox(
              height: 110,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  for (final p in points)
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text('${p.value}', textAlign: TextAlign.center, style: AppTypography.bodySm),
                            const SizedBox(height: 2),
                            Container(
                              height: (p.value / maxValue * 70).clamp(3, 70).toDouble(),
                              decoration: BoxDecoration(color: AppColors.gold600, borderRadius: const BorderRadius.vertical(top: Radius.circular(4))),
                            ),
                            const SizedBox(height: 4),
                            Text(p.label, textAlign: TextAlign.center, style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary)),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PackageChart extends StatelessWidget {
  const _PackageChart({required this.points});

  final List<ChartPoint> points;

  @override
  Widget build(BuildContext context) {
    final maxValue = points.map((p) => p.value).fold(1, (a, b) => b > a ? b : a);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(context.tr('Schools by package'), style: AppTypography.label),
            const SizedBox(height: AppSpacing.md),
            for (final p in points)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                child: Row(
                  children: [
                    SizedBox(width: 90, child: Text(p.label, style: AppTypography.bodySm, overflow: TextOverflow.ellipsis)),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(6),
                        child: LinearProgressIndicator(
                          value: p.value / maxValue,
                          minHeight: 14,
                          backgroundColor: AppColors.surface,
                          color: AppColors.navy700,
                        ),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    SizedBox(width: 24, child: Text('${p.value}', textAlign: TextAlign.right, style: AppTypography.bodySm)),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _SchoolCard extends ConsumerStatefulWidget {
  const _SchoolCard({required this.school});

  final SchoolSummary school;

  @override
  ConsumerState<_SchoolCard> createState() => _SchoolCardState();
}

class _SchoolCardState extends ConsumerState<_SchoolCard> {
  bool _busy = false;

  Color get _statusColour => switch (widget.school.status) {
    'active' => AppColors.green700,
    'suspended' => AppColors.error600,
    _ => AppColors.warning600,
  };

  Future<void> _toggle() async {
    setState(() => _busy = true);
    try {
      final repo = ref.read(platformRepositoryProvider);
      if (widget.school.isSuspended) {
        await repo.activate(widget.school.id);
      } else {
        await repo.suspend(widget.school.id);
      }
      ref.invalidate(platformSchoolsProvider);
      ref.invalidate(platformDashboardProvider);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e is ApiException ? e.message : '$e')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.school;

    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(s.name, style: AppTypography.bodyLg),
                  Text(
                    [if (s.packageName != null) s.packageName!, if (s.studentsCount != null) '${s.studentsCount} ${context.tr('students')}'].join(' · '),
                    style: AppTypography.bodySm,
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(color: _statusColour.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(AppSpacing.radiusPill)),
                    child: Text(context.tr(s.status[0].toUpperCase() + s.status.substring(1)), style: AppTypography.label.copyWith(color: _statusColour)),
                  ),
                ],
              ),
            ),
            if (_busy)
              const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
            else
              OutlinedButton(
                onPressed: _toggle,
                style: OutlinedButton.styleFrom(foregroundColor: s.isSuspended ? AppColors.green700 : AppColors.error600),
                child: Text(context.tr(s.isSuspended ? 'Activate' : 'Suspend')),
              ),
          ],
        ),
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.error});

  final Object error;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Center(child: Text('$error', textAlign: TextAlign.center)),
    );
  }
}
