<?php

require_once __DIR__ . '/business-settings-helper.php';

function invoice_payment_method_label(string $paymentMethod): string
{
    $method = strtolower(trim($paymentMethod));
    if ($method === 'upi_manual' || $method === 'gateway') {
        return 'UPI / Bank';
    }
    if ($method === 'cod') {
        return 'Cash';
    }
    return strtoupper($method !== '' ? $method : 'NA');
}

function invoice_fetch_order(mysqli $conn, int $orderId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result ? $result->fetch_assoc() : null;
    if (!$order) {
        return null;
    }

    $itemsStmt = $conn->prepare('SELECT product_name_snapshot, quantity, unit_price, line_total FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $itemsStmt->bind_param('i', $orderId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $items = array();
    while ($itemsResult && ($row = $itemsResult->fetch_assoc())) {
        $items[] = $row;
    }

    $addressLines = array();
    $line1 = trim((string)($order['billing_address_line1'] ?? ''));
    $line2 = trim((string)($order['billing_address_line2'] ?? ''));
    $city = trim((string)($order['billing_city'] ?? ''));
    $state = trim((string)($order['billing_state'] ?? ''));
    $postal = trim((string)($order['billing_postal_code'] ?? ''));

    if ($line1 !== '') {
        $addressLines[] = $line1;
    }
    if ($line2 !== '') {
        $addressLines[] = $line2;
    }

    $cityState = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);
    if ($cityState !== '') {
        $addressLines[] = $cityState;
    }
    if ($postal !== '') {
        $addressLines[] = 'PIN: ' . $postal;
    }

    if (!$addressLines && (int)($order['user_id'] ?? 0) > 0) {
        $userId = (int)$order['user_id'];
        $addrStmt = $conn->prepare('SELECT line1, line2, city, state, postal_code FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
        $addrStmt->bind_param('i', $userId);
        $addrStmt->execute();
        $addrResult = $addrStmt->get_result();
        $addr = $addrResult ? $addrResult->fetch_assoc() : null;
        if ($addr) {
            foreach (array('line1', 'line2') as $key) {
                $val = trim((string)($addr[$key] ?? ''));
                if ($val !== '') {
                    $addressLines[] = $val;
                }
            }
            $addrCity = trim((string)($addr['city'] ?? ''));
            $addrState = trim((string)($addr['state'] ?? ''));
            $addrPostal = trim((string)($addr['postal_code'] ?? ''));
            $addrCityState = trim($addrCity . ($addrCity !== '' && $addrState !== '' ? ', ' : '') . $addrState);
            if ($addrCityState !== '') {
                $addressLines[] = $addrCityState;
            }
            if ($addrPostal !== '') {
                $addressLines[] = 'PIN: ' . $addrPostal;
            }
        }
    }

    $order['invoice_items'] = $items;
    $order['invoice_address_lines'] = $addressLines;
    return $order;
}

function invoice_render_html(array $order): string
{
    global $conn;
    
    $itemsHtml = '';
    foreach ($order['invoice_items'] as $item) {
        $name = htmlspecialchars((string)($item['product_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8');
        $qty = (int)($item['quantity'] ?? 0);
        $rate = number_format((float)($item['unit_price'] ?? 0), 2);
        $lineTotal = number_format((float)($item['line_total'] ?? 0), 2);
        $itemsHtml .= '<tr><td>' . $name . '</td><td style="text-align:center">' . $qty . '</td><td style="text-align:right">Rs ' . $rate . '</td><td style="text-align:right">Rs ' . $lineTotal . '</td></tr>';
    }

    if ($itemsHtml === '') {
        $itemsHtml = '<tr><td colspan="4" style="text-align:center">No items found</td></tr>';
    }

    $addressHtml = '';
    $addressLines = is_array($order['invoice_address_lines'] ?? null) ? $order['invoice_address_lines'] : array();
    if ($addressLines) {
        foreach ($addressLines as $line) {
            $addressHtml .= '<div>' . htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    } else {
        $addressHtml = '<div>Address not provided</div>';
    }

    $invoiceNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerPhone = htmlspecialchars((string)($order['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerEmail = htmlspecialchars((string)($order['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $createdAt = htmlspecialchars((string)($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
    $paymentMethod = htmlspecialchars(invoice_payment_method_label((string)($order['payment_method'] ?? '')), ENT_QUOTES, 'UTF-8');

    $subtotal = number_format((float)($order['subtotal'] ?? 0), 2);
    $discount = number_format((float)($order['discount_total'] ?? 0), 2);
    $tax = number_format((float)($order['tax_total'] ?? 0), 2);
    $delivery = number_format((float)($order['delivery_fee'] ?? 0), 2);
    $grandTotal = number_format((float)($order['grand_total'] ?? 0), 2);

    $businessSettings = get_business_settings($conn);
    $businessName = htmlspecialchars((string)$businessSettings['business_name'], ENT_QUOTES, 'UTF-8');
    $businessPhone = htmlspecialchars((string)$businessSettings['business_phone'], ENT_QUOTES, 'UTF-8');
    $businessEmail = htmlspecialchars((string)$businessSettings['business_email'], ENT_QUOTES, 'UTF-8');
    
    $businessAddressParts = array();
    if ($businessSettings['business_address_line1']) {
        $businessAddressParts[] = htmlspecialchars((string)$businessSettings['business_address_line1'], ENT_QUOTES, 'UTF-8');
    }
    if ($businessSettings['business_address_line2']) {
        $businessAddressParts[] = htmlspecialchars((string)$businessSettings['business_address_line2'], ENT_QUOTES, 'UTF-8');
    }
    $cityState = '';
    if ($businessSettings['business_city'] || $businessSettings['business_state']) {
        $cityState = trim($businessSettings['business_city'] . ($businessSettings['business_city'] && $businessSettings['business_state'] ? ', ' : '') . $businessSettings['business_state']);
        if ($cityState) {
            $businessAddressParts[] = htmlspecialchars($cityState, ENT_QUOTES, 'UTF-8');
        }
    }
    if ($businessSettings['business_postal_code']) {
        $businessAddressParts[] = 'PIN: ' . htmlspecialchars((string)$businessSettings['business_postal_code'], ENT_QUOTES, 'UTF-8');
    }
    $businessAddressHtml = implode('<br>', $businessAddressParts) ?: 'Address not configured';

    $gstNote = $businessSettings['business_gst_number'] ? '<div>GST: ' . htmlspecialchars((string)$businessSettings['business_gst_number'], ENT_QUOTES, 'UTF-8') . '</div>' : '';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invoice ' . $invoiceNumber . '</title><style>
      body{font-family:Arial,Helvetica,sans-serif;background:#fff;color:#111;margin:0;padding:18px}
      .invoice{max-width:860px;margin:0 auto;border:2px solid #111;padding:22px}
      .head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #111;padding-bottom:14px;margin-bottom:16px;align-items:flex-start}
      .brand{display:flex;flex-direction:column;gap:8px}
      .brand img{height:48px;width:auto;display:block}
      .brand-text{font-size:12px;color:#666}
      .meta{font-size:13px;line-height:1.5}
      .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
      .box{border:1px solid #111;padding:10px}
      .box h3{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:0.08em}
      table{width:100%;border-collapse:collapse}
      th,td{border:1px solid #111;padding:8px;font-size:13px}
      th{background:#efefef;text-transform:uppercase;letter-spacing:0.05em;font-size:11px}
      .totals{margin-top:12px;max-width:320px;margin-left:auto}
      .totals table td{padding:7px}
      .grand td{font-weight:700;font-size:15px}
      .footer{margin-top:18px;border-top:1px solid #111;padding-top:10px;font-size:12px;line-height:1.6}
      @media print{body{padding:0}.invoice{border:1px solid #111;box-shadow:none}}
    </style></head><body><div class="invoice">
      <div class="head">
        <div class="brand"><img src="/client/assets/images/mainlogo.svg" alt="' . $businessName . '"><div class="brand-text">' . $businessName . '</div></div>
        <div class="meta">
          <div><strong>Invoice #:</strong> ' . $invoiceNumber . '</div>
          <div><strong>Date:</strong> ' . $createdAt . '</div>
          <div><strong>Payment:</strong> ' . $paymentMethod . '</div>
          <div><strong>Status:</strong> PAID</div>
        </div>
      </div>
      <div class="grid">
        <div class="box"><h3>Bill To</h3><div><strong>' . $customerName . '</strong></div><div>Phone: ' . $customerPhone . '</div><div>Email: ' . $customerEmail . '</div><div style="margin-top:6px">' . $addressHtml . '</div></div>
        <div class="box"><h3>Business</h3><div><strong>' . $businessName . '</strong></div><div>' . $businessAddressHtml . '</div><div>Phone: ' . $businessPhone . '</div><div>Email: ' . $businessEmail . '</div>' . $gstNote . '</div>
      </div>
      <table><thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table>
      <div class="totals"><table>
        <tr><td>Subtotal</td><td style="text-align:right">Rs ' . $subtotal . '</td></tr>
        <tr><td>Discount</td><td style="text-align:right">Rs ' . $discount . '</td></tr>
        <tr><td>Tax</td><td style="text-align:right">Rs ' . $tax . '</td></tr>
        <tr><td>Delivery</td><td style="text-align:right">Rs ' . $delivery . '</td></tr>
        <tr class="grand"><td>Total Paid</td><td style="text-align:right">Rs ' . $grandTotal . '</td></tr>
      </table></div>
      <div class="footer">Thank you for choosing ' . $businessName . '. This is a system-generated paid invoice and does not require a physical signature.</div>
    </div></body></html>';
}

function invoice_queue_email(mysqli $conn, array $order, string $invoiceHtml): bool
{
    $recipient = trim((string)($order['customer_email'] ?? ''));
    if ($recipient === '') {
        return false;
    }

    $payload = array(
        'order_number' => (string)($order['order_number'] ?? ''),
        'customer_name' => (string)($order['customer_name'] ?? ''),
        'invoice_html' => $invoiceHtml,
        'attachments' => array(
            array(
                'filename' => 'invoice-' . preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($order['order_number'] ?? 'order')) . '.html',
                'mime_type' => 'text/html',
                'content_base64' => base64_encode($invoiceHtml),
            ),
        ),
    );
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        $payloadJson = '{}';
    }

    $orderId = (int)($order['id'] ?? 0);
    $userId = (int)($order['user_id'] ?? 0);

    $logStmt = $conn->prepare('INSERT INTO communication_logs (user_id, order_id, channel, event_key, recipient, status, payload_json) VALUES (?, ?, "email", "invoice_paid", ?, "queued", ?)');
    $logUserId = $userId > 0 ? $userId : null;
    $logOrderId = $orderId > 0 ? $orderId : null;
    $logStmt->bind_param('iiss', $logUserId, $logOrderId, $recipient, $payloadJson);
    $logStmt->execute();
    $logId = (int)$conn->insert_id;

    $queuePayload = json_encode(array('log_id' => $logId), JSON_UNESCAPED_SLASHES);
    if ($queuePayload === false) {
        $queuePayload = '{}';
    }
    $queueStmt = $conn->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (?, "email", ?)');
    $queueStmt->bind_param('is', $logId, $queuePayload);
    $queueStmt->execute();

    $jobPayload = json_encode(array(
        'log_id' => $logId,
        'channel' => 'email',
        'event_key' => 'invoice_paid',
        'recipient' => $recipient,
    ), JSON_UNESCAPED_SLASHES);
    if ($jobPayload === false) {
        $jobPayload = '{}';
    }
    $jobStmt = $conn->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("send_communication", ?, "queued", NOW(), 0)');
    $jobStmt->bind_param('s', $jobPayload);
    $jobStmt->execute();

    return true;
}
