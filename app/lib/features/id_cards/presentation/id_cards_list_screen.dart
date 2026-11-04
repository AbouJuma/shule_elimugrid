import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../certificates/issue_forms.dart';
import 'id_cards_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class IdCardsListScreen extends ConsumerStatefulWidget {
  const IdCardsListScreen({super.key});

  @override
  ConsumerState<IdCardsListScreen> createState() => _IdCardsListScreenState();
}

class _IdCardsListScreenState extends ConsumerState<IdCardsListScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels > _scrollController.position.maxScrollExtent - 200) {
        ref.read(idCardsListControllerProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncState = ref.watch(idCardsListControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('ID Cards'))),
      floatingActionButton: (ref.watch(authControllerProvider).value?.user?.can('id_cards.issue') ?? false)
          ? FloatingActionButton.extended(
              onPressed: () async {
                final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const IssueIdCardScreen()));
                if (saved == true) ref.read(idCardsListControllerProvider.notifier).refresh();
              },
              icon: const Icon(Icons.badge_outlined),
              label: Text(context.tr('Issue ID card')),
            )
          : null,
      body: switch (asyncState) {
        AsyncData(:final value) when value.idCards.isEmpty =>
          Center(child: Text(context.tr('No ID cards issued yet.'), style: AppTypography.bodyMd)),
        AsyncData(:final value) => RefreshIndicator(
          onRefresh: () => ref.read(idCardsListControllerProvider.notifier).refresh(),
          child: ListView.separated(
            controller: _scrollController,
            itemCount: value.idCards.length + (value.hasMore ? 1 : 0),
            separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
            itemBuilder: (context, index) {
              if (index >= value.idCards.length) {
                return const Padding(
                  padding: EdgeInsets.all(AppSpacing.md),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              final idCard = value.idCards[index];
              return ListTile(
                onTap: () => context.push('/home/id-cards/${idCard.id}'),
                leading: CircleAvatar(
                  backgroundColor: idCard.isActive ? AppColors.navy100 : AppColors.error100,
                  child: Icon(
                    Icons.badge_outlined,
                    color: idCard.isActive ? AppColors.navy700 : AppColors.error600,
                  ),
                ),
                title: Text(idCard.student?.name ?? idCard.cardNumber, style: AppTypography.bodyLg),
                subtitle: Text(idCard.cardNumber, style: AppTypography.bodySm),
                trailing: _StatusBadge(status: idCard.status),
              );
            },
          ),
        ),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load ID cards.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final isActive = status == 'active';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: isActive ? AppColors.green100 : AppColors.error100,
        borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
      ),
      child: Text(
        context.tr(status[0].toUpperCase() + status.substring(1)),
        style: AppTypography.bodySm.copyWith(color: isActive ? AppColors.green700 : AppColors.error600),
      ),
    );
  }
}
