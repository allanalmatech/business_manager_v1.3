<?php
// includes/settings_schema.php
// Every setting the app understands: where it appears, how it is edited, and its default.
// Field types: text, textarea, number, switch, select, password, theme.
// Adding a setting here is all it takes to show it on the Settings page.

return [
    'business' => ['label' => 'Business', 'icon' => 'bi-shop', 'intro' => 'Shown on receipts, invoices and emails.', 'fields' => [
        'business_name'    => ['label' => 'Business name', 'type' => 'text', 'default' => 'Business Manager'],
        'business_address' => ['label' => 'Address', 'type' => 'textarea', 'default' => ''],
        'business_phone'   => ['label' => 'Phone', 'type' => 'text', 'default' => ''],
        'business_email'   => ['label' => 'Email', 'type' => 'text', 'default' => '', 'help' => 'Also used as the "from" address for emails.'],
        'business_website' => ['label' => 'Website', 'type' => 'text', 'default' => ''],
        'business_tax_id'  => ['label' => 'Tax ID (TIN)', 'type' => 'text', 'default' => ''],
    ]],
    'money' => ['label' => 'Money & tax', 'icon' => 'bi-cash-coin', 'intro' => 'Currency display and sales tax.', 'fields' => [
        'currency_code'       => ['label' => 'Currency code', 'type' => 'text', 'default' => 'UGX', 'max' => 3],
        'currency_symbol'     => ['label' => 'Currency symbol', 'type' => 'text', 'default' => 'UGX ', 'help' => 'Printed before amounts.'],
        'decimal_places'      => ['label' => 'Decimal places', 'type' => 'select', 'default' => '0', 'options' => ['0' => 'None (1,500)', '2' => 'Two (1,500.00)']],
        'tax_enabled'         => ['label' => 'Charge tax on sales', 'type' => 'switch', 'default' => '0'],
        'tax_name'            => ['label' => 'Tax name', 'type' => 'text', 'default' => 'VAT'],
        'tax_rate'            => ['label' => 'Tax rate (%)', 'type' => 'number', 'default' => '18', 'min' => 0, 'max' => 100, 'step' => '0.01'],
        'prices_include_tax'  => ['label' => 'Prices already include tax', 'type' => 'switch', 'default' => '1', 'help' => 'On: tax is shown as part of the price. Off: tax is added on top.'],
    ]],
    'sales' => ['label' => 'Selling', 'icon' => 'bi-cart-check', 'intro' => 'Rules at the till.', 'fields' => [
        'shift_required'      => ['label' => 'Cashiers must open a shift before selling', 'type' => 'switch', 'default' => '0'],
        'credit_limit_enforced' => ['label' => 'Block credit sales over a customer\'s limit', 'type' => 'switch', 'default' => '1'],
        'low_stock_default'   => ['label' => 'Default reorder level for new products', 'type' => 'number', 'default' => '0', 'min' => 0, 'step' => '1'],
    ]],
    'receipts' => ['label' => 'Receipts & printing', 'icon' => 'bi-printer', 'intro' => 'What the customer takes away.', 'fields' => [
        'receipt_header'      => ['label' => 'Header text', 'type' => 'text', 'default' => ''],
        'receipt_footer'      => ['label' => 'Footer text', 'type' => 'text', 'default' => 'Thank you for your purchase!'],
        'receipt_width'       => ['label' => 'Paper width', 'type' => 'select', 'default' => '80', 'options' => ['58' => '58 mm', '80' => '80 mm']],
        'auto_print_receipt'  => ['label' => 'Print automatically after a sale', 'type' => 'switch', 'default' => '1'],
        'auto_open_drawer'    => ['label' => 'Open the cash drawer after a sale', 'type' => 'switch', 'default' => '0'],
        'show_customer_copy'  => ['label' => 'Offer a customer copy', 'type' => 'switch', 'default' => '1'],
        'show_cashier_copy'   => ['label' => 'Offer a cashier copy', 'type' => 'switch', 'default' => '1'],
    ]],
    'email' => ['label' => 'Email', 'icon' => 'bi-envelope', 'intro' => 'How the system sends email (password resets, reminders, documents).', 'fields' => [
        'mail_transport' => ['label' => 'Send using', 'type' => 'select', 'default' => 'mail', 'options' => ['mail' => 'This server (PHP mail)', 'smtp' => 'An SMTP account']],
        'smtp_host'      => ['label' => 'SMTP server', 'type' => 'text', 'default' => '', 'show_if' => ['mail_transport' => 'smtp']],
        'smtp_port'      => ['label' => 'Port', 'type' => 'number', 'default' => '587', 'min' => 1, 'max' => 65535, 'step' => '1', 'show_if' => ['mail_transport' => 'smtp']],
        'smtp_secure'    => ['label' => 'Encryption', 'type' => 'select', 'default' => 'tls', 'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'], 'show_if' => ['mail_transport' => 'smtp']],
        'smtp_user'      => ['label' => 'Username', 'type' => 'text', 'default' => '', 'show_if' => ['mail_transport' => 'smtp']],
        'smtp_pass'      => ['label' => 'Password', 'type' => 'password', 'default' => '', 'show_if' => ['mail_transport' => 'smtp']],
    ]],
    'sms' => ['label' => 'SMS', 'icon' => 'bi-chat-left-text', 'intro' => 'Text messages for reminders and receipts.', 'fields' => [
        'sms_provider'  => ['label' => 'Provider', 'type' => 'select', 'default' => 'none', 'options' => ['none' => 'Off', 'africastalking' => "Africa's Talking", 'egosms' => 'EgoSMS', 'http' => 'Other (HTTP link)']],
        'sms_username'  => ['label' => 'Account username', 'type' => 'text', 'default' => '', 'show_if' => ['sms_provider' => ['africastalking', 'egosms']]],
        'sms_api_key'   => ['label' => 'API key or password', 'type' => 'password', 'default' => '', 'show_if' => ['sms_provider' => ['africastalking', 'egosms']]],
        'sms_sender_id' => ['label' => 'Sender name', 'type' => 'text', 'default' => '', 'max' => 11, 'show_if' => ['sms_provider' => ['africastalking', 'egosms']]],
        'sms_http_url'  => ['label' => 'Send link', 'type' => 'text', 'default' => '', 'help' => 'Use {to} and {message} where the number and text go.', 'show_if' => ['sms_provider' => 'http']],
    ]],
    'reminders' => ['label' => 'Reminders', 'icon' => 'bi-alarm', 'intro' => 'Automatic messages about money owed. Needs the scheduled job in docs/INSTALL.md.', 'fields' => [
        'reminders_enabled'     => ['label' => 'Send reminders automatically', 'type' => 'switch', 'default' => '0'],
        'reminder_channel'      => ['label' => 'Send by', 'type' => 'select', 'default' => 'sms', 'options' => ['sms' => 'SMS', 'email' => 'Email', 'both' => 'SMS and email']],
        'reminder_days_before'  => ['label' => 'Days before an installment is due', 'type' => 'number', 'default' => '2', 'min' => 0, 'max' => 30, 'step' => '1'],
        'reminder_unpaid_days'  => ['label' => 'Remind about unpaid documents every (days)', 'type' => 'number', 'default' => '7', 'min' => 1, 'max' => 90, 'step' => '1'],
    ]],
    'appearance' => ['label' => 'Appearance', 'icon' => 'bi-palette', 'intro' => 'Every theme is checked for readable contrast.', 'fields' => [
        'app_theme' => ['label' => 'Theme', 'type' => 'theme', 'default' => 'default'],
    ]],
];
