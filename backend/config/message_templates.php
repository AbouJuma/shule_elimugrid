<?php

/*
 * The messages the system sends by itself. Each event has a default text in
 * every supported language; a school can edit any of them under
 * Communications → Templates (they are stored as rows in message_templates, one
 * per event and language, created from these defaults the first time they are
 * needed). {tokens} are replaced when the message is sent.
 *
 * Keep each text short: SMS is billed per 160-character segment (70 when the
 * text contains characters outside the basic GSM set).
 */
return [

    'events' => [

        'admission_fees' => [
            'name' => 'Admission — fees to pay',
            'category' => 'fee_reminder',
            'description' => 'Sent to the parent when a student is admitted and a fee structure exists for their class: the amount, the control number and where to pay.',
            'tokens' => ['guardian_name', 'student_name', 'admission_number', 'class', 'school_name', 'term', 'currency', 'amount', 'balance', 'control_number', 'invoice_number', 'bank_details', 'due_line'],
            'bodies' => [
                'en' => 'Dear {guardian_name}, {student_name} has been admitted to {school_name} (Adm. No. {admission_number}). School fees for {term}: {currency} {amount}. Pay using Control Number {control_number}. {bank_details} {due_line}Thank you.',
                'sw' => 'Ndugu {guardian_name}, {student_name} amesajiliwa {school_name} (Namba ya usajili {admission_number}). Ada ya shule ya {term}: {currency} {amount}. Lipa kwa Namba ya Malipo {control_number}. {bank_details} {due_line}Asante.',
            ],
        ],

        'fee_invoice' => [
            'name' => 'Fees invoice notice',
            'category' => 'fee_reminder',
            'description' => 'Sent to the parent when a fees invoice is issued for a student who is already enrolled, or when the school resends it.',
            'tokens' => ['guardian_name', 'student_name', 'admission_number', 'class', 'school_name', 'term', 'currency', 'amount', 'balance', 'control_number', 'invoice_number', 'bank_details', 'due_line'],
            'bodies' => [
                'en' => 'Dear {guardian_name}, school fees for {student_name} ({term}): {currency} {amount}, balance {currency} {balance}. Control Number {control_number}. {bank_details} {due_line}Thank you. {school_name}',
                'sw' => 'Ndugu {guardian_name}, ada ya shule ya {student_name} ({term}): {currency} {amount}, salio {currency} {balance}. Namba ya Malipo {control_number}. {bank_details} {due_line}Asante. {school_name}',
            ],
        ],

        'fee_reminder' => [
            'name' => 'Fees reminder',
            'category' => 'fee_reminder',
            'description' => 'Sent to the parent when the school sends a reminder for an unpaid or part-paid invoice.',
            'tokens' => ['guardian_name', 'student_name', 'admission_number', 'class', 'school_name', 'term', 'currency', 'amount', 'balance', 'control_number', 'invoice_number', 'bank_details', 'due_line'],
            'bodies' => [
                'en' => "Dear {guardian_name}, reminder: {student_name}'s school fees balance is {currency} {balance} (invoice {invoice_number}). Pay using Control Number {control_number}. {bank_details} {due_line}Thank you. {school_name}",
                'sw' => 'Ndugu {guardian_name}, kumbusho: salio la ada ya shule ya {student_name} ni {currency} {balance} (ankara {invoice_number}). Lipa kwa Namba ya Malipo {control_number}. {bank_details} {due_line}Asante. {school_name}',
            ],
        ],

        'payment_received' => [
            'name' => 'Payment received',
            'category' => 'fee_reminder',
            'description' => 'Sent to the parent every time a payment (including a partial one) is recorded against an invoice: what they just paid and what balance is left.',
            'tokens' => ['guardian_name', 'student_name', 'school_name', 'currency', 'amount_paid', 'balance', 'receipt_number', 'invoice_number'],
            'bodies' => [
                'en' => 'Dear {guardian_name}, we have received {currency} {amount_paid} towards {student_name}\'s school fees (receipt {receipt_number}). Balance remaining: {currency} {balance}. Thank you. {school_name}',
                'sw' => 'Ndugu {guardian_name}, tumepokea {currency} {amount_paid} kwa ada ya shule ya {student_name} (risiti {receipt_number}). Salio linalobaki: {currency} {balance}. Asante. {school_name}',
            ],
        ],

        'account_login' => [
            'name' => 'Login details',
            'category' => 'general',
            'description' => 'Sent to a parent when they are given a login (or their password is reset).',
            // {login} holds whichever identifier the account actually
            // signs in with — an email address or a phone number.
            'tokens' => ['guardian_name', 'school_name', 'login', 'password'],
            'bodies' => [
                'en' => '{school_name}: your ElimuGrid login - {login}, temporary password: {password}. Please change it after signing in.',
                'sw' => '{school_name}: akaunti yako ya ElimuGrid - {login}, nenosiri la muda: {password}. Tafadhali libadilishe baada ya kuingia.',
            ],
        ],

    ],

    /** Tokens a system message must keep, or it stops doing its job (a fees message without the control number). */
    'required_tokens' => [
        'admission_fees' => ['control_number'],
        'fee_invoice' => ['control_number'],
        'fee_reminder' => ['control_number'],
        'payment_received' => ['amount_paid', 'balance'],
        'account_login' => ['password'],
    ],

    /** What each {token} means, shown next to the template editor. */
    'tokens' => [
        'guardian_name' => "The parent's name",
        'student_name' => "The student's name",
        'admission_number' => 'Admission number',
        'class' => 'Class',
        'school_name' => 'School name',
        'term' => 'Term (or the academic year if the fees are not for a term)',
        'currency' => 'Currency, e.g. TZS',
        'amount' => 'Total fees on the invoice',
        'balance' => 'Amount still unpaid',
        'control_number' => 'The control number to pay with',
        'invoice_number' => 'Invoice number',
        'bank_details' => "The school's bank account(s), already worded: bank, account name and number (blank when none is set up)",
        'due_line' => 'The pay-by date sentence (blank when there is no due date)',
        'amount_paid' => 'The amount paid in this specific payment',
        'receipt_number' => 'The receipt number for this payment',
        'login' => 'The email or phone number the account signs in with',
        'password' => 'The temporary password',
    ],

];
