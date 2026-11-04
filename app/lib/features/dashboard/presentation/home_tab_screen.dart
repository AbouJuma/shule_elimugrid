import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../platform/presentation/platform_dashboard_screen.dart';

class _ModuleTile {
  const _ModuleTile({
    required this.label,
    required this.icon,
    required this.color,
    required this.route,
  });

  final String label;
  final IconData icon;
  final Color color;
  final String route;
}

const _modules = [
  _ModuleTile(label: 'My children', icon: Icons.child_care_rounded, color: AppColors.green700, route: '/home/my-children'),
  _ModuleTile(label: 'Students', icon: Icons.people_alt_rounded, color: AppColors.navy700, route: '/home/students'),
  _ModuleTile(
    label: 'Attendance',
    icon: Icons.checklist_rounded,
    color: AppColors.gold700,
    route: '/home/attendance',
  ),
  _ModuleTile(
    label: 'Staff Attendance',
    icon: Icons.badge_rounded,
    color: AppColors.info600,
    route: '/home/staff-attendance',
  ),
  _ModuleTile(
    label: 'Scan Attendance',
    icon: Icons.qr_code_scanner_rounded,
    color: AppColors.green700,
    route: '/home/attendance-scan',
  ),
  _ModuleTile(
    label: 'Exams',
    icon: Icons.assignment_rounded,
    color: AppColors.info600,
    route: '/home/exams',
  ),
  _ModuleTile(label: 'Fees', icon: Icons.payments_rounded, color: AppColors.gold600, route: '/home/fees'),
  _ModuleTile(
    label: 'Guardians',
    icon: Icons.family_restroom_rounded,
    color: AppColors.navy600,
    route: '/home/guardians',
  ),
  _ModuleTile(
    label: 'Transport',
    icon: Icons.directions_bus_rounded,
    color: AppColors.green700,
    route: '/home/transport',
  ),
  _ModuleTile(
    label: 'Certificates',
    icon: Icons.workspace_premium_rounded,
    color: AppColors.gold700,
    route: '/home/certificates',
  ),
  _ModuleTile(
    label: 'ID Cards',
    icon: Icons.badge_rounded,
    color: AppColors.navy600,
    route: '/home/id-cards',
  ),
  _ModuleTile(label: 'Academics', icon: Icons.school_rounded, color: AppColors.navy700, route: '/home/academics'),
  _ModuleTile(label: 'Staff', icon: Icons.groups_rounded, color: AppColors.info600, route: '/home/staff'),
  _ModuleTile(label: 'Inventory', icon: Icons.inventory_2_rounded, color: AppColors.gold700, route: '/home/inventory'),
  _ModuleTile(label: 'Library', icon: Icons.local_library_rounded, color: AppColors.green700, route: '/home/library'),
  _ModuleTile(label: 'Hostel', icon: Icons.apartment_rounded, color: AppColors.navy600, route: '/home/hostel'),
  _ModuleTile(label: 'Timetable', icon: Icons.calendar_month_rounded, color: AppColors.info600, route: '/home/timetable'),
  _ModuleTile(label: 'Results', icon: Icons.emoji_events_rounded, color: AppColors.gold600, route: '/home/results'),
  _ModuleTile(label: 'My payslips', icon: Icons.receipt_long_rounded, color: AppColors.green700, route: '/home/payslips'),
  _ModuleTile(label: 'Leave', icon: Icons.beach_access_rounded, color: AppColors.navy700, route: '/home/leave'),
];

/// Home tab: a compact greeting plus a modern "quick access" grid of
/// module tiles the user is entitled to. This is the app's launcher —
/// new modules land here as another tile, not as another bottom-nav icon.
class HomeTabScreen extends ConsumerWidget {
  const HomeTabScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;

