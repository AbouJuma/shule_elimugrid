import 'package:flutter/material.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_typography.dart';

class FieldChoice {
  const FieldChoice(this.value, this.label);

  final String value;
  final String label;
}

enum FieldKind { text, multiline, number, decimal, phone, email, password, date, choice, multi }

/// One input on a form. Forms are described as a list of these, so a new
/// create/edit screen is a field list plus a submit callback — the layout,
/// validation, date/dropdown handling, busy state and server-error mapping
/// are all shared.
class FieldSpec {
  const FieldSpec({
    required this.key,
    required this.label,
    this.kind = FieldKind.text,
    this.required = false,
    this.initial,
    this.choices = const [],
    this.choicesFor,
    this.visibleWhen,
    this.hint,
    this.maxLength,
    this.firstDate,
    this.lastDate,
    this.pickerStart,
    this.search,
    this.initialLabel,
    this.serverKey,
    this.section,
  });

  final String key;

  /// English label (translated at render time).
  final String label;
  final FieldKind kind;
  final bool required;
  final String? initial;
  final List<FieldChoice> choices;

  /// Choices that depend on other fields (e.g. streams of the chosen class).
  final List<FieldChoice> Function(Map<String, String> values)? choicesFor;

  /// Hide the field (and skip its validation) unless this returns true.
  final bool Function(Map<String, String> values)? visibleWhen;
  final String? hint;
  final int? maxLength;
  final DateTime? firstDate;
  final DateTime? lastDate;

  /// Where the date picker opens when nothing is chosen yet (e.g. ~10 years ago for a birth date).
  final DateTime? pickerStart;

  /// For choices too long for a dropdown (students, guardians): the field opens a
  /// search sheet and calls this with what was typed. The chosen value is submitted;
  /// its label is shown in the field.
  final Future<List<FieldChoice>> Function(String query)? search;

  /// The label to show for [initial] when the field is [search]-based.
  final String? initialLabel;

  /// The key the server reports this field's errors under, when it differs from [key].
  final String? serverKey;

  /// A heading shown above this field (starts a new section).
  final String? section;
}

/// A scrollable form built from [fields]. [onSubmit] receives every visible
/// field as a string (dates as yyyy-MM-dd, blank as ''). Throw an
/// [ApiException] to show server-side validation errors next to the fields.
/// Pops with `true` when the submit succeeds.
class EntityFormScreen extends StatefulWidget {
  const EntityFormScreen({
    super.key,
    required this.title,
    required this.fields,
    required this.onSubmit,
    this.submitLabel = 'Save',
    this.actions = const [],
    this.footer,
  });

  final String title;
  final List<FieldSpec> fields;
  final Future<void> Function(Map<String, String> values) onSubmit;
  final String submitLabel;

  /// Extra app-bar actions (e.g. a delete button).
  final List<Widget> actions;

  /// Extra content under the fields (e.g. a stop editor).
  final Widget? footer;

  @override
  State<EntityFormScreen> createState() => _EntityFormScreenState();
}

