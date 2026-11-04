import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/network/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_typography.dart';
import '../../shared/crud/crud_list.dart';
import '../../shared/forms/entity_form.dart';
import '../../shared/utils/currency.dart';
import '../academics/academics_lookups.dart';

String _iso(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// What each class pays, and generating the invoices from it.
class FeeStructuresScreen extends ConsumerWidget {
  const FeeStructuresScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiClientProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Fee structures'))),
      body: CrudListBody<Map<String, dynamic>>(
        emptyText: 'No fee structures yet.',
        addLabel: 'Add fee structure',
        load: (_) => api.rows('/fee-structures'),
        onAdd: (context) => openForm(context, const FeeStructureFormScreen()),
        tile: (context, f, reload) {
          final items = (f['items'] as List<dynamic>).cast<Map<String, dynamic>>();

          return ListTile(
            onTap: () => showActions(context, title: f['name'] as String, subtitle: '${f['school_class']} · ${f['academic_year']}', actions: [
              SheetAction(Icons.receipt_long_outlined, 'Generate invoices for the class', () async {
                await openForm(context, GenerateInvoicesScreen(structure: f));
              }),
              SheetAction(Icons.edit_outlined, 'Edit', () async {
                if (await openForm(context, FeeStructureFormScreen(structure: f))) await reload();
              }),
              SheetAction(Icons.delete_outline, 'Delete', () async {
                if (!await confirmAction(context, title: 'Delete this fee structure?', message: f['name'] as String)) return;

                try {
                  await api.delete('/fee-structures/${f['id']}');
                  await reload();
                } catch (e) {
                  if (context.mounted) showError(context, e);
                }
              }, destructive: true),
            ]),
            leading: const CircleAvatar(backgroundColor: AppColors.gold100, child: Icon(Icons.request_quote_outlined, color: AppColors.gold700)),
            title: Text(f['name'] as String, style: AppTypography.bodyLg),
            subtitle: Text('${f['school_class']} · ${f['academic_year']}${f['term'] != null ? ' · ${f['term']}' : ''} · ${items.length} ${context.tr('items')}', style: AppTypography.bodySm),
            trailing: Text(formatMoney(num.tryParse('${f['total']}') ?? 0), style: AppTypography.headingSm),
          );
        },
      ),
    );
  }
}

class _ItemDraft {
  _ItemDraft({String name = '', String amount = ''})
      : name = TextEditingController(text: name),
        amount = TextEditingController(text: amount);

  final TextEditingController name;
  final TextEditingController amount;
}

class FeeStructureFormScreen extends ConsumerStatefulWidget {
  const FeeStructureFormScreen({super.key, this.structure});

  final Map<String, dynamic>? structure;

  @override
  ConsumerState<FeeStructureFormScreen> createState() => _FeeStructureFormScreenState();
}

class _FeeStructureFormScreenState extends ConsumerState<FeeStructureFormScreen> {
  late final List<_ItemDraft> _items = [
    for (final i in ((widget.structure?['items'] as List<dynamic>?) ?? const []).cast<Map<String, dynamic>>())
      _ItemDraft(name: i['name'] as String, amount: '${double.tryParse('${i['amount']}')?.toStringAsFixed(0) ?? i['amount']}'),
    if (widget.structure == null) _ItemDraft(),
  ];

