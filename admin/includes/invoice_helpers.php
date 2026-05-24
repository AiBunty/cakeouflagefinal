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

    $itemsStmt = $conn->prepare('SELECT product_name_snapshot, variant_snapshot, quantity, unit_price, line_total, cake_message, topper_name_snapshot, topper_price_snapshot, customisation_note FROM order_items WHERE order_id = ? ORDER BY id ASC');
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

function invoice_is_fully_paid(array $order): bool
{
    $paymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
    if ($paymentStatus !== 'paid') {
        return false;
    }

    $grandTotal = (float)($order['grand_total'] ?? 0);
    if ($grandTotal <= 0.0001) {
        return false;
    }

    $balanceDue = (float)($order['balance_due_amount'] ?? 0);
    if ($balanceDue > 0.01) {
        return false;
    }

    // If the order was configured for advance collection, invoice must stay locked
    // until the collected amount reaches full order total.
    $plannedAdvance = (float)($order['advance_amount'] ?? 0);
    if ($plannedAdvance > 0.0001 && $plannedAdvance < $grandTotal) {
        $advanceReceived = (float)($order['advance_received_amount'] ?? 0);
        $netCollected = (float)($order['net_collected_amount'] ?? 0);
        $effectiveCollected = max($advanceReceived, $netCollected);
        if ($effectiveCollected > 0.0001 && $effectiveCollected + 0.01 < $grandTotal) {
            return false;
        }

        $collectionStatus = strtolower(trim((string)($order['collection_status'] ?? '')));
        if (in_array($collectionStatus, array('advance_paid', 'payment_pending', 'overdue'), true)) {
            return false;
        }
    }

    return true;
}

function payment_receipt_is_eligible(array $order): bool
{
    if (!invoice_partial_payment_enabled()) {
        return false;
    }

    if (invoice_is_fully_paid($order)) {
        return false;
    }

    $advanceReceived = (float)($order['advance_received_amount'] ?? 0);
    $netCollected = (float)($order['net_collected_amount'] ?? 0);
    $plannedAdvance = (float)($order['advance_amount'] ?? 0);

    return max($advanceReceived, $netCollected, $plannedAdvance) > 0.0001;
}

function invoice_partial_payment_enabled(): bool
{
    global $conn;
    global $INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE;

    if (is_array($INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE ?? null) && array_key_exists('allow_partial_payment', $INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE)) {
        return (string)$INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE['allow_partial_payment'] !== '0';
    }

    if (!($conn instanceof mysqli)) {
        return true;
    }

    $settings = get_business_settings($conn);
    return (string)($settings['allow_partial_payment'] ?? '1') !== '0';
}

function payment_receipt_received_amount(array $order, ?array $receipt = null): float
{
    if (is_array($receipt) && isset($receipt['amount'])) {
        return round((float)$receipt['amount'], 2);
    }

    $advanceReceived = (float)($order['advance_received_amount'] ?? 0);
    $netCollected = (float)($order['net_collected_amount'] ?? 0);
    $plannedAdvance = (float)($order['advance_amount'] ?? 0);

    return max($advanceReceived, $netCollected, $plannedAdvance);
}

function payment_receipt_balance_due(array $order, ?array $receipt = null): float
{
    if (is_array($receipt) && isset($receipt['balance_due'])) {
        return round((float)$receipt['balance_due'], 2);
    }

    $grandTotal = (float)($order['grand_total'] ?? 0);
    $receivedAmount = payment_receipt_received_amount($order, $receipt);
    $balanceDue = (float)($order['balance_due_amount'] ?? 0);
    if ($balanceDue <= 0.0001 && $grandTotal > 0.0001) {
        $balanceDue = max(0, $grandTotal - $receivedAmount);
    }

    return $balanceDue;
}

function invoice_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function invoice_money($value): string
{
    return 'Rs ' . number_format((float)$value, 2);
}

function invoice_format_datetime(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return 'NA';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return invoice_escape($raw);
    }

    return date('d M Y, h:i A', $timestamp);
}

function invoice_format_date_only(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return 'NA';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return invoice_escape($raw);
    }

    return date('d M Y', $timestamp);
}