class _EntityFormScreenState extends State<EntityFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, String> _values = {};
  final Map<String, String> _errors = {};

  // Password fields default obscured; a key present and true means "shown".
  final Map<String, bool> _passwordShown = {};
  final Map<String, String> _labels = {};
  bool _busy = false;
  String? _generalError;

  bool _isText(FieldKind k) => k != FieldKind.date && k != FieldKind.choice && k != FieldKind.multi;

  @override
  void initState() {
    super.initState();

    for (final f in widget.fields) {
      _values[f.key] = f.initial ?? '';
      if (f.initialLabel != null) _labels[f.key] = f.initialLabel!;

      if (_isText(f.kind)) _controllers[f.key] = TextEditingController(text: f.initial ?? '');
    }
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Map<String, String> get _snapshot => {
    for (final f in widget.fields) f.key: _isText(f.kind) ? _controllers[f.key]!.text.trim() : (_values[f.key] ?? ''),
  };

  bool _visible(FieldSpec f) => f.visibleWhen == null || f.visibleWhen!(_snapshot);

  List<FieldChoice> _choices(FieldSpec f) => f.choicesFor != null ? f.choicesFor!(_snapshot) : f.choices;

  /// After any change, drop a choice that is no longer valid (a stream of another class).
  void _reconcileChoices() {
    for (final f in widget.fields.where((f) => f.kind == FieldKind.choice && f.choicesFor != null)) {
      final current = _values[f.key] ?? '';
      if (current.isNotEmpty && !_choices(f).any((c) => c.value == current)) _values[f.key] = '';
    }
  }

  Future<void> _pickDate(FieldSpec f) async {
    final now = DateTime.now();
    final current = DateTime.tryParse(_values[f.key] ?? '');
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? f.pickerStart ?? (f.lastDate != null && f.lastDate!.isBefore(now) ? f.lastDate! : now),
      firstDate: f.firstDate ?? DateTime(1990),
      lastDate: f.lastDate ?? DateTime(now.year + 5),
    );

    if (picked == null) return;

    setState(() {
      _values[f.key] = '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
      _errors.remove(f.key);
      _reconcileChoices();
    });
  }

  Future<void> _pickSearch(FieldSpec f) async {
    final picked = await showModalBottomSheet<FieldChoice>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SearchSheet(title: context.tr(f.label), search: f.search!),
    );
    if (picked == null) return;

    setState(() {
      _values[f.key] = picked.value;
      _labels[f.key] = picked.label;
      _errors.remove(f.key);
      _reconcileChoices();
    });
  }

  Future<void> _pickMulti(FieldSpec f) async {
    final chosen = (_values[f.key] ?? '').split(',').where((e) => e.isNotEmpty).toSet();
    final result = await showModalBottomSheet<Set<String>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _MultiSheet(title: context.tr(f.label), choices: _choices(f), chosen: chosen),
    );
    if (result == null) return;

    setState(() {
      _values[f.key] = result.join(',');
      _errors.remove(f.key);
    });
  }

  Future<void> _submit() async {
    setState(() => _generalError = null);

    // Required check per visible field (dates and dropdowns aren't TextFormFields).
    final missing = <String, String>{};
    for (final f in widget.fields.where((f) => f.required && _visible(f))) {
      if ((_snapshot[f.key] ?? '').isEmpty) missing[f.key] = context.tr('This field is required.');
    }

    if (!_formKey.currentState!.validate() || missing.isNotEmpty) {
      setState(() {
        _errors
          ..clear()
          ..addAll(missing);
      });
      return;
    }

    final values = {for (final e in _snapshot.entries) if (_visible(widget.fields.firstWhere((f) => f.key == e.key))) e.key: e.value};

    setState(() {
      _busy = true;
      _errors.clear();
    });

    try {
      await widget.onSubmit(values);
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;

      final mapped = <String, String>{};
      for (final entry in (e.fieldErrors ?? const <String, List<String>>{}).entries) {
        final spec = widget.fields.where((f) => f.serverKey == entry.key || f.key == entry.key || entry.key.endsWith('.${f.key}')).firstOrNull;
        if (spec != null && entry.value.isNotEmpty) mapped[spec.key] = entry.value.first;
      }

      setState(() {
        _errors.addAll(mapped);
        _generalError = mapped.isEmpty ? e.message : null;
      });
    } catch (e) {
      if (mounted) setState(() => _generalError = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr(widget.title)), actions: widget.actions),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [
              if (_generalError != null)
                Container(
                  margin: const EdgeInsets.only(bottom: AppSpacing.md),
                  padding: const EdgeInsets.all(AppSpacing.md),
                  decoration: BoxDecoration(color: AppColors.error100, borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
                  child: Text(_generalError!, style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
                ),
              for (final f in widget.fields)
                if (_visible(f)) ...[
                  if (f.section != null)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.sm, bottom: AppSpacing.sm),
                      child: Text(context.tr(f.section!), style: AppTypography.headingSm),
                    ),
                  Padding(padding: const EdgeInsets.only(bottom: AppSpacing.md), child: _field(context, f)),
                ],
              if (widget.footer != null) widget.footer!,
              const SizedBox(height: AppSpacing.sm),
              FilledButton(
                onPressed: _busy ? null : _submit,
                child: _busy
                    ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : Text(context.tr(widget.submitLabel)),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
      ),
    );
  }

  String _label(BuildContext context, FieldSpec f) => '${context.tr(f.label)}${f.required ? ' *' : ''}';

  Widget _field(BuildContext context, FieldSpec f) {
    if (f.kind == FieldKind.choice && f.search != null) {
      final label = _labels[f.key];

      return InkWell(
        onTap: () => _pickSearch(f),
        child: InputDecorator(
          decoration: InputDecoration(labelText: _label(context, f), errorText: _errors[f.key], suffixIcon: const Icon(Icons.search, size: 20)),
          child: Text(
            label == null || (_values[f.key] ?? '').isEmpty ? context.tr('Tap to search…') : label,
            style: AppTypography.bodyMd.copyWith(color: (_values[f.key] ?? '').isEmpty ? AppColors.textSecondary : AppColors.textPrimary),
          ),
        ),
      );
    }

    if (f.kind == FieldKind.multi) {
      final chosen = (_values[f.key] ?? '').split(',').where((e) => e.isNotEmpty).toSet();
      final names = _choices(f).where((c) => chosen.contains(c.value)).map((c) => c.label).toList();

      return InkWell(
        onTap: () => _pickMulti(f),
        child: InputDecorator(
          decoration: InputDecoration(labelText: _label(context, f), errorText: _errors[f.key], suffixIcon: const Icon(Icons.arrow_drop_down)),
          child: Text(
            names.isEmpty ? context.tr('None selected') : names.join(', '),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.bodyMd.copyWith(color: names.isEmpty ? AppColors.textSecondary : AppColors.textPrimary),
          ),
        ),
      );
    }

    switch (f.kind) {
      case FieldKind.date:
        return InkWell(
          onTap: () => _pickDate(f),
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: _label(context, f),
              errorText: _errors[f.key],
              suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
            ),
            child: Text(
              (_values[f.key] ?? '').isEmpty ? (f.hint != null ? context.tr(f.hint!) : ' ') : _values[f.key]!,
              style: AppTypography.bodyMd.copyWith(color: (_values[f.key] ?? '').isEmpty ? AppColors.textSecondary : AppColors.textPrimary),
            ),
          ),
        );

      case FieldKind.choice:
        final choices = _choices(f);
        final current = _values[f.key] ?? '';

        return DropdownButtonFormField<String>(
          key: ValueKey('${f.key}|${choices.map((c) => c.value).join(',')}|$current'),
          initialValue: choices.any((c) => c.value == current) ? current : null,
          isExpanded: true,
          decoration: InputDecoration(labelText: _label(context, f), errorText: _errors[f.key]),
          items: [for (final c in choices) DropdownMenuItem(value: c.value, child: Text(c.label, overflow: TextOverflow.ellipsis))],
          onChanged: (v) => setState(() {
            _values[f.key] = v ?? '';
            _errors.remove(f.key);
            _reconcileChoices();
          }),
        );

      default:
        final isPassword = f.kind == FieldKind.password;
        final shown = _passwordShown[f.key] ?? false;

        return TextFormField(
          controller: _controllers[f.key],
          maxLength: f.maxLength,
          buildCounter: (_, {required currentLength, required isFocused, maxLength}) => null,
          maxLines: f.kind == FieldKind.multiline ? 3 : 1,
          obscureText: isPassword && !shown,
          enableSuggestions: !isPassword,
          autocorrect: !isPassword,
          keyboardType: switch (f.kind) {
            FieldKind.number => TextInputType.number,
            FieldKind.decimal => const TextInputType.numberWithOptions(decimal: true),
            FieldKind.phone => TextInputType.phone,
            FieldKind.email => TextInputType.emailAddress,
            FieldKind.password => TextInputType.visiblePassword,
            FieldKind.multiline => TextInputType.multiline,
            _ => TextInputType.text,
          },
          textCapitalization: f.kind == FieldKind.text ? TextCapitalization.words : TextCapitalization.none,
          decoration: InputDecoration(
            labelText: _label(context, f),
            hintText: f.hint != null ? context.tr(f.hint!) : null,
            errorText: _errors[f.key],
            suffixIcon: isPassword
                ? IconButton(
                    icon: Icon(shown ? Icons.visibility_off : Icons.visibility, size: 20),
                    onPressed: () => setState(() => _passwordShown[f.key] = !shown),
                  )
                : null,
          ),
          onChanged: (_) {
            if (_errors.containsKey(f.key)) setState(() => _errors.remove(f.key));
            // A dependent field may show/hide when this one changes.
            if (widget.fields.any((x) => x.visibleWhen != null || x.choicesFor != null)) setState(_reconcileChoices);
          },
        );
    }
  }
}

