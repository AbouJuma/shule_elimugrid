import 'package:flutter/material.dart';
import '../../../../core/i18n/app_localizations.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../domain/trip_models.dart';

String formatClock(DateTime t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

/// "12 s", "3 min" — how long ago something happened.
String agoShort(DateTime t) {
  final s = DateTime.now().difference(t).inSeconds.clamp(0, 1 << 30);

  return s < 90 ? '$s s' : '${(s / 60).round()} min';
}

/// "1 student" / "3 students".
String studentCount(BuildContext context, int n) => '$n ${context.tr(n == 1 ? 'student' : 'students')}';

/// "≈ 6 min · 1.4 km"
String etaLabel(BuildContext context, NextStopEta e) => '≈ ${e.etaMinutes} ${context.tr('min')} · ${e.distanceLabel}';

String directionLabel(BuildContext context, Trip trip) =>
    context.tr(trip.isMorning ? 'Morning pick-up' : 'Afternoon drop-off');

/// Every stop in visiting order with its state: reached (time), next, or waiting.
class TripStopsList extends StatelessWidget {
  const TripStopsList({super.key, required this.trip, this.shrinkWrap = false});

  final Trip trip;
  final bool shrinkWrap;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      shrinkWrap: shrinkWrap,
      physics: shrinkWrap ? const NeverScrollableScrollPhysics() : null,
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      itemCount: trip.stops.length,
      separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
      itemBuilder: (context, i) {
        final s = trip.stops[i];
        final colour = s.arrived ? AppColors.green700 : (s.isNext ? AppColors.gold700 : AppColors.textSecondary);

        return ListTile(
          dense: true,
          leading: CircleAvatar(
            radius: 15,
            backgroundColor: s.arrived ? AppColors.green100 : (s.isNext ? AppColors.gold100 : AppColors.surface),
            child: s.arrived
                ? Icon(Icons.check, size: 16, color: colour)
                : Text('${i + 1}', style: AppTypography.bodySm.copyWith(color: colour, fontWeight: FontWeight.w700)),
          ),
          title: Text(s.name, style: AppTypography.bodyMd.copyWith(fontWeight: s.isNext ? FontWeight.w700 : FontWeight.w500)),
          subtitle: Text(
            [
              if (s.students > 0) studentCount(context, s.students),
              if ((trip.isMorning ? s.pickupTime : s.dropoffTime) != null) (trip.isMorning ? s.pickupTime! : s.dropoffTime!).substring(0, 5),
            ].join(' · '),
            style: AppTypography.bodySm,
          ),
          trailing: s.arrived
              ? Text(formatClock(s.arrivedAt!), style: AppTypography.bodySm.copyWith(color: AppColors.green700))
              : (s.isNext ? Text(context.tr('Next stop'), style: AppTypography.label.copyWith(color: AppColors.gold700)) : null),
        );
      },
    );
  }
}