function invoice_operational_tags(array $order): array
{
    $tags = array();

    $paymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
    if ($paymentStatus === 'paid' || $paymentStatus === 'credit') {
        $tags[] = array('label' => 'PAID', 'class' => 'tag tag--ok');
    } elseif ($paymentStatus === 'partially_refunded' || $paymentStatus === 'refunded') {
        $tags[] = array('label' => 'REFUNDED', 'class' => 'tag tag--warn');
    }

    $fulfilment = strtolower(trim((string)($order['fulfilment_mode'] ?? '')));
    if ($fulfilment === 'pickup') {
        $tags[] = array('label' => 'PICKUP', 'class' => 'tag');
    } elseif ($fulfilment === 'delivery' || $fulfilment === 'custom_delivery') {
        $tags[] = array('label' => 'DELIVERY', 'class' => 'tag');
    }

    $advance = (float)($order['advance_received_amount'] ?? $order['advance_amount'] ?? 0);
    if ($advance > 0.0001) {
        $tags[] = array('label' => 'ADVANCE', 'class' => 'tag');
    }

    $totalRefunded = (float)($order['total_refunded'] ?? $order['refund_amount'] ?? 0);
    if ($totalRefunded > 0.0001) {
        $tags[] = array('label' => 'REFUND ' . invoice_money($totalRefunded), 'class' => 'tag tag--warn');
    }

    return $tags;
}

function invoice_normalize_item(array $item, int $index): array
{
    $name = trim((string)($item['product_name_snapshot'] ?? 'Item ' . $index));
    if ($name === '') {
        $name = 'Item ' . $index;
    }

    $qty = max(1, (int)($item['quantity'] ?? 1));
    $rate = (float)($item['unit_price'] ?? 0);
    $lineTotal = (float)($item['line_total'] ?? 0);
    $variantSnapshot = trim((string)($item['variant_snapshot'] ?? ''));
    $cakeMessage = trim((string)($item['cake_message'] ?? ''));
    $topperName = trim((string)($item['topper_name_snapshot'] ?? ''));
    $topperPrice = (float)($item['topper_price_snapshot'] ?? 0);
    $customNote = trim((string)($item['customisation_note'] ?? ''));

    $chips = array();
    $notes = array();

    if ($variantSnapshot !== '') {
        $chips[] = 'Variant: ' . $variantSnapshot;
        if (preg_match('/(\d+(?:\.\d+)?\s?(?:kg|g|gram|lb))/i', $variantSnapshot, $weightMatch)) {
            $chips[] = 'Weight: ' . $weightMatch[1];
        }
        if (preg_match('/(eggless|chocolate|vanilla|red velvet|butterscotch|strawberry|black forest|pineapple)/i', $variantSnapshot, $flavourMatch)) {
            $chips[] = 'Flavour: ' . ucfirst(strtolower($flavourMatch[1]));
        }
    }

    if ($cakeMessage !== '') {
        $chips[] = 'Message: ' . $cakeMessage;
    }

    if ($topperName !== '' && strtolower($topperName) !== 'no topper') {
        $chips[] = 'Topper: ' . $topperName . ($topperPrice > 0 ? ' (+' . invoice_money($topperPrice) . ')' : '');
    }

    if ($customNote !== '') {
        $notes[] = $customNote;
    }

    return array(
        'name' => $name,
        'qty' => $qty,
        'rate' => $rate,
        'line_total' => $lineTotal,
        'chips' => array_values(array_unique($chips)),
        'notes' => $notes,
    );
}

function invoice_render_item_block(array $item): string
{
    $chipsHtml = '';
    if (!empty($item['chips']) && is_array($item['chips'])) {
        $chipsHtml .= '<div class="item-chips">';
        foreach ($item['chips'] as $chip) {
            $chipsHtml .= '<span class="item-chip">' . invoice_escape($chip) . '</span>';
        }
        $chipsHtml .= '</div>';
    }

    $notesHtml = '';
    if (!empty($item['notes']) && is_array($item['notes'])) {
        foreach ($item['notes'] as $note) {
            $notesHtml .= '<div class="item-note">Note: ' . invoice_escape($note) . '</div>';
        }
    }

    return '<div class="item-block">'
        . '<div class="item-line1">'
        . '<div class="item-name">' . invoice_escape($item['name']) . ' x' . (int)$item['qty'] . '</div>'
        . '<div class="item-rate">' . invoice_money($item['rate']) . '</div>'
        . '<div class="item-amount">' . invoice_money($item['line_total']) . '</div>'
        . '</div>'
        . $chipsHtml
        . $notesHtml
        . '</div>';
}

