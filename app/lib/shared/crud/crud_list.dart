import 'package:flutter/material.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_typography.dart';

/// The message to show for any failure: the first field error, else the server message, else the exception text.
String errorMessage(Object e) {
  if (e is ApiException) {
    return e.fieldErrors?.values.firstOrNull?.firstOrNull ?? e.message;
  }

  return e.toString();
}

void showError(BuildContext context, Object e) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(errorMessage(e))));
}

void showInfo(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
}

Future<bool> confirmAction(BuildContext context, {required String title, String? message, String confirmLabel = 'Delete'}) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(context.tr(title)),
      content: message == null ? null : Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: Text(context.tr('Cancel'))),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(context.tr(confirmLabel))),
      ],
    ),
  );

  return ok == true;
}

/// Opens [screen] and reports whether it saved something (the form pops `true`).
Future<bool> openForm(BuildContext context, Widget screen) async =>
    await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => screen)) == true;

/// One row of an action sheet.
class SheetAction {
  const SheetAction(this.icon, this.label, this.run, {this.destructive = false});

  final IconData icon;
  final String label;
  final Future<void> Function() run;
  final bool destructive;
}

/// A bottom sheet of actions for a tapped row. Runs the chosen action after the sheet closes.
Future<void> showActions(BuildContext context, {required String title, String? subtitle, required List<SheetAction> actions}) async {
  final picked = await showModalBottomSheet<SheetAction>(
    context: context,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.sm),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: AppTypography.headingMd),
              if (subtitle != null) Text(subtitle, style: AppTypography.bodySm),
            ]),
          ),
          for (final a in actions)
            ListTile(
              leading: Icon(a.icon, color: a.destructive ? AppColors.error600 : AppColors.navy700),
              title: Text(context.tr(a.label), style: a.destructive ? const TextStyle(color: AppColors.error600) : null),
              onTap: () => Navigator.of(context).pop(a),
            ),
        ],
      ),
    ),
  );

  if (picked != null) await picked.run();
}

/// A searchable, pull-to-refresh list with an optional "add" button — the shape
/// of nearly every management screen (staff, stock, books, hostels, classes…).
/// The list re-loads after [onAdd] or any [tile] callback finishes.
class CrudListBody<T> extends StatefulWidget {
  const CrudListBody({
    super.key,
    required this.load,
    required this.tile,
    this.onAdd,
    this.addLabel = 'Add',
    this.emptyText = 'Nothing here yet.',
    this.searchHint,
  });

  final Future<List<T>> Function(String search) load;

  /// Build a row. Call `reload()` after changing something.
  final Widget Function(BuildContext context, T item, Future<void> Function() reload) tile;

  /// Opens the "new" form; return true when something was saved.
  final Future<bool> Function(BuildContext context)? onAdd;
  final String addLabel;
  final String emptyText;
  final String? searchHint;

  @override
  State<CrudListBody<T>> createState() => _CrudListBodyState<T>();
}

class _CrudListBodyState<T> extends State<CrudListBody<T>> {
  late Future<List<T>> _future = widget.load('');
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final next = widget.load(_search.text.trim());
    setState(() => _future = next);
    await next.catchError((_) => <T>[]);
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Column(
          children: [
            if (widget.searchHint != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, AppSpacing.xs),
                child: TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _reload(),
                  decoration: InputDecoration(hintText: context.tr(widget.searchHint!), prefixIcon: const Icon(Icons.search), isDense: true),
                ),
              ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: _reload,
                child: FutureBuilder<List<T>>(
                  future: _future,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());

                    if (snapshot.hasError) {
                      return ListView(children: [Padding(padding: const EdgeInsets.all(AppSpacing.lg), child: Text(errorMessage(snapshot.error!), textAlign: TextAlign.center))]);
                    }

                    final rows = snapshot.data ?? <T>[];

                    if (rows.isEmpty) {
                      return ListView(children: [Padding(padding: const EdgeInsets.all(AppSpacing.xl), child: Center(child: Text(context.tr(widget.emptyText), style: AppTypography.bodyMd)))]);
                    }

                    return ListView.separated(
                      padding: const EdgeInsets.only(bottom: 96),
                      itemCount: rows.length,
                      separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
                      itemBuilder: (context, i) => widget.tile(context, rows[i], _reload),
                    );
                  },
                ),
              ),
            ),
          ],
        ),
        if (widget.onAdd != null)
          Positioned(
            right: AppSpacing.md,
            bottom: AppSpacing.md,
            child: FloatingActionButton.extended(
              heroTag: null,
              onPressed: () async {
                if (await widget.onAdd!(context)) await _reload();
              },
              icon: const Icon(Icons.add),
              label: Text(context.tr(widget.addLabel)),
            ),
          ),
      ],
    );
  }
}

/// A small coloured pill for a status.
class StatusPill extends StatelessWidget {
  const StatusPill(this.text, {super.key, this.tone = PillTone.neutral});

  final String text;
  final PillTone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (tone) {
      PillTone.good => (AppColors.green100, AppColors.green700),
      PillTone.warn => (AppColors.warning100, AppColors.warning600),
      PillTone.bad => (AppColors.error100, AppColors.error600),
      PillTone.neutral => (AppColors.navy100, AppColors.navy700),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(AppSpacing.radiusPill)),
      child: Text(text, style: AppTypography.label.copyWith(color: fg)),
    );
  }
}

enum PillTone { good, warn, bad, neutral }
