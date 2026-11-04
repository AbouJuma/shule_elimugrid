import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../data/leave_repository.dart';
import '../domain/leave_models.dart';

final leaveRepositoryProvider = Provider<LeaveRepository>(
  (ref) => LeaveRepository(ref.watch(apiClientProvider)),
);

final leaveOverviewProvider = FutureProvider.autoDispose<LeaveOverview>(
  (ref) => ref.watch(leaveRepositoryProvider).overview(),
);

String _iso(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

String _message(Object error) => error is ApiException ? error.message : error.toString();

void _snack(BuildContext context, String text) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

class LeaveScreen extends ConsumerWidget {
  const LeaveScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final overview = ref.watch(leaveOverviewProvider);

    return switch (overview) {
      AsyncData(:final value) => _Loaded(overview: value),
      AsyncError(:final error) => Scaffold(
        appBar: AppBar(title: Text(context.tr('Leave'))),
        body: Center(child: Text(_message(error), textAlign: TextAlign.center)),
      ),
      _ => Scaffold(
        appBar: AppBar(title: Text(context.tr('Leave'))),
        body: const Center(child: CircularProgressIndicator()),
      ),
    };
  }
}

class _Loaded extends ConsumerWidget {
  const _Loaded({required this.overview});

  final LeaveOverview overview;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mine = _MyRequests(overview: overview);

    if (!overview.canApprove) {
      return Scaffold(
        appBar: AppBar(title: Text(context.tr('Leave'))),
        body: mine,
        floatingActionButton: _ApplyButton(overview: overview),
      );
    }

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.tr('Leave')),
          bottom: TabBar(tabs: [
            Tab(text: context.tr('My requests')),
            Tab(text: '${context.tr('Approvals')} (${overview.pending.length})'),
          ]),
        ),
        body: TabBarView(children: [mine, _Approvals(pending: overview.pending)]),
        floatingActionButton: _ApplyButton(overview: overview),
      ),
    );
  }
}

class _ApplyButton extends ConsumerWidget {
  const _ApplyButton({required this.overview});

  final LeaveOverview overview;

  @override
  Widget build(BuildContext context, WidgetRef ref) => FloatingActionButton.extended(
    icon: const Icon(Icons.add),
    label: Text(context.tr('Apply for leave')),
    onPressed: () async {
      final applied = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        builder: (_) => _ApplySheet(types: overview.types),
      );
      if (applied == true) ref.invalidate(leaveOverviewProvider);
    },
  );
}

class _MyRequests extends ConsumerWidget {
  const _MyRequests({required this.overview});

  final LeaveOverview overview;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (overview.myRequests.isEmpty) {
      return Center(child: Text(context.tr('You have not applied for any leave yet.')));
    }

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(leaveOverviewProvider),
      child: ListView.separated(
        padding: const EdgeInsets.only(bottom: 88),
        itemCount: overview.myRequests.length,
        separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
        itemBuilder: (context, i) => _RequestTile(item: overview.myRequests[i]),
      ),
    );
  }
}

class _RequestTile extends StatelessWidget {
  const _RequestTile({required this.item, this.trailing});

  final LeaveRequestItem item;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (item.status) {
      'approved' => (AppColors.green100, AppColors.green700),
      'rejected' => (AppColors.error100, AppColors.error600),
      _ => (AppColors.warning100, AppColors.warning600),
    };

    return ListTile(
      title: Text(
        '${item.requestedBy != null ? '${item.requestedBy} · ' : ''}${item.type ?? context.tr('Leave')}',
        style: AppTypography.bodyLg,
      ),
      subtitle: Text(
        '${item.startDate} → ${item.endDate} · ${item.days} ${context.tr('days')}'
        '${item.reason != null && item.reason!.isNotEmpty ? '\n${item.reason}' : ''}',
        style: AppTypography.bodySm,
      ),
      isThreeLine: item.reason != null && item.reason!.isNotEmpty,
      trailing: trailing ??
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(AppSpacing.radiusPill)),
            child: Text(context.tr(item.status[0].toUpperCase() + item.status.substring(1)), style: AppTypography.label.copyWith(color: fg)),
          ),
    );
  }
}

