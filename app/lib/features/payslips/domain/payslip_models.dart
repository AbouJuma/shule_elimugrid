/// Mirrors App\Http\Controllers\Api\V1\PayslipController's resource.
/// Amounts arrive as strings (decimal columns) and stay strings for display.
class PayslipLine {
  const PayslipLine({required this.name, required this.type, required this.amount, this.isEmployer = false});

  final String name;
  final String type;
  final String amount;

  /// A cost the school carries on top of the salary — never taken from the employee.
  final bool isEmployer;

  bool get isDeduction => type == 'deduction' && !isEmployer;

  num get value => num.tryParse(amount) ?? 0;

  factory PayslipLine.fromJson(Map<String, dynamic> json) => PayslipLine(
    name: json['name'] as String,
    type: json['type'] as String,
    amount: json['amount'].toString(),
    isEmployer: json['is_employer'] as bool? ?? false,
  );
}

class Payslip {
  const Payslip({
    required this.id,
    required this.periodLabel,
    required this.currency,
    required this.basicSalary,
    required this.totalAllowances,
    required this.totalDeductions,
    required this.grossPay,
    required this.netPay,
    required this.paymentStatus,
    this.paymentMethod,
    this.paidAt,
    required this.pdfUrl,
    this.lines = const [],
  });

  final String id;
  final String periodLabel;
  final String currency;
  final String basicSalary;
  final String totalAllowances;
  final String totalDeductions;
  final String grossPay;
  final String netPay;
  final String paymentStatus;
  final String? paymentMethod;
  final String? paidAt;
  final String pdfUrl;
  final List<PayslipLine> lines;

  bool get isPaid => paymentStatus == 'paid';

  num get net => num.tryParse(netPay) ?? 0;

  factory Payslip.fromJson(Map<String, dynamic> json) => Payslip(
    id: json['id'] as String,
    periodLabel: json['period_label'] as String,
    currency: json['currency'] as String? ?? '',
    basicSalary: json['basic_salary'].toString(),
    totalAllowances: json['total_allowances'].toString(),
    totalDeductions: json['total_deductions'].toString(),
    grossPay: json['gross_pay'].toString(),
    netPay: json['net_pay'].toString(),
    paymentStatus: json['payment_status'] as String? ?? 'pending',
    paymentMethod: json['payment_method'] as String?,
    paidAt: json['paid_at'] as String?,
    pdfUrl: json['pdf_url'] as String,
    lines: (json['lines'] as List<dynamic>? ?? const []).map((l) => PayslipLine.fromJson(l as Map<String, dynamic>)).toList(),
  );
}