class _SearchSheet extends StatefulWidget {
  const _SearchSheet({required this.title, required this.search});

  final String title;
  final Future<List<FieldChoice>> Function(String query) search;

  @override
  State<_SearchSheet> createState() => _SearchSheetState();
}

class _SearchSheetState extends State<_SearchSheet> {
  final _query = TextEditingController();
  List<FieldChoice> _results = const [];
  bool _loading = true;
  String? _error;
  int _seq = 0;

  @override
  void initState() {
    super.initState();
    _run('');
  }

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  Future<void> _run(String q) async {
    final mine = ++_seq;
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final r = await widget.search(q.trim());
      if (mounted && mine == _seq) setState(() => _results = r);
    } catch (e) {
      if (mounted && mine == _seq) setState(() => _error = e is ApiException ? e.message : '$e');
    } finally {
      if (mounted && mine == _seq) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.75,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: TextField(
                controller: _query,
                autofocus: true,
                onChanged: _run,
                decoration: InputDecoration(hintText: widget.title, prefixIcon: const Icon(Icons.search), isDense: true),
              ),
            ),
            if (_loading) const LinearProgressIndicator(minHeight: 2),
            if (_error != null) Padding(padding: const EdgeInsets.all(AppSpacing.md), child: Text(_error!, style: AppTypography.bodySm.copyWith(color: AppColors.error600))),
            Expanded(
              child: _results.isEmpty && !_loading
                  ? Center(child: Text(context.tr('No matches.'), style: AppTypography.bodyMd))
                  : ListView.separated(
                      itemCount: _results.length,
                      separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
                      itemBuilder: (context, i) => ListTile(title: Text(_results[i].label), onTap: () => Navigator.of(context).pop(_results[i])),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MultiSheet extends StatefulWidget {
  const _MultiSheet({required this.title, required this.choices, required this.chosen});

  final String title;
  final List<FieldChoice> choices;
  final Set<String> chosen;

  @override
  State<_MultiSheet> createState() => _MultiSheetState();
}

class _MultiSheetState extends State<_MultiSheet> {
  late final Set<String> _chosen = {...widget.chosen};

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.7,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Row(children: [
              Expanded(child: Text(widget.title, style: AppTypography.headingSm)),
              FilledButton(onPressed: () => Navigator.of(context).pop(_chosen), child: Text(context.tr('Done'))),
            ]),
          ),
          Expanded(
            child: ListView(
              children: [
                for (final c in widget.choices)
                  CheckboxListTile(
                    title: Text(c.label),
                    value: _chosen.contains(c.value),
                    onChanged: (on) => setState(() => on == true ? _chosen.add(c.value) : _chosen.remove(c.value)),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