    // A Super Admin belongs to no school — none of the module tiles below apply to
    // them, so they get the platform-wide dashboard instead, not an empty grid.
    if (user?.isPlatformUser == true) {
      return Scaffold(
        appBar: AppBar(title: Text(context.tr('ElimuGrid'))),
        body: const SafeArea(child: PlatformDashboardBody()),
      );
    }

    final isStudent = user?.hasRole('Student') ?? false;

    final availableModules = _modules.where((m) {
      return switch (m.route) {
        '/home/my-children' => (user?.hasRole('Parent') ?? false) || isStudent,
        '/home/students' => user?.can('students.view') ?? false,
        '/home/attendance' => user?.can('attendance.view') ?? false,
        '/home/staff-attendance' => user?.can('staff_attendance.view') ?? false,
        '/home/attendance-scan' => user?.can('attendance.scan') ?? false,
        '/home/exams' => user?.can('exams.view') ?? false,
        '/home/fees' => user?.can('fees.view') ?? false,
        '/home/guardians' => user?.can('guardians.view') ?? false,
        '/home/transport' => (user?.can('transport.view') ?? false) || (user?.can('transport.track') ?? false),
        '/home/certificates' => user?.can('certificates.view') ?? false,
        '/home/id-cards' => user?.can('id_cards.view') ?? false,
        // Staff tools: not for the platform team, and not for a parent or student (neither is on the payroll or a
        // teacher's timetable — their own timetable is under "My children" / "My results" instead).
        '/home/timetable' || '/home/payslips' || '/home/leave' => user?.isPlatformUser == false && !(user!.roles.isNotEmpty && user.roles.every((r) => r == 'Parent' || r == 'Student')),
        '/home/results' => user?.can('results.view') ?? false,
        '/home/academics' => user?.can('classes.view') ?? false,
        '/home/staff' => user?.can('hr.view') ?? false,
        '/home/inventory' => user?.can('inventory.view') ?? false,
        '/home/library' => user?.can('library.view') ?? false,
        '/home/hostel' => user?.can('hostel.view') ?? false,
        _ => true,
      };
    }).toList();

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('ElimuGrid'))),
      body: SafeArea(
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
                      child: Text(
                        (user?.name.isNotEmpty ?? false) ? user!.name[0].toUpperCase() : '?',
                        style: AppTypography.headingMd.copyWith(color: AppColors.navy700),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(user?.name ?? '', style: AppTypography.headingSm),
                          const SizedBox(height: 2),
                          Text(
                            user?.isPlatformUser == true
                                ? context.tr('Platform Super Admin')
                                : user?.school?.name ?? '',
                            style: AppTypography.bodySm,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: AppSpacing.sm,
              children: (user?.roles ?? []).map((role) => Chip(
                label: Text(context.tr(role)),
                backgroundColor: AppColors.gold100,
                labelStyle: AppTypography.bodySm.copyWith(color: AppColors.navy900),
                side: BorderSide.none,
              )).toList(),
            ),
            const SizedBox(height: AppSpacing.lg),
            if (availableModules.isNotEmpty) ...[
              Text(context.tr('Quick access'), style: AppTypography.label),
              const SizedBox(height: AppSpacing.sm),
              LayoutBuilder(
                builder: (context, constraints) {
                  final columns = constraints.maxWidth >= 520 ? 3 : 2;

                  return GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: availableModules.length,
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: columns,
                      mainAxisSpacing: AppSpacing.sm,
                      crossAxisSpacing: AppSpacing.sm,
                      mainAxisExtent: 104,
                    ),
                    itemBuilder: (context, index) {
                      final m = availableModules[index];

                      return _ModuleCard(module: isStudent && m.route == '/home/my-children' ? _ModuleTile(label: 'My results', icon: m.icon, color: m.color, route: m.route) : m);
                    },
                  );
                },
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.module});

  final _ModuleTile module;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: () => context.push(module.route),
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: module.color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
                ),
                child: Icon(module.icon, color: module.color, size: 20),
              ),
              Text(context.tr(module.label), style: AppTypography.bodyMd.copyWith(fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }
}
