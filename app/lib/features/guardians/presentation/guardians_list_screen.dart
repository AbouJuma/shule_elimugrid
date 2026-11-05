import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/crud/crud_list.dart';
import '../domain/guardian_model.dart';
import '../../auth/presentation/auth_controller.dart';
import 'guardian_form_screen.dart';
import 'guardian_login_actions.dart';
import 'guardians_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class GuardiansListScreen extends ConsumerStatefulWidget {
  const GuardiansListScreen({super.key});

  @override
  ConsumerState<GuardiansListScreen> createState() => _GuardiansListScreenState();
}

class _GuardiansListScreenState extends ConsumerState<GuardiansListScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels > _scrollController.position.maxScrollExtent - 200) {
        ref.read(guardiansListControllerProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _showDetail(GuardianModel guardian) {
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(guardian.name, style: AppTypography.headingMd),
            const SizedBox(height: AppSpacing.md),
            _DetailRow(icon: Icons.phone_outlined, label: 'Phone', value: guardian.phone),
            if (guardian.email != null)
              _DetailRow(icon: Icons.email_outlined, label: 'Email', value: guardian.email!),
            if (guardian.occupation != null)
              _DetailRow(icon: Icons.work_outline, label: 'Occupation', value: guardian.occupation!),
            if (guardian.address != null)
              _DetailRow(icon: Icons.home_outlined, label: 'Address', value: guardian.address!),
            if (ref.read(authControllerProvider).value?.user?.can('guardians.manage') ?? false) ...[
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                icon: const Icon(Icons.edit_outlined),
                label: Text(context.tr('Edit')),
                onPressed: () async {
                  Navigator.of(context).pop();
                  final saved = await Navigator.of(this.context).push<bool>(MaterialPageRoute(builder: (_) => GuardianFormScreen(guardian: guardian)));
                  if (saved == true) ref.read(guardiansListControllerProvider.notifier).refresh();
                },
              ),
              const SizedBox(height: AppSpacing.sm),
              OutlinedButton.icon(
                icon: Icon(guardian.hasLogin ? Icons.lock_reset : Icons.key_outlined),
                label: Text(context.tr(guardian.hasLogin ? 'Reset password' : 'Create parent login')),
                onPressed: () async {
                  Navigator.of(context).pop();
                  final changed = await manageGuardianLogin(this.context, ref, guardian);
                  if (changed) ref.read(guardiansListControllerProvider.notifier).refresh();
                },
              ),
              const SizedBox(height: AppSpacing.sm),
              OutlinedButton.icon(
                icon: const Icon(Icons.delete_outline, color: AppColors.error600),
                label: Text(context.tr('Delete guardian'), style: const TextStyle(color: AppColors.error600)),
                onPressed: () async {
                  final sheetContext = context;
                  final ok = await confirmAction(
                    sheetContext,
                    title: 'Delete this guardian?',
                    message: context.tr('Removes {name} and signs them out of any login they have. This cannot be undone.', {'name': guardian.name}),
                  );
                  if (!ok) return;
                  if (sheetContext.mounted) Navigator.of(sheetContext).pop();

                  try {
                    await ref.read(apiClientProvider).delete('/guardians/${guardian.id}');
                    ref.read(guardiansListControllerProvider.notifier).refresh();
                    if (this.context.mounted) showInfo(this.context, context.tr('Guardian deleted.'));
                  } catch (e) {
                    if (this.context.mounted) showError(this.context, e);
                  }
                },
              ),
            ],
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final asyncState = ref.watch(guardiansListControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Guardians'))),
      floatingActionButton: (ref.watch(authControllerProvider).value?.user?.can('guardians.manage') ?? false)
          ? FloatingActionButton.extended(
              onPressed: () async {
                final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const GuardianFormScreen()));
                if (saved == true) ref.read(guardiansListControllerProvider.notifier).refresh();
              },
              icon: const Icon(Icons.person_add_alt),
              label: Text(context.tr('Add guardian')),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: context.tr('Search name or phone…'),
                prefixIcon: Icon(Icons.search),
                isDense: true,
              ),
              textInputAction: TextInputAction.search,
              onSubmitted: (value) => ref.read(guardiansListControllerProvider.notifier).setSearch(value),
            ),
          ),
          Expanded(
            child: switch (asyncState) {
              AsyncData(:final value) when value.guardians.isEmpty =>
                Center(child: Text(context.tr('No guardians found.'), style: AppTypography.bodyMd)),
              AsyncData(:final value) => RefreshIndicator(
                onRefresh: () => ref.read(guardiansListControllerProvider.notifier).refresh(),
                child: ListView.separated(
                  controller: _scrollController,
                  itemCount: value.guardians.length + (value.hasMore ? 1 : 0),
                  separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
                  itemBuilder: (context, index) {
                    if (index >= value.guardians.length) {
                      return const Padding(
                        padding: EdgeInsets.all(AppSpacing.md),
                        child: Center(child: CircularProgressIndicator()),
                      );
                    }
                    final guardian = value.guardians[index];
                    return ListTile(
                      onTap: () => _showDetail(guardian),
                      leading: CircleAvatar(
                        backgroundColor: AppColors.navy100,
                        child: Text(
                          guardian.name.isNotEmpty ? guardian.name[0].toUpperCase() : '?',
                          style: AppTypography.headingSm.copyWith(color: AppColors.navy700),
                        ),
                      ),
                      title: Text(guardian.name, style: AppTypography.bodyLg),
                      subtitle: Text(guardian.phone, style: AppTypography.bodySm),
                      trailing: guardian.hasLogin ? const Icon(Icons.verified_user_outlined, size: 18, color: AppColors.green700) : null,
                    );
                  },
                ),
              ),
              AsyncError(:final error) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: Text('${context.tr('Could not load guardians.')}\n$error', textAlign: TextAlign.center),
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

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.textSecondary),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(context.tr(label), style: AppTypography.bodySm),
                Text(value, style: AppTypography.bodyMd),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
