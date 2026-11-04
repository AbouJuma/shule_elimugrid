/// Mirrors App\Http\Resources\InvoiceResource / PaymentResource. Money
/// fields are always JSON strings on this API (explicit `(string) $amount`
/// casts server-side, to avoid float precision loss) — parsed here with
/// `num.parse`, never treated as native JSON numbers.
class InvoiceStudent {
  const InvoiceStudent({required this.id, required this.name, required this.admissionNumber});

  final String id;
  final String name;
  final String admissionNumber;

  factory InvoiceStudent.fromJson(Map<String, dynamic> json) => InvoiceStudent(
    id: json['id'] as String,
    name: json['name'] as String,
    admissionNumber: json['admission_number'] as String,
  );
}

class InvoiceItem {
  const InvoiceItem({required this.name, required this.amount});

  final String name;
  final num amount;

  factory InvoiceItem.fromJson(Map<String, dynamic> json) =>
      InvoiceItem(name: json['name'] as String, amount: num.parse(json['amount'] as String));
}

class PaymentModel {
  const PaymentModel({
    required this.id,
    required this.receiptNumber,
    required this.amount,
    required this.method,
    this.reference,
    required this.paidOn,
    this.recordedBy,
  });

  final String id;
  final String receiptNumber;
  final num amount;
  final String method;
  final String? reference;
  final String? paidOn;
  final String? recordedBy;

  factory PaymentModel.fromJson(Map<String, dynamic> json) => PaymentModel(
    id: json['id'] as String,
    receiptNumber: json['receipt_number'] as String,
    amount: num.parse(json['amount'] as String),
    method: json['method'] as String,
    reference: json['reference'] as String?,
    paidOn: json['paid_on'] as String?,
    recordedBy: json['recorded_by'] as String?,
  );
}

class InvoiceModel {
  const InvoiceModel({
    required this.id,
    required this.invoiceNumber,
    this.controlNumber,
    this.student,
    required this.totalAmount,
    required this.amountPaid,
    required this.balance,
    required this.status,
    this.dueDate,
    this.items = const [],
    this.payments = const [],
  });

  final String id;
  final String invoiceNumber;
  final String? controlNumber;
  final InvoiceStudent? student;
  final num totalAmount;
  final num amountPaid;
  final num balance;
  final String status;
  final String? dueDate;
  final List<InvoiceItem> items;
  final List<PaymentModel> payments;

  factory InvoiceModel.fromJson(Map<String, dynamic> json) => InvoiceModel(
    id: json['id'] as String,
    invoiceNumber: json['invoice_number'] as String,
    controlNumber: json['control_number'] as String?,
    student: json['student'] != null ? InvoiceStudent.fromJson(json['student'] as Map<String, dynamic>) : null,
    totalAmount: num.parse(json['total_amount'] as String),
    amountPaid: num.parse(json['amount_paid'] as String),
    balance: num.parse(json['balance'] as String),
    status: json['status'] as String,
    dueDate: json['due_date'] as String?,
    items: (json['items'] as List<dynamic>? ?? const [])
        .map((i) => InvoiceItem.fromJson(i as Map<String, dynamic>))
        .toList(),
    payments: (json['payments'] as List<dynamic>? ?? const [])
        .map((p) => PaymentModel.fromJson(p as Map<String, dynamic>))
        .toList(),
  );
}