class _Approvals extends ConsumerWidget {
  const _Approvals({required this.pending});

  final List<LeaveRequestItem> pending;

  Future<void> _decide(BuildContext context, WidgetRef ref, LeaveRequestItem item, bool approve) async {
    final repo = ref.read(leaveRepositoryProvider);

    try {
      approve ? await repo.approve(item.id) : await repo.reject(item.id);
      ref.invalidate(leaveOverviewProvider);
      if (context.mounted) _snack(context, context.tr(approve ? 'Leave approved.' : 'Leave rejected.'));
    } catch (e) {
      if (context.mounted) _snack(context, _message(e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (pending.isEmpty) return Center(child: Text(context.tr('No requests waiting for approval.')));

    return ListView.separated(
      padding: const EdgeInsets.only(bottom: 88),
      itemCount: pending.length,
      separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
      itemBuilder: (context, i) {
        final item = pending[i];

        return _RequestTile(
          item: item,
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            IconButton(
              tooltip: context.tr('Reject'),
              icon: const Icon(Icons.close, color: AppColors.error600),
              onPressed: () => _decide(context, ref, item, false),
            ),
            IconButton(
              tooltip: context.tr('Approve'),
              icon: const Icon(Icons.check, color: AppColors.green700),
              onPressed: () => _decide(context, ref, item, true),
            ),
          ]),
        );
      },
    );
  }
}

class _ApplySheet extends ConsumerStatefulWidget {
  const _ApplySheet({required this.types});

  final List<LeaveType> types;

  @override
  ConsumerState<_ApplySheet> createState() => _ApplySheetState();
}

class _ApplySheetState extends ConsumerState<_ApplySheet> {
  String? _typeId;
  DateTime? _start;
  DateTime? _end;
  final _reason = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _pick(bool start) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: (start ? _start : _end) ?? _start ?? now,
      firstDate: now.subtract(const Duration(days: 30)),
      lastDate: now.add(const Duration(days: 730)),
    );
    if (picked == null) return;

    setState(() {
      if (start) {
        _start = picked;
        if (_end != null && _end!.isBefore(picked)) _end = picked;
      } else {
        _end = picked;
      }
    });
  }

  Future<void> _submit() async {
    if (_typeId == null || _start == null || _end == null) {
      setState(() => _error = context.tr('Choose a leave type and both dates.'));
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref.read(leaveRepositoryProvider).apply(
        leaveTypeId: _typeId!,
        startDate: _iso(_start!),
        endDate: _iso(_end!),
        reason: _reason.text.trim(),
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      setState(() {
        _busy = false;
        _error = _message(e);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, MediaQuery.of(context).viewInsets.bottom + AppSpacing.md),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(context.tr('Apply for leave'), style: AppTypography.headingMd),
            const SizedBox(height: AppSpacing.md),
            DropdownButtonFormField<String>(
              initialValue: _typeId,
              isExpanded: true,
              decoration: InputDecoration(labelText: context.tr('Leave type')),
              items: widget.types.map((t) => DropdownMenuItem(value: t.id, child: Text('${t.name} (${t.daysPerYear} ${context.tr('days')}/${context.tr('year')})${t.isPaid ? '' : ' · ${context.tr('unpaid')}'}'))).toList(),
              onChanged: (v) => setState(() => _typeId = v),
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(children: [
              Expanded(
                child: OutlinedButton.icon(
                  icon: const Icon(Icons.event, size: 18),
                  label: Text(_start == null ? context.tr('Start date') : _iso(_start!)),
                  onPressed: () => _pick(true),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: OutlinedButton.icon(
                  icon: const Icon(Icons.event, size: 18),
                  label: Text(_end == null ? context.tr('End date') : _iso(_end!)),
                  onPressed: () => _pick(false),
                ),
              ),
            ]),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: _reason,
              maxLines: 3,
              maxLength: 1000,
              decoration: InputDecoration(labelText: context.tr('Reason (optional)')),
            ),
            if (_error != null) Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: Text(_error!, style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
            ),
            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(context.tr('Submit request')),
            ),
          ],
        ),
      ),
    );
  }
}
