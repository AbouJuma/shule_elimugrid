import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../data/attendance_scan_repository.dart';

final attendanceScanRepositoryProvider = Provider<AttendanceScanRepository>(
  (ref) => AttendanceScanRepository(ref.watch(apiClientProvider)),
);

/// Marks whoever's ID was just scanned (student or staff) present for
/// today. Two ways in: the phone's camera (barcode/QR), or typing the
/// code directly — which is also how a Bluetooth/USB barcode-scanner
/// "gun" works here, since to the OS it's just a keyboard. Manual entry
/// is the default tab so opening this screen never forces a camera
/// permission prompt on someone who just wants to type a code.
class AttendanceScanScreen extends ConsumerStatefulWidget {
  const AttendanceScanScreen({super.key});

  @override
  ConsumerState<AttendanceScanScreen> createState() => _AttendanceScanScreenState();
}

class _AttendanceScanScreenState extends ConsumerState<AttendanceScanScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController = TabController(length: 2, vsync: this);
  final List<_ScanEvent> _history = [];

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _submit(String rawCode) async {
    final code = rawCode.trim();
    if (code.isEmpty) return;

    try {
      final result = await ref.read(attendanceScanRepositoryProvider).scan(code);
      if (mounted) setState(() => _history.insert(0, _ScanEvent.success(result)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _history.insert(0, _ScanEvent.failure(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Scan Attendance')),
        bottom: TabBar(
          controller: _tabController,
          tabs: [
            Tab(text: context.tr('Type Code')),
            Tab(text: context.tr('Camera')),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            flex: 3,
            child: TabBarView(
              controller: _tabController,
              children: [
                _ManualEntryTab(onSubmit: _submit),
                _CameraTab(onDetect: _submit),
              ],
            ),
          ),
          Expanded(
            flex: 2,
            child: _HistoryList(history: _history),
          ),
        ],
      ),
    );
  }
}

class _ManualEntryTab extends StatefulWidget {
  const _ManualEntryTab({required this.onSubmit});

  final ValueChanged<String> onSubmit;

  @override
  State<_ManualEntryTab> createState() => _ManualEntryTabState();
}

class _ManualEntryTabState extends State<_ManualEntryTab> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        children: [
          Text(
            context.tr('Type or scan (Bluetooth/USB scanner) a code, then press Enter.'),
            textAlign: TextAlign.center,
            style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _controller,
            autofocus: true,
            textAlign: TextAlign.center,
            textInputAction: TextInputAction.done,
            decoration: InputDecoration(labelText: context.tr('Code'), border: const OutlineInputBorder()),
            onSubmitted: (value) {
              widget.onSubmit(value);
              _controller.clear();
            },
          ),
        ],
      ),
    );
  }
}

class _CameraTab extends StatefulWidget {
  const _CameraTab({required this.onDetect});

  final ValueChanged<String> onDetect;

  @override
  State<_CameraTab> createState() => _CameraTabState();
}

class _CameraTabState extends State<_CameraTab> {
  final MobileScannerController _controller = MobileScannerController();
  DateTime? _lastDetectAt;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    // A camera keeps firing detections every frame while a code is in
    // view — debounce so one physical scan doesn't fire a dozen times.
    final now = DateTime.now();
    if (_lastDetectAt != null && now.difference(_lastDetectAt!) < const Duration(seconds: 2)) return;

    final value = capture.barcodes.firstOrNull?.rawValue;
    if (value == null) return;

    _lastDetectAt = now;
    widget.onDetect(value);
  }

  @override
  Widget build(BuildContext context) {
    return MobileScanner(controller: _controller, onDetect: _onDetect);
  }
}

class _ScanEvent {
  const _ScanEvent.success(this.result) : error = null;
  const _ScanEvent.failure(this.error) : result = null;

  final ScanResult? result;
  final String? error;
}

class _HistoryList extends StatelessWidget {
  const _HistoryList({required this.history});

  final List<_ScanEvent> history;

  @override
  Widget build(BuildContext context) {
    if (history.isEmpty) {
      return Center(
        child: Text(context.tr('Nothing scanned yet.'), style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary)),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      itemCount: history.length,
      separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
      itemBuilder: (context, i) {
        final event = history[i];

        if (event.result != null) {
          final r = event.result!;
          return ListTile(
            dense: true,
            leading: Icon(Icons.check_circle, color: AppColors.green700),
            title: Text(r.name),
            subtitle: Text([r.subLabel, if (r.alreadyMarked) context.tr('already marked today')].nonNulls.join(' · ')),
            trailing: Chip(label: Text(r.type), visualDensity: VisualDensity.compact),
          );
        }

        return ListTile(
          dense: true,
          leading: Icon(Icons.error, color: AppColors.error600),
          title: Text(event.error ?? context.tr('Not recognised.')),
        );
      },
    );
  }
}