function invoice_render_tag_row(array $tags): string
{
    if (!$tags) {
        return '';
    }

    $html = '<div class="tag-row">';
    foreach ($tags as $tag) {
        $className = invoice_escape((string)($tag['class'] ?? 'tag'));
        $label = invoice_escape((string)($tag['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $html .= '<span class="' . $className . '">' . $label . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function invoice_render_finance_table(array $finance): string
{
    $rows = '';
    foreach ($finance as $row) {
        $key = invoice_escape((string)($row['label'] ?? ''));
        $value = invoice_escape((string)($row['value'] ?? ''));
        $className = invoice_escape((string)($row['class'] ?? ''));
        $rows .= '<tr class="' . $className . '"><td class="finance-key">' . $key . '</td><td class="finance-value">' . $value . '</td></tr>';
    }

    return '<table class="finance-table">' . $rows . '</table>';
}

function invoice_render_copy(array $context): string
{
    $business = $context['business'];
    $order = $context['order'];
    $customerAddressHtml = $context['customer_address_html'];
    $pageIndex = (int)$context['page_index'];
    $pageCount = (int)$context['page_count'];
    $copyLabel = (string)$context['copy_label'];
    $isFinalPage = (bool)$context['is_final_page'];
    $chunkItems = $context['chunk_items'];
    $showTotals = (bool)$context['show_totals'];
    $financeTable = (string)$context['finance_table'];
    $tagsHtml = (string)$context['tags_html'];
    $generatedAt = (string)$context['generated_at'];

    $itemsBody = '';
    foreach ($chunkItems as $item) {
        $itemsBody .= invoice_render_item_block($item);
    }
    if ($itemsBody === '') {
        $itemsBody = '<div class="item-block"><div class="item-line1"><div class="item-name">No items</div><div class="item-rate">-</div><div class="item-amount">-</div></div></div>';
    }

    $continuedText = $isFinalPage
        ? 'Final page. Totals and balance summary are shown for this invoice.'
        : 'Continued Invoice. Remaining items and totals will appear on the next page.';

    $footerRight = 'Generated: ' . invoice_escape($generatedAt) . '<br>QR: Manual verification at counter';

    return '<article class="invoice-copy">'
        . '<div class="copy-topline">'
        . '<div class="copy-label">' . invoice_escape($copyLabel) . '</div>'
        . '<div class="copy-page">Page ' . $pageIndex . ' of ' . $pageCount . '</div>'
        . '</div>'
        . '<header class="invoice-header">'
        . '<div>'
        . ($business['logo_url'] !== '' ? '<img class="brand-logo" src="' . invoice_escape($business['logo_url']) . '" alt="' . invoice_escape($business['name']) . '">' : '')
        . '</div>'
        . '<div>'
        . '<div class="brand-name">' . invoice_escape($business['name']) . '</div>'
        . '<div class="brand-address">' . $business['address_html'] . '</div>'
        . '<div class="brand-meta">Phone: ' . invoice_escape($business['phone']) . ' | Email: ' . invoice_escape($business['email']) . '</div>'
        . ($business['gst'] !== '' ? '<div class="brand-meta">GST: ' . invoice_escape($business['gst']) . '</div>' : '')
        . '</div>'
        . '<div class="invoice-meta">'
        . '<div class="meta-line"><strong>Invoice:</strong> ' . invoice_escape((string)($order['order_number'] ?? 'NA')) . '</div>'
        . '<div class="meta-line"><strong>Order Date:</strong> ' . invoice_escape(invoice_format_datetime((string)($order['created_at'] ?? ''))) . '</div>'
        . '<div class="meta-line"><strong>Payment:</strong> ' . invoice_escape(invoice_payment_method_label((string)($order['payment_method'] ?? ''))) . '</div>'
        . '<div class="meta-line"><strong>Status:</strong> ' . invoice_escape(strtoupper((string)($order['payment_status'] ?? 'pending'))) . '</div>'
        . '<div class="meta-line"><strong>Collection Due:</strong> ' . invoice_escape(invoice_format_date_only((string)($order['collection_due_date'] ?? ''))) . '</div>'
        . $tagsHtml
        . '</div>'
        . '</header>'
        . '<section class="invoice-customer">'
        . '<div class="customer-block">'
        . '<h4 class="block-title">Bill To</h4>'
        . '<div class="customer-name">' . invoice_escape((string)($order['customer_name'] ?? 'Guest Customer')) . '</div>'
        . '<div class="customer-meta">Phone: ' . invoice_escape((string)($order['customer_phone'] ?? 'NA')) . '</div>'
        . '<div class="customer-meta">Email: ' . invoice_escape((string)($order['customer_email'] ?? 'NA')) . '</div>'
        . '<div class="customer-meta">' . $customerAddressHtml . '</div>'
        . '</div>'
        . '<div class="customer-block">'
        . '<h4 class="block-title">Fulfilment</h4>'
        . '<div class="customer-meta">Mode: ' . invoice_escape(strtoupper((string)($order['fulfilment_mode'] ?? 'delivery'))) . '</div>'
        . '<div class="customer-meta">Order Status: ' . invoice_escape(strtoupper((string)($order['order_status'] ?? 'pending'))) . '</div>'
        . '<div class="customer-meta">Collection: ' . invoice_escape(strtoupper((string)($order['collection_status'] ?? 'payment_pending'))) . '</div>'
        . '<div class="customer-meta">Settlement Ref: ' . invoice_escape((string)($order['settlement_reference'] ?? 'NA')) . '</div>'
        . '</div>'
        . '</section>'
        . '<section class="invoice-items">'
        . '<div class="items-head"><div>Item Description</div><div style="text-align:right">Rate</div><div style="text-align:right">Amount</div></div>'
        . $itemsBody
        . '</section>'
        . '<section class="invoice-summary">'
        . '<div class="continued-note">' . invoice_escape($continuedText) . '</div>'
        . ($showTotals ? $financeTable : '')
        . '</section>'
        . '<footer class="invoice-footer">'
        . '<div class="footer-left">'
        . '<div>Thank you for choosing ' . invoice_escape($business['name']) . '.</div>'
        . '<div>This is a system generated invoice and does not require signature.</div>'
        . '</div>'
        . '<div class="footer-right">' . $footerRight . '</div>'
        . '</footer>'
        . '</article>';
}

function invoice_get_print_css(): string
{
    $cssPath = dirname(__DIR__) . '/assets/invoice-print.css';
    $css = '';
    if (is_file($cssPath)) {
        $loaded = @file_get_contents($cssPath);
        if (is_string($loaded)) {
            $css = $loaded;
        }
    }

    if ($css === '') {
        $css = 'body{font-family:Arial,sans-serif;font-size:12px;color:#111}';
    }

    return $css;
}

function invoice_render_html(array $order): string
{
    global $conn;
    global $INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE;

    $normalizedItems = array();
    $rawItems = is_array($order['invoice_items'] ?? null) ? $order['invoice_items'] : array();
    foreach ($rawItems as $idx => $item) {
        $normalizedItems[] = invoice_normalize_item((array)$item, $idx + 1);
    }
    if (!$normalizedItems) {
        $normalizedItems[] = invoice_normalize_item(array('product_name_snapshot' => 'No items found', 'quantity' => 1, 'unit_price' => 0, 'line_total' => 0), 1);
    }

    $totalItems = count($normalizedItems);

    // Layout decision: ≤4 items → both copies share one A4 (half-page each)
    //                 >4 items → each copy gets its own full A4 page
    $useFullPageLayout = $totalItems > 4;
    $itemsPerCopy = $useFullPageLayout ? 8 : 4;
    $sheetClass = $useFullPageLayout ? 'invoice-sheet invoice-sheet--full' : 'invoice-sheet invoice-sheet--half';

    $itemChunks = array_chunk($normalizedItems, $itemsPerCopy);
    if (!$itemChunks) {
        $itemChunks = array(array());
    }

    $addressHtml = '';
    $addressLines = is_array($order['invoice_address_lines'] ?? null) ? $order['invoice_address_lines'] : array();
    if ($addressLines) {
        foreach ($addressLines as $line) {
            $addressHtml .= '<div>' . invoice_escape($line) . '</div>';
        }
    } else {
        $addressHtml = '<div>Address not provided</div>';
    }

    $businessSettings = is_array($INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE ?? null)
        ? $INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE
        : get_business_settings($conn);

    // Read duplicate copy setting — default on
    $duplicateCopyEnabled = (string)($businessSettings['invoice_duplicate_copy'] ?? 'on') !== 'off';

    $businessAddressParts = array();
    if (!empty($businessSettings['business_address_line1'])) {
        $businessAddressParts[] = invoice_escape($businessSettings['business_address_line1']);
    }
    if (!empty($businessSettings['business_address_line2'])) {
        $businessAddressParts[] = invoice_escape($businessSettings['business_address_line2']);
    }
    $businessCityState = trim((string)$businessSettings['business_city'] . ((string)$businessSettings['business_city'] !== '' && (string)$businessSettings['business_state'] !== '' ? ', ' : '') . (string)$businessSettings['business_state']);
    if ($businessCityState !== '') {
        $businessAddressParts[] = invoice_escape($businessCityState);
    }
    if (!empty($businessSettings['business_postal_code'])) {
        $businessAddressParts[] = 'PIN: ' . invoice_escape((string)$businessSettings['business_postal_code']);
    }

    $invoiceLogoUrl = trim((string)($businessSettings['email_logo_url'] ?? ''));
    if ($invoiceLogoUrl === '') {
        $invoiceLogoUrl = '/client/assets/images/mainlogo.svg';
    }

    $business = array(
        'name' => (string)($businessSettings['business_name'] ?? 'Cakeouflage'),
        'phone' => (string)($businessSettings['business_phone'] ?? 'NA'),
        'email' => (string)($businessSettings['business_email'] ?? 'NA'),
        'gst' => (string)($businessSettings['business_gst_number'] ?? ''),
        'address_html' => $businessAddressParts ? implode('<br>', $businessAddressParts) : 'Address not configured',
        'logo_url' => $invoiceLogoUrl,
    );

    $subtotal = (float)($order['subtotal'] ?? 0);
    $discount = (float)($order['discount_total'] ?? 0);
    $tax = (float)($order['tax_total'] ?? 0);
    $delivery = (float)($order['delivery_fee'] ?? 0);
    $grandTotal = (float)($order['grand_total'] ?? 0);
    $advance = (float)($order['advance_received_amount'] ?? $order['advance_amount'] ?? 0);
    $netCollected = (float)($order['net_collected_amount'] ?? 0);
    $balanceDue = (float)($order['balance_due_amount'] ?? 0);
    $totalRefunded = (float)($order['total_refunded'] ?? $order['refund_amount'] ?? 0);

    $financeRows = array(
        array('label' => 'Subtotal', 'value' => invoice_money($subtotal), 'class' => ''),
        array('label' => 'Less Coupon Discount', 'value' => '- ' . invoice_money($discount), 'class' => ''),
        array('label' => 'Less Advance', 'value' => '- ' . invoice_money($advance), 'class' => ''),
        array('label' => 'Tax', 'value' => invoice_money($tax), 'class' => ''),
        array('label' => 'Delivery', 'value' => invoice_money($delivery), 'class' => ''),
        array('label' => 'Total Amount', 'value' => invoice_money($grandTotal), 'class' => 'finance-row--total'),
    );

    if ($netCollected > 0.0001) {
        $financeRows[] = array('label' => 'Net Collected', 'value' => invoice_money($netCollected), 'class' => '');
    }
    if ($totalRefunded > 0.0001) {
        $financeRows[] = array('label' => 'Total Refunded', 'value' => invoice_money($totalRefunded), 'class' => '');
    }
    $financeRows[] = array('label' => 'Balance Due', 'value' => invoice_money($balanceDue), 'class' => 'finance-row--balance');

    $financeTable = invoice_render_finance_table($financeRows);
    $tagsHtml = invoice_render_tag_row(invoice_operational_tags($order));
    $generatedAt = date('d M Y, h:i A');

    $pageCount = count($itemChunks);
    $sheetHtml = '';

    if ($useFullPageLayout) {
        // Full-page mode: all Original sheets, then all Duplicate sheets
        // Each copy series has its own page numbering (Page X of Y)
        foreach (array('ORIGINAL COPY', 'DUPLICATE COPY') as $copyLabel) {
            if ($copyLabel === 'DUPLICATE COPY' && !$duplicateCopyEnabled) {
                continue;
            }
            foreach ($itemChunks as $chunkIndex => $chunk) {
                $pageIndex = $chunkIndex + 1;
                $isFinalPage = ($pageIndex === $pageCount);

                $ctx = array(
                    'business' => $business,
                    'order' => $order,
                    'customer_address_html' => $addressHtml,
                    'page_index' => $pageIndex,
                    'page_count' => $pageCount,
                    'copy_label' => $copyLabel,
                    'is_final_page' => $isFinalPage,
                    'chunk_items' => $chunk,
                    'show_totals' => $isFinalPage,
                    'finance_table' => $financeTable,
                    'tags_html' => $tagsHtml,
                    'generated_at' => $generatedAt,
                );

                $sheetHtml .= '<section class="' . invoice_escape($sheetClass) . '">'
                    . invoice_render_copy($ctx)
                    . '</section>';
            }
        }
    } else {
        // Half-page mode: Original + Duplicate stacked on the same A4 per chunk
        foreach ($itemChunks as $chunkIndex => $chunk) {
            $pageIndex = $chunkIndex + 1;
            $isFinalPage = ($pageIndex === $pageCount);

            $baseCtx = array(
                'business' => $business,
                'order' => $order,
                'customer_address_html' => $addressHtml,
                'page_index' => $pageIndex,
                'page_count' => $pageCount,
                'is_final_page' => $isFinalPage,
                'chunk_items' => $chunk,
                'show_totals' => $isFinalPage,
                'finance_table' => $financeTable,
                'tags_html' => $tagsHtml,
                'generated_at' => $generatedAt,
            );

            $topCtx = $baseCtx;
            $topCtx['copy_label'] = 'ORIGINAL COPY';

            $sheetContent = invoice_render_copy($topCtx);

            if ($duplicateCopyEnabled) {
                $botCtx = $baseCtx;
                $botCtx['copy_label'] = 'DUPLICATE COPY';
                $sheetContent .= '<div class="invoice-separator"></div>'
                    . invoice_render_copy($botCtx);
            }

            $sheetHtml .= '<section class="' . invoice_escape($sheetClass) . '">'
                . $sheetContent
                . '</section>';
        }
    }

    $css = invoice_get_print_css();

    return '<!DOCTYPE html>'
        . '<html>'
        . '<head>'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Invoice ' . invoice_escape((string)($order['order_number'] ?? 'NA')) . '</title>'
        . '<style>' . $css . '</style>'
        . '</head>'
        . '<body>'
        . '<main class="invoice-engine">' . $sheetHtml . '</main>'
        . '</body>'
        . '</html>';
}

function payment_receipt_render_html(array $order, ?array $receipt = null): string
{
    global $conn;
    global $INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE;
    $businessSettings = is_array($INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE ?? null)
        ? $INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE
        : get_business_settings($conn);

    $businessName = (string)($businessSettings['business_name'] ?? 'Cakeouflage');
    $businessPhone = (string)($businessSettings['business_phone'] ?? 'NA');
    $businessEmail = (string)($businessSettings['business_email'] ?? 'NA');

    $grandTotal = (float)($order['grand_total'] ?? 0);
    $receivedAmount = payment_receipt_received_amount($order, $receipt);
    $balanceDue = payment_receipt_balance_due($order, $receipt);

    $receiptNumber = trim((string)($receipt['receipt_number'] ?? ''));
    if ($receiptNumber === '') {
        $receiptNumber = 'PR-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string)($order['order_number'] ?? ('ORD-' . (int)($order['id'] ?? 0)))) . '-01';
    }
    $generatedAt = invoice_format_datetime((string)($receipt['issued_at'] ?? ''));
    if ($generatedAt === 'NA') {
        $generatedAt = date('d M Y, h:i A');
    }

    $paymentMethodLabel = invoice_payment_method_label((string)($receipt['payment_method'] ?? ($order['payment_method'] ?? 'NA')));
    $paymentStatus = strtoupper((string)($receipt['payment_status_snapshot'] ?? ($order['payment_status'] ?? 'pending')));
    $collectionStatus = strtoupper((string)($receipt['collection_status_snapshot'] ?? ($order['collection_status'] ?? 'payment_pending')));

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Payment Receipt ' . invoice_escape($receiptNumber) . '</title>'
        . '<style>'
        . 'body{font-family:Segoe UI,Tahoma,Arial,sans-serif;font-size:11px;color:#111;margin:0;padding:10px;background:#f6f6f6}'
        . '.sheet{width:210mm;max-width:100%;margin:0 auto;background:#fff;border:1px solid #d2d2d2;padding:10mm;box-sizing:border-box}'
        . '.head{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #dcdcdc;padding-bottom:8px;margin-bottom:8px}'
        . '.title{font-size:14px;font-weight:700}.sub{color:#555;font-size:10px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}'
        . '.card{border:1px solid #dcdcdc;padding:8px}.k{color:#666;font-size:10px}.v{font-weight:600}.tbl{width:100%;border-collapse:collapse;margin-top:8px}'
        . '.tbl td{border:1px solid #e5e5e5;padding:6px 8px}.tbl td:last-child{text-align:right;font-weight:600}'
        . '.total td{font-size:12px;font-weight:700}.footer{margin-top:10px;font-size:10px;color:#555;border-top:1px solid #dcdcdc;padding-top:8px}'
        . '@media print{@page{size:A4 portrait;margin:8mm}body{background:#fff;padding:0}.sheet{border:0;padding:0;width:auto}}'
        . '</style></head><body><main class="sheet">'
        . '<div class="head"><div><div class="title">Payment Receipt</div><div class="sub">' . invoice_escape($businessName) . '</div><div class="sub">Phone: ' . invoice_escape($businessPhone) . ' | Email: ' . invoice_escape($businessEmail) . '</div></div>'
        . '<div style="text-align:right"><div class="sub"><strong>Receipt No:</strong> ' . invoice_escape($receiptNumber) . '</div><div class="sub"><strong>Order:</strong> ' . invoice_escape((string)($order['order_number'] ?? 'NA')) . '</div><div class="sub"><strong>Issued:</strong> ' . invoice_escape($generatedAt) . '</div></div></div>'
        . '<div class="grid">'
        . '<div class="card"><div class="k">Received From</div><div class="v">' . invoice_escape((string)($order['customer_name'] ?? 'Customer')) . '</div><div class="k">Phone: ' . invoice_escape((string)($order['customer_phone'] ?? 'NA')) . '</div><div class="k">Email: ' . invoice_escape((string)($order['customer_email'] ?? 'NA')) . '</div></div>'
        . '<div class="card"><div class="k">Payment Info</div><div class="v">Method: ' . invoice_escape($paymentMethodLabel) . '</div><div class="k">Status: ' . invoice_escape($paymentStatus) . '</div><div class="k">Collection Status: ' . invoice_escape($collectionStatus) . '</div></div>'
        . '</div>'
        . '<table class="tbl">'
        . '<tr><td>Order Amount</td><td>' . invoice_money($grandTotal) . '</td></tr>'
        . '<tr><td>Advance / Received Amount</td><td>' . invoice_money($receivedAmount) . '</td></tr>'
        . '<tr class="total"><td>Balance Due</td><td>' . invoice_money($balanceDue) . '</td></tr>'
        . '</table>'
        . '<div class="footer">This is an advance payment receipt. Tax invoice will be generated only after full payment is received and verified.</div>'
        . '</main></body></html>';

    return $html;
}

function payment_receipt_queue_email(mysqli $conn, array $order, string $receiptHtml): bool
{
    $recipient = trim((string)($order['customer_email'] ?? ''));
    if ($recipient === '') {
        return false;
    }

    $payload = array(
        'order_number' => (string)($order['order_number'] ?? ''),
        'customer_name' => (string)($order['customer_name'] ?? ''),
        'receipt_html' => $receiptHtml,
        'attachments' => array(
            array(
                'filename' => 'payment-receipt-' . preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($order['order_number'] ?? 'order')) . '.html',
                'mime_type' => 'text/html',
                'content_base64' => base64_encode($receiptHtml),
            ),
        ),
    );
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        $payloadJson = '{}';
    }

    $orderId = (int)($order['id'] ?? 0);
    $userId = (int)($order['user_id'] ?? 0);

    $logStmt = $conn->prepare('INSERT INTO communication_logs (user_id, order_id, channel, event_key, recipient, status, payload_json) VALUES (?, ?, "email", "payment_receipt_advance", ?, "queued", ?)');
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
        'event_key' => 'payment_receipt_advance',
        'recipient' => $recipient,
    ), JSON_UNESCAPED_SLASHES);
    if ($jobPayload === false) {
        $jobPayload = '{}';
    }

    $jobStmt = $conn->prepare('INSERT INTO queue_jobs (queue_name, payload, status, available_at, created_at) VALUES ("communication", ?, "pending", NOW(), NOW())');
    $jobStmt->bind_param('s', $jobPayload);
    $jobStmt->execute();

    return true;
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
