import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../timetable/presentation/timetable_controller.dart' show timetableClassesProvider;
import '../domain/student_model.dart';
import '../../auth/presentation/auth_controller.dart';
import 'student_form_screen.dart';
import 'students_list_controller.dart';
import '../../../core/i18n/app_localizations.dart';

const _statusOptions = [
  ('', 'All statuses'),
  ('active', 'Active'),
  ('graduated', 'Graduated'),
  ('withdrawn', 'Withdrawn'),
  ('transferred', 'Transferred'),
  ('suspended', 'Suspended'),
];

class StudentsListScreen extends ConsumerStatefulWidget {
  const StudentsListScreen({super.key});

  @override
  ConsumerState<StudentsListScreen> createState() => _StudentsListScreenState();
}

class _StudentsListScreenState extends ConsumerState<StudentsListScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >
          _scrollController.position.maxScrollExtent - 200) {
        ref.read(studentsListControllerProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncState = ref.watch(studentsListControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Students'))),
      floatingActionButton: (ref.watch(authControllerProvider).value?.user?.can('students.create') ?? false)
          ? FloatingActionButton.extended(
              onPressed: () async {
                final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const StudentFormScreen()));
                if (saved == true) ref.read(studentsListControllerProvider.notifier).refresh();
              },
              icon: const Icon(Icons.person_add_alt_1),
              label: Text(context.tr('Admit a student')),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.sm,
            ),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    decoration: InputDecoration(
                      hintText: context.tr('Search name or admission no…'),
                      prefixIcon: Icon(Icons.search),
                      isDense: true,
                    ),
                    textInputAction: TextInputAction.search,
                    onSubmitted: (value) =>
                        ref.read(studentsListControllerProvider.notifier).setSearch(value),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Builder(builder: (context) {
                  final classes = ref.watch(timetableClassesProvider).value ?? const [];
                  final selected = asyncState.value?.classId;

                  return PopupMenuButton<int>(
                    icon: Icon(Icons.class_outlined, color: selected == null ? AppColors.navy700 : AppColors.gold700),
                    tooltip: context.tr('Filter by class'),
                    onSelected: (id) =>
                        ref.read(studentsListControllerProvider.notifier).setClass(id == 0 ? null : id),
                    itemBuilder: (context) => [
                      PopupMenuItem(value: 0, child: Text(context.tr('All classes'))),
                      ...classes.map((c) => PopupMenuItem(value: c.id, child: Text(c.name))),
                    ],
                  );
                }),
                PopupMenuButton<String>(
                  icon: const Icon(Icons.filter_list, color: AppColors.navy700),
                  tooltip: context.tr('Filter by status'),
                  onSelected: (status) =>
                      ref.read(studentsListControllerProvider.notifier).setStatus(status),
                  itemBuilder: (context) => _statusOptions
                      .map((o) => PopupMenuItem(value: o.$1, child: Text(context.tr(o.$2))))
                      .toList(),
                ),
              ],
            ),
          ),
          Expanded(
            child: switch (asyncState) {
              AsyncData(:final value) when value.students.isEmpty =>
                Center(child: Text(context.tr('No students found.'), style: AppTypography.bodyMd)),
              AsyncData(:final value) => RefreshIndicator(
                onRefresh: () => ref.read(studentsListControllerProvider.notifier).refresh(),
                child: ListView.separated(
                  controller: _scrollController,
                  padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
                  itemCount: value.students.length + (value.hasMore ? 1 : 0),
                  separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
                  itemBuilder: (context, index) {
                    if (index >= value.students.length) {
                      return const Padding(
                        padding: EdgeInsets.all(AppSpacing.md),
                        child: Center(child: CircularProgressIndicator()),
                      );
                    }
                    return _StudentTile(student: value.students[index]);
                  },
                ),
              ),
              AsyncError(:final error) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: Text(
                    '${context.tr('Could not load students.')}\n$error',
                    textAlign: TextAlign.center,
                    style: AppTypography.bodyMd,
                  ),
                ),
              ),
              _ => const Center(child: CircularProgressIndicator()),
            },
          ),
        ],
      ),
    );
  }
}

class _StudentTile extends StatelessWidget {
  const _StudentTile({required this.student});

  final StudentModel student;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: () => context.push('/home/students/${student.id}'),
      leading: CircleAvatar(
        backgroundColor: AppColors.navy100,
        backgroundImage: student.photoUrl != null ? NetworkImage(student.photoUrl!) : null,
        child: student.photoUrl == null
            ? Text(
                student.firstName.isNotEmpty ? student.firstName[0] : '?',
                style: AppTypography.headingSm.copyWith(color: AppColors.navy700),
              )
            : null,
      ),
      title: Text(student.fullName, style: AppTypography.bodyLg),
      subtitle: Text(
        [
          student.admissionNumber,
          if (student.schoolClass != null) student.schoolClass!.name,
        ].join(' · '),
        style: AppTypography.bodySm,
      ),
      trailing: _StatusChip(status: student.status),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final isActive = status == 'active';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm, vertical: 4),
      decoration: BoxDecoration(
        color: isActive ? AppColors.green100 : AppColors.warning100,
        borderRadius: BorderRadius.circular(AppSpacing.radiusPill),
      ),
      child: Text(
        context.tr(status[0].toUpperCase() + status.substring(1)),
        style: AppTypography.bodySm.copyWith(
          color: isActive ? AppColors.green700 : AppColors.warning600,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