  @override
  void dispose() {
    for (final i in _items) {
      i.name.dispose();
      i.amount.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final years = ref.watch(yearOptionsProvider);
    final classes = ref.watch(classOptionsProvider);
    final s = widget.structure;

    if (s == null && (!years.hasValue || !classes.hasValue)) {
      return Scaffold(appBar: AppBar(title: Text(context.tr('Fee structure'))), body: const Center(child: CircularProgressIndicator()));
    }

    final current = years.value?.where((y) => y.isCurrent).firstOrNull ?? years.value?.firstOrNull;

    return EntityFormScreen(
      title: s == null ? 'Add fee structure' : 'Edit fee structure',
      fields: [
        FieldSpec(key: 'name', label: 'Name', required: true, initial: s?['name'] as String?, maxLength: 150, hint: 'e.g. Form 1 — 2026'),
        if (s == null) ...[
          FieldSpec(key: 'academic_year_id', label: 'Academic year', kind: FieldKind.choice, required: true, initial: current != null ? '${current.id}' : null, choices: [for (final y in years.requireValue) FieldChoice('${y.id}', y.name)]),
          FieldSpec(key: 'school_class_id', label: 'Class', kind: FieldKind.choice, required: true, choices: [for (final c in classes.requireValue) FieldChoice('${c.id}', c.name)]),
        ],
      ],
      footer: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(context.tr('Fee items'), style: AppTypography.headingSm),
        const SizedBox(height: AppSpacing.sm),
        for (var i = 0; i < _items.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: Row(children: [
              Expanded(flex: 3, child: TextField(controller: _items[i].name, decoration: InputDecoration(labelText: context.tr('Item (e.g. Tuition)'), isDense: true))),
              const SizedBox(width: AppSpacing.sm),
              Expanded(flex: 2, child: TextField(controller: _items[i].amount, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: context.tr('Amount'), isDense: true))),
              IconButton(
                icon: const Icon(Icons.close, size: 18, color: AppColors.error600),
                onPressed: _items.length == 1 ? null : () => setState(() => _items.removeAt(i).name.dispose()),
              ),
            ]),
          ),
        OutlinedButton.icon(onPressed: () => setState(() => _items.add(_ItemDraft())), icon: const Icon(Icons.add), label: Text(context.tr('Add item'))),
        const SizedBox(height: AppSpacing.md),
      ]),
      onSubmit: (v) async {
        final items = [
          for (final i in _items)
            if (i.name.text.trim().isNotEmpty || i.amount.text.trim().isNotEmpty) {'name': i.name.text.trim(), 'amount': double.tryParse(i.amount.text.trim())},
        ];

        if (items.isEmpty || items.any((i) => (i['name'] as String).isEmpty || i['amount'] == null)) {
          throw ApiException(message: context.tr('Every fee item needs a name and an amount.'));
        }

        final api = ref.read(apiClientProvider);

        if (s == null) {
          await api.post('/fee-structures', data: {
            'name': v['name'],
            'academic_year_id': int.tryParse(v['academic_year_id'] ?? ''),
            'school_class_id': int.tryParse(v['school_class_id'] ?? ''),
            'items': items,
          });
        } else {
          await api.put('/fee-structures/${s['id']}', data: {'name': v['name'], 'items': items});
        }
      },
    );
  }
}

/// Invoice every active student in the structure's class.
class GenerateInvoicesScreen extends ConsumerWidget {
  const GenerateInvoicesScreen({super.key, required this.structure});

  final Map<String, dynamic> structure;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return EntityFormScreen(
      title: 'Generate invoices',
      submitLabel: 'Generate',
      fields: [
        FieldSpec(key: 'due_date', label: 'Due date', kind: FieldKind.date, initial: _iso(DateTime.now().add(const Duration(days: 30))), firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 730))),
      ],
      footer: Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.md),
        child: Text(
          context.tr('This invoices every active student in {class} for "{name}". Students who already have an invoice from it are skipped.', {'class': '${structure['school_class']}', 'name': '${structure['name']}'}),
          style: AppTypography.bodySm,
        ),
      ),
      onSubmit: (v) async {
        final body = await ref.read(apiClientProvider).post('/fee-structures/${structure['id']}/invoices', data: {if ((v['due_date'] ?? '').isNotEmpty) 'due_date': v['due_date']});
        final created = (body['data'] as List<dynamic>).length;

        if (context.mounted) showInfo(context, context.tr('{n} invoices created.', {'n': '$created'}));
      },
    );
  }
}
