<?php
$pageTitle = 'Build Your Own Cake Leads';
require_once __DIR__ . '/layout.php';

if (!function_exists('queue_custom_cake_comm_log')) {
    /**
     * @param mysqli $conn
     * @param array<string,mixed> $context
     */
    function queue_custom_cake_comm_log($conn, $channel, $eventKey, $recipient, array $context)
    {
        $payloadJson = json_encode($context, JSON_UNESCAPED_SLASHES);

        $logStmt = $conn->prepare('INSERT INTO communication_logs (channel, event_key, recipient, status, payload_json) VALUES (?, ?, ?, "queued", ?)');
        if (!$logStmt) {
            throw new RuntimeException('Unable to prepare communication log insert.');
        }
        $logStmt->bind_param('ssss', $channel, $eventKey, $recipient, $payloadJson);
        $logStmt->execute();
        $logId = (int)$logStmt->insert_id;
        $logStmt->close();

        $queueStmt = $conn->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (?, ?, ?)');
        if (!$queueStmt) {
            throw new RuntimeException('Unable to prepare communication queue insert.');
        }
        $queueStmt->bind_param('iss', $logId, $channel, $payloadJson);
        $queueStmt->execute();
        $queueStmt->close();

        $jobPayload = json_encode([
            'log_id' => $logId,
            'channel' => $channel,
            'event_key' => $eventKey,
            'recipient' => $recipient,
        ], JSON_UNESCAPED_SLASHES);

        $jobStmt = $conn->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("send_communication", ?, "queued", NOW(), 0)');
        if (!$jobStmt) {
            throw new RuntimeException('Unable to prepare queue job insert.');
        }
        $jobStmt->bind_param('s', $jobPayload);
        $jobStmt->execute();
        $jobStmt->close();
    }
}

if (!function_exists('ensure_byoc_quote_schema')) {
  /**
   * @param mysqli $conn
   */
  function ensure_byoc_quote_schema($conn)
  {
    $conn->query('CREATE TABLE IF NOT EXISTS byoc_quotes (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      inquiry_id BIGINT UNSIGNED NOT NULL,
      quote_number VARCHAR(50) NOT NULL UNIQUE,
      quote_subject VARCHAR(180) NOT NULL,
      quote_message TEXT NULL,
      quote_amount DECIMAL(10,2) NOT NULL,
      currency CHAR(3) NOT NULL DEFAULT "INR",
      status ENUM("sent","accepted","expired","cancelled") NOT NULL DEFAULT "sent",
      expires_at DATETIME NULL,
      accepted_at DATETIME NULL,
      order_id BIGINT UNSIGNED NULL,
      created_by_admin_id BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_byoc_quotes_inquiry (inquiry_id),
      INDEX idx_byoc_quotes_status (status),
      FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
      FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
      FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
    ) ENGINE=InnoDB');

    $conn->query('CREATE TABLE IF NOT EXISTS byoc_quote_links (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      byoc_quote_id BIGINT UNSIGNED NOT NULL,
      token VARCHAR(120) NOT NULL UNIQUE,
      expires_at DATETIME NULL,
      used_at DATETIME NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_byoc_quote_links_quote (byoc_quote_id)
    ) ENGINE=InnoDB');

    $hasOrderSource = false;
    if ($res = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_source'")) {
      $hasOrderSource = $res->num_rows > 0;
      $res->close();
    }
    if (!$hasOrderSource) {
      $conn->query('ALTER TABLE orders ADD COLUMN order_source ENUM("retail","byoc_quote") NOT NULL DEFAULT "retail" AFTER payment_method');
      $conn->query('CREATE INDEX idx_orders_source ON orders(order_source)');
    }

    $hasByocQuoteId = false;
    if ($res = $conn->query("SHOW COLUMNS FROM orders LIKE 'byoc_quote_id'")) {
      $hasByocQuoteId = $res->num_rows > 0;
      $res->close();
    }
    if (!$hasByocQuoteId) {
      $conn->query('ALTER TABLE orders ADD COLUMN byoc_quote_id BIGINT UNSIGNED NULL AFTER order_source');
      $conn->query('CREATE UNIQUE INDEX uq_orders_byoc_quote ON orders(byoc_quote_id)');
      $conn->query('ALTER TABLE orders ADD CONSTRAINT fk_orders_byoc_quote FOREIGN KEY (byoc_quote_id) REFERENCES byoc_quotes(id) ON DELETE SET NULL');
    }
  }
}

if (!function_exists('byoc_generate_order_number')) {
  function byoc_generate_order_number(): string
  {
    return 'BYOC-' . date('Ymd') . '-' . random_int(100000, 999999);
  }
}

if (!function_exists('expire_due_byoc_quotes')) {
  /**
   * @param mysqli $conn
   */
  function expire_due_byoc_quotes($conn): void
  {
    $conn->query(
      'UPDATE byoc_quote_links l
       INNER JOIN byoc_quotes q ON q.id = l.byoc_quote_id
       INNER JOIN inquiries i ON i.id = q.inquiry_id
       SET l.is_active = 0
       WHERE q.status = "sent"
         AND q.accepted_at IS NULL
         AND q.order_id IS NULL
         AND q.expires_at IS NOT NULL
         AND q.expires_at <= NOW()
         AND l.used_at IS NULL
         AND i.inquiry_type = "custom_cake"'
    );

    $conn->query(
      'UPDATE byoc_quotes q
       INNER JOIN inquiries i ON i.id = q.inquiry_id
       SET q.status = "expired", q.updated_at = NOW()
       WHERE q.status = "sent"
         AND q.accepted_at IS NULL
         AND q.order_id IS NULL
         AND q.expires_at IS NOT NULL
         AND q.expires_at <= NOW()
         AND i.inquiry_type = "custom_cake"'
    );

    $conn->query(
      'UPDATE inquiries i
       SET i.status = "closed", i.updated_at = NOW()
       WHERE i.inquiry_type = "custom_cake"
         AND i.status <> "closed"
         AND EXISTS (
           SELECT 1
           FROM byoc_quotes q
           WHERE q.inquiry_id = i.id
             AND q.status = "expired"
             AND q.accepted_at IS NULL
             AND q.order_id IS NULL
             AND q.expires_at IS NOT NULL
             AND q.expires_at <= NOW()
         )
         AND NOT EXISTS (
           SELECT 1
           FROM byoc_quotes q2
           WHERE q2.inquiry_id = i.id
             AND q2.status IN ("sent", "accepted")
         )'
    );
  }
}

if (!function_exists('byoc_resolve_fallback_product_id')) {
  function byoc_resolve_fallback_product_id(mysqli $conn): int
  {
    $hasIsActive = false;
    if ($colRes = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'")) {
      $hasIsActive = $colRes->num_rows > 0;
      $colRes->close();
    }

    if ($hasIsActive) {
      $res = $conn->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
      if ($res) {
        $row = $res->fetch_assoc();
        $activeId = (int)($row['id'] ?? 0);
        if ($activeId > 0) {
          return $activeId;
        }
      }
    }

    // Handle schemas that use availability_status + deleted_at rather than is_active.
    $statusRes = $conn->query('SELECT id FROM products WHERE (deleted_at IS NULL OR deleted_at = "0000-00-00 00:00:00") AND availability_status IN ("in_stock", "preorder") ORDER BY id ASC LIMIT 1');
    if ($statusRes) {
      $statusRow = $statusRes->fetch_assoc();
      $statusId = (int)($statusRow['id'] ?? 0);
      if ($statusId > 0) {
        return $statusId;
      }
    }

    // Last resort: first product row, regardless of active/status flags.
    $fallbackRes = $conn->query('SELECT id FROM products ORDER BY id ASC LIMIT 1');
    if (!$fallbackRes) {
      return 0;
    }
    $fallbackRow = $fallbackRes->fetch_assoc();
    return (int)($fallbackRow['id'] ?? 0);
  }
}

ensure_byoc_quote_schema($conn);
expire_due_byoc_quotes($conn);

// Load WhatsApp toggle setting (default ON)
$byocWhatsAppEnabled = true;
$_waStmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = "byoc_whatsapp_enabled" LIMIT 1');
if ($_waStmt) {
  $_waStmt->execute();
  $_waRes = $_waStmt->get_result();
  $_waRow = $_waRes ? $_waRes->fetch_assoc() : null;
  $_waStmt->close();
  if ($_waRow !== null) {
    $byocWhatsAppEnabled = (string)($_waRow['setting_value'] ?? '1') !== '0';
  }
  unset($_waRow, $_waRes, $_waStmt);
}

$flash = '';
$flashType = 'success';

$defaultEmailSubject = 'Your Custom Cake Quote from Cakeouflage';
$defaultEmailBody = "Hi {{first_name}},\n\nThank you for your Build Your Own Cake inquiry.\n\nQuote: {{quote_subject}}\n\n{{quote_message}}\n\nQuote Amount: INR {{quote_amount}}\n\nAccept your quote: {{quote_accept_link}}\n\nEvent: {{event_information}}\nEvent Date: {{event_date}}\nGuests: {{number_of_servings_guests}}\nBudget: {{budget_range}}\nDiet: {{diet_preference}}\n\nRegards,\nCakeouflage Team";

$tplStmt = $conn->prepare('INSERT IGNORE INTO communication_templates (channel, event_key, subject, body_template, is_active) VALUES ("email", "build_your_cake_quote_email", ?, ?, 1)');
if ($tplStmt) {
  $tplStmt->bind_param('ss', $defaultEmailSubject, $defaultEmailBody);
  $tplStmt->execute();
  $tplStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);

    try {
        if ($action === 'save_whatsapp_toggle') {
            $newWaVal = ($_POST['byoc_whatsapp_enabled'] ?? '1') === '0' ? '0' : '1';
            $adminId = (int)($_SESSION['admin_id'] ?? 0);
            $upsertWa = $conn->prepare('INSERT INTO settings (setting_key, setting_value, updated_by_admin_id, updated_at) VALUES ("byoc_whatsapp_enabled", ?, ?, NOW()) AS new ON DUPLICATE KEY UPDATE setting_value = new.setting_value, updated_by_admin_id = new.updated_by_admin_id, updated_at = NOW()');
            if ($upsertWa) {
                $upsertWa->bind_param('si', $newWaVal, $adminId);
                $upsertWa->execute();
                $upsertWa->close();
            }
            $byocWhatsAppEnabled = $newWaVal === '1';
            $flash = 'WhatsApp notification setting updated.';
        }

        if ($action === 'update_status') {
            $allowedStatuses = ['new', 'in_review', 'closed'];
            $status = trim((string)($_POST['status'] ?? ''));
            if ($inquiryId <= 0 || !in_array($status, $allowedStatuses, true)) {
                throw new RuntimeException('Invalid status update request.');
            }

            $stmt = $conn->prepare('UPDATE inquiries SET status = ?, updated_at = NOW() WHERE id = ? AND inquiry_type = "custom_cake"');
            if (!$stmt) {
                throw new RuntimeException('Could not prepare status update.');
            }
            $stmt->bind_param('si', $status, $inquiryId);
            $stmt->execute();
            $stmt->close();

            $flash = 'Lead status updated.';
        }

        if ($action === 'send_quote') {
            $quoteSubject = trim((string)($_POST['quote_subject'] ?? ''));
            $quoteMessage = trim((string)($_POST['quote_message'] ?? ''));
          $quoteAmount = (float)($_POST['quote_amount'] ?? 0);
          $expiryHours = (int)($_POST['expiry_hours'] ?? 72);
          if ($expiryHours < 1) {
            $expiryHours = 72;
          }

          if ($inquiryId <= 0 || $quoteSubject === '' || $quoteMessage === '' || $quoteAmount <= 0) {
            throw new RuntimeException('Quote subject, amount, and message are required.');
            }

            $leadStmt = $conn->prepare('SELECT id, name, email, phone, message, reference_file FROM inquiries WHERE id = ? AND inquiry_type = "custom_cake" LIMIT 1');
            if (!$leadStmt) {
                throw new RuntimeException('Could not load lead details.');
            }
            $leadStmt->bind_param('i', $inquiryId);
            $leadStmt->execute();
            $result = $leadStmt->get_result();
            $lead = $result ? $result->fetch_assoc() : null;
            $leadStmt->close();

            if (!$lead) {
                throw new RuntimeException('Lead not found.');
            }

            $meta = json_decode((string)($lead['message'] ?? ''), true);
            if (!is_array($meta)) {
                $meta = [];
            }

            $firstName = trim((string)($lead['name'] ?? 'Customer'));
            $parts = preg_split('/\s+/', $firstName) ?: [];
            if (isset($parts[0]) && trim((string)$parts[0]) !== '') {
                $firstName = trim((string)$parts[0]);
            }

            $phoneCountry = trim((string)($meta['phone_country_code'] ?? '+91'));
            $phoneDigits = preg_replace('/\D+/', '', (string)($lead['phone'] ?? '')) ?: '';
            $fullPhone = $phoneDigits !== '' ? ($phoneCountry . $phoneDigits) : '';

            $conn->begin_transaction();

            // Cancel any previous sent quotes and deactivate their links for this inquiry
            $conn->query('UPDATE byoc_quotes SET status = "cancelled", updated_at = NOW() WHERE inquiry_id = ' . (int)$inquiryId . ' AND status = "sent"');
            $conn->query('UPDATE byoc_quote_links SET is_active = 0 WHERE byoc_quote_id IN (SELECT id FROM byoc_quotes WHERE inquiry_id = ' . (int)$inquiryId . ') AND used_at IS NULL');

            $quoteNumber = 'BYOC-' . date('Ymd') . '-' . random_int(100000, 999999);
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));
            $insQuote = $conn->prepare('INSERT INTO byoc_quotes (inquiry_id, quote_number, quote_subject, quote_message, quote_amount, currency, status, expires_at) VALUES (?, ?, ?, ?, ?, "INR", "sent", ?)');
            if (!$insQuote) {
              throw new RuntimeException('Could not prepare quote record insert.');
            }
            $insQuote->bind_param('isssds', $inquiryId, $quoteNumber, $quoteSubject, $quoteMessage, $quoteAmount, $expiresAt);
            $insQuote->execute();
            $byocQuoteId = (int)$insQuote->insert_id;
            $insQuote->close();

            $quoteToken = bin2hex(random_bytes(24));
            $insLink = $conn->prepare('INSERT INTO byoc_quote_links (byoc_quote_id, token, expires_at, is_active) VALUES (?, ?, ?, 1)');
            if (!$insLink) {
              throw new RuntimeException('Could not prepare quote link insert.');
            }
            $insLink->bind_param('iss', $byocQuoteId, $quoteToken, $expiresAt);
            $insLink->execute();
            $insLink->close();

            $appUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
            if ($appUrl === '') {
              $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
              $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
              $appUrl = $scheme . '://' . $host;
            }
            $acceptLink = $appUrl . '/quote/accept/' . $quoteToken;

            $context = [
              'inquiry_id' => $inquiryId,
              'lead_id' => $inquiryId,
              'byoc_quote_id' => $byocQuoteId,
              'quote_number' => $quoteNumber,
                'name' => (string)($lead['name'] ?? ''),
                'first_name' => $firstName,
                'email' => (string)($lead['email'] ?? ''),
                'phone' => $fullPhone,
                'event_information' => (string)($meta['event_information'] ?? ''),
                'event_date' => (string)($meta['event_date'] ?? ''),
                'number_of_servings_guests' => (string)($meta['number_of_servings_guests'] ?? ''),
                'budget_range' => (string)($meta['budget_range'] ?? ''),
                'diet_preference' => (string)($meta['diet_preference'] ?? ''),
                'design_breif_notes' => (string)($meta['design_breif_notes'] ?? ''),
                'reference_file' => (string)($lead['reference_file'] ?? ''),
                'quote_subject' => $quoteSubject,
                'quote_message' => $quoteMessage,
                'quote_amount' => number_format($quoteAmount, 2, '.', ''),
                'advance_amount' => number_format(round($quoteAmount * 0.5, 2), 2, '.', ''),
                'quote_expiry_at' => $expiresAt,
                'quote_expiry_display' => date('d M Y \a\t g:i A', strtotime($expiresAt)),
                'quote_accept_link' => $acceptLink,
                'subject' => $quoteSubject,
                'body_template' => $quoteMessage,
            ];

            if (!empty($lead['email'])) {
                queue_custom_cake_comm_log($conn, 'email', 'build_your_cake_quote_email', (string)$lead['email'], $context);
            }
            if ($fullPhone !== '' && $byocWhatsAppEnabled) {
                queue_custom_cake_comm_log($conn, 'whatsapp', 'build_your_cake_quote_whatsapp', $fullPhone, $context);
            }

            $statusStmt = $conn->prepare('UPDATE inquiries SET status = "in_review", updated_at = NOW() WHERE id = ? AND inquiry_type = "custom_cake"');
            if ($statusStmt) {
                $statusStmt->bind_param('i', $inquiryId);
                $statusStmt->execute();
                $statusStmt->close();
            }

            $conn->commit();

            $flash = 'Custom quote queued. Acceptance link generated and attached to communication context.';
        }

        if ($action === 'create_order') {
          if ($inquiryId <= 0) {
            throw new RuntimeException('Invalid lead for order creation.');
          }

          // Resolve selected topper (optional)
          $byocTopperId = isset($_POST['topper_id']) && (int)$_POST['topper_id'] > 0 ? (int)$_POST['topper_id'] : null;
          $byocTopperPrice = 0.00;
          $byocTopperName  = null;
          if ($byocTopperId !== null) {
            $tpRow = $conn->query('SELECT name, price FROM cake_toppers WHERE id = ' . $byocTopperId . ' AND is_active = 1 LIMIT 1');
            $tpRow = $tpRow ? $tpRow->fetch_assoc() : null;
            if ($tpRow) {
              $byocTopperPrice = (float)$tpRow['price'];
              $byocTopperName  = (string)$tpRow['name'];
            } else {
              $byocTopperId = null; // invalid — ignore
            }
          }

          $conn->begin_transaction();

          $quoteStmt = $conn->prepare('SELECT q.id, q.quote_number, q.quote_subject, q.quote_message, q.quote_amount, q.status, q.order_id, i.name, i.email, i.phone, i.message FROM byoc_quotes q INNER JOIN inquiries i ON i.id = q.inquiry_id WHERE q.inquiry_id = ? ORDER BY q.id DESC LIMIT 1');
          if (!$quoteStmt) {
            throw new RuntimeException('Could not prepare latest quote lookup.');
          }
          $quoteStmt->bind_param('i', $inquiryId);
          $quoteStmt->execute();
          $quoteResult = $quoteStmt->get_result();
          $quote = $quoteResult ? $quoteResult->fetch_assoc() : null;
          $quoteStmt->close();

          if (!$quote) {
            throw new RuntimeException('No quote found for this lead. Send a quote first.');
          }

          if ((string)($quote['status'] ?? '') === 'cancelled') {
            throw new RuntimeException('Latest quote is cancelled. Please resend quote before creating order.');
          }
          if ((string)($quote['status'] ?? '') === 'expired') {
            throw new RuntimeException('Latest quote has expired and the request is closed. Please resend quote before creating order.');
          }

          $existingOrderId = (int)($quote['order_id'] ?? 0);
          if ($existingOrderId > 0) {
            $conn->commit();
            $flash = 'Order already exists for this quote: #' . $existingOrderId;
          } else {
            $quoteAmount = (float)($quote['quote_amount'] ?? 0);
            if ($quoteAmount <= 0) {
              throw new RuntimeException('Quote amount is invalid for order creation.');
            }

            $productId = byoc_resolve_fallback_product_id($conn);
            if ($productId <= 0) {
              throw new RuntimeException('No active product found for BYOC conversion.');
            }

            $meta = json_decode((string)($quote['message'] ?? ''), true);
            if (!is_array($meta)) {
              $meta = [];
            }

            $customerName = trim((string)($quote['name'] ?? 'Guest Customer'));
            if ($customerName === '') {
              $customerName = 'Guest Customer';
            }
            $customerEmail = trim((string)($quote['email'] ?? ''));
            if ($customerEmail === '') {
              $customerEmail = 'guest-' . strtolower(bin2hex(random_bytes(4))) . '@cakeouflage.local';
            }
            $customerPhone = preg_replace('/\D+/', '', (string)($quote['phone'] ?? '')) ?: '0000000000';
            $orderNumber = byoc_generate_order_number();
            $scheduledSlot = !empty($meta['event_date']) ? ((string)$meta['event_date'] . ' 10:00:00') : null;
            $scheduledSlotLabel = !empty($meta['event_date']) ? ('Event Date: ' . (string)$meta['event_date']) : null;
            $adminNote = 'BYOC order created by admin tele-calling from Quote #' . (string)($quote['quote_number'] ?? '');

            $orderStmt = $conn->prepare('INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, fulfilment_mode, order_status, payment_status, payment_method, scheduled_slot, scheduled_slot_label, delivery_postal_code, delivery_distance_km, delivery_fee, subtotal, discount_total, tax_total, grand_total, admin_note, order_source, byoc_quote_id) VALUES (?, NULL, ?, ?, ?, "custom_delivery", "pending", "pending", "upi_manual", ?, ?, NULL, NULL, 0, ?, 0, 0, ?, ?, "byoc_quote", ?)');
            if (!$orderStmt) {
              throw new RuntimeException('Could not prepare BYOC order insert.');
            }
            $quoteId = (int)$quote['id'];
            $orderStmt->bind_param('ssssssddsi', $orderNumber, $customerName, $customerEmail, $customerPhone, $scheduledSlot, $scheduledSlotLabel, $quoteAmount, $quoteAmount, $adminNote, $quoteId);
            $orderStmt->execute();
            $orderId = (int)$orderStmt->insert_id;
            $orderStmt->close();

            $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note, topper_id, topper_name_snapshot, topper_price_snapshot) VALUES (?, ?, NULL, ?, NULL, ?, 1, ?, ?, ?, ?, ?)');
            if (!$itemStmt) {
              throw new RuntimeException('Could not prepare BYOC order item insert.');
            }
            $subjectSnapshot = trim((string)($quote['quote_subject'] ?? ''));
            if ($subjectSnapshot === '') {
              $subjectSnapshot = 'Build Your Own Cake Quote';
            }
            $messageSnapshot = (string)($quote['quote_message'] ?? '');
            $itemStmt->bind_param('iisddsisd', $orderId, $productId, $subjectSnapshot, $quoteAmount, $quoteAmount, $messageSnapshot, $byocTopperId, $byocTopperName, $byocTopperPrice);
            $itemStmt->execute();
            $itemStmt->close();

            $updateQuote = $conn->prepare('UPDATE byoc_quotes SET order_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            if ($updateQuote) {
              $updateQuote->bind_param('ii', $orderId, $quoteId);
              $updateQuote->execute();
              $updateQuote->close();
            }

            $deactivateLinks = $conn->prepare('UPDATE byoc_quote_links SET is_active = 0 WHERE byoc_quote_id = ? AND used_at IS NULL');
            if ($deactivateLinks) {
              $deactivateLinks->bind_param('i', $quoteId);
              $deactivateLinks->execute();
              $deactivateLinks->close();
            }

            $statusStmt = $conn->prepare('UPDATE inquiries SET status = "in_review", updated_at = NOW() WHERE id = ? AND inquiry_type = "custom_cake"');
            if ($statusStmt) {
              $statusStmt->bind_param('i', $inquiryId);
              $statusStmt->execute();
              $statusStmt->close();
            }

            $conn->commit();
            $flash = 'BYOC order created from tele-calling flow. Order ID: #' . $orderId;
          }
        }
    } catch (Throwable $e) {
      $conn->rollback();
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));

$where = ['inquiry_type = "custom_cake"'];
$params = [];
$types = '';

if ($statusFilter !== '' && in_array($statusFilter, ['new', 'in_review', 'closed'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sql = 'SELECT id, name, email, phone, status, message, reference_file, created_at FROM inquiries WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 300';
$stmt = $conn->prepare($sql);
if ($stmt && $types !== '' && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $rowsResult = $stmt->get_result();
    $rows = $rowsResult ? $rowsResult->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $rows = [];
}

$quoteHistoryByLead = [];
$latestQuoteByLead = [];
$rowIds = [];
foreach ($rows as $leadRow) {
  $rowIds[] = (int)($leadRow['id'] ?? 0);
}
$rowIds = array_values(array_filter($rowIds, static function ($id) { return $id > 0; }));

if (!empty($rowIds)) {
  $logEventKeys = ['build_your_cake_quote_email'];
  if ($byocWhatsAppEnabled) {
    $logEventKeys[] = 'build_your_cake_quote_whatsapp';
  }
  $types = str_repeat('s', count($logEventKeys)) . str_repeat('i', count($rowIds));
  $params = $logEventKeys;
  foreach ($rowIds as $id) {
    $params[] = $id;
  }

  $placeholders = implode(',', array_fill(0, count($rowIds), '?'));
  $keyPlaceholders = implode(',', array_fill(0, count($logEventKeys), '?'));
  $logSql = 'SELECT id, channel, event_key, recipient, status, provider_message_id, error_message, created_at, JSON_UNQUOTE(JSON_EXTRACT(payload_json, "$.inquiry_id")) AS inquiry_id FROM communication_logs WHERE event_key IN (' . $keyPlaceholders . ') AND JSON_EXTRACT(payload_json, "$.inquiry_id") IS NOT NULL AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload_json, "$.inquiry_id")) AS UNSIGNED) IN (' . $placeholders . ') ORDER BY created_at DESC, id DESC';
  $logStmt = $conn->prepare($logSql);
  if ($logStmt) {
    $logStmt->bind_param($types, ...$params);
    $logStmt->execute();
    $logResult = $logStmt->get_result();
    $historyRows = $logResult ? $logResult->fetch_all(MYSQLI_ASSOC) : [];
    $logStmt->close();

    foreach ($historyRows as $historyRow) {
      $leadId = (int)($historyRow['inquiry_id'] ?? 0);
      if ($leadId <= 0) {
        continue;
      }
      if (!isset($quoteHistoryByLead[$leadId])) {
        $quoteHistoryByLead[$leadId] = [];
      }
      $quoteHistoryByLead[$leadId][] = $historyRow;
    }
  }

  $quoteTypes = str_repeat('i', count($rowIds));
  $quoteParams = [];
  foreach ($rowIds as $id) {
    $quoteParams[] = $id;
  }
  $quotePlaceholders = implode(',', array_fill(0, count($rowIds), '?'));
  $quoteSql = 'SELECT q.id, q.inquiry_id, q.quote_number, q.quote_subject, q.quote_message, q.quote_amount, q.currency, q.status, q.expires_at, q.accepted_at, q.order_id, l.token, l.used_at, l.is_active, o.payment_status, o.order_status
               FROM byoc_quotes q
               LEFT JOIN byoc_quote_links l ON l.byoc_quote_id = q.id
               LEFT JOIN orders o ON o.id = q.order_id
               WHERE q.inquiry_id IN (' . $quotePlaceholders . ')
               ORDER BY q.id DESC';
  $quoteStmt = $conn->prepare($quoteSql);
  if ($quoteStmt) {
    $quoteStmt->bind_param($quoteTypes, ...$quoteParams);
    $quoteStmt->execute();
    $quoteResult = $quoteStmt->get_result();
    $quoteRows = $quoteResult ? $quoteResult->fetch_all(MYSQLI_ASSOC) : [];
    $quoteStmt->close();

    foreach ($quoteRows as $quoteRow) {
      $leadId = (int)($quoteRow['inquiry_id'] ?? 0);
      if ($leadId <= 0 || isset($latestQuoteByLead[$leadId])) {
        continue;
      }
      $latestQuoteByLead[$leadId] = $quoteRow;
    }
  }
}
// Fetch active toppers for the create_order form
$byocTopperOptions = [];
if ($tres = $conn->query('SELECT id, name, price FROM cake_toppers WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')) {
  $byocTopperOptions = $tres->fetch_all(MYSQLI_ASSOC);
}
?>
<style>
.byoc-shell { display: grid; gap: 16px; }
.byoc-card { background: #fff; border: 1px solid #f1dce3; border-radius: 14px; padding: 16px; }
.byoc-filters { display: flex; gap: 10px; flex-wrap: wrap; }
.byoc-filters input, .byoc-filters select { min-height: 38px; border: 1px solid #e4c9d2; border-radius: 8px; padding: 0 10px; }
.byoc-table { width: 100%; border-collapse: collapse; }
.byoc-table th, .byoc-table td { padding: 10px; border-bottom: 1px solid #f3e3e8; text-align: left; vertical-align: top; font-size: 13px; }
.byoc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.byoc-grid textarea, .byoc-grid input, .byoc-grid select { width: 100%; min-height: 38px; border: 1px solid #e4c9d2; border-radius: 8px; padding: 8px 10px; }
.byoc-grid textarea { min-height: 90px; }
.byoc-flash { padding: 10px 12px; border-radius: 10px; font-size: 13px; }
.byoc-flash--success { background: #edf9f1; color: #1f6b3d; }
.byoc-flash--error { background: #fff0f0; color: #9b1f1f; }
.byoc-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.byoc-btn { border: none; border-radius: 8px; min-height: 34px; padding: 0 10px; cursor: pointer; background: #80001f; color: #fff; font-size: 12px; }
.byoc-btn--secondary { background: #f6dde5; color: #80001f; }
.byoc-meta { color: #6f5360; font-size: 12px; }
.byoc-history { margin-top: 10px; border-top: 1px dashed #e6cbd3; padding-top: 8px; }
.qt-wrap { grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: -4px; }
.qt-label { font-size: 11px; color: #9c7b86; white-space: nowrap; }
.qt-chip { border: 1px solid #f0d7df; background: #fff7f9; color: #80001f; border-radius: 20px; padding: 4px 12px; font-size: 11px; cursor: pointer; white-space: nowrap; }
.qt-chip:hover { background: #80001f; color: #fff; }
.byoc-accept-link { display: flex; gap: 6px; align-items: center; margin-top: 6px; }
.byoc-accept-link input[type=text] { flex: 1; font-size: 11px; padding: 5px 8px; border: 1px solid #e4c9d2; border-radius: 6px; background: #fffafc; color: #4a2033; cursor: text; }
.byoc-btn--edit { background: #fff3cd; color: #7a4f00; margin-top: 6px; }
.byoc-history-item { border: 1px solid #f2e2e8; border-radius: 8px; padding: 8px; margin-top: 6px; background: #fffafc; }
.byoc-history-title { font-weight: 600; font-size: 12px; color: #5b2436; }
.byoc-history-meta { color: #7d6170; font-size: 11px; margin-top: 4px; }
</style>

<div class="content-header">
  <h1>Build Your Own Cake Leads</h1>
  <p>Review incoming leads, update statuses, and respond with custom quotes.</p>
</div>

<div class="byoc-shell">
  <?php if ($flash !== ''): ?>
    <div class="byoc-flash <?= $flashType === 'error' ? 'byoc-flash--error' : 'byoc-flash--success' ?>"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <div class="byoc-card" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
    <form method="get" class="byoc-filters" style="flex:1; min-width:220px;">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, mobile" />
      <select name="status">
        <option value="">All statuses</option>
        <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>New</option>
        <option value="in_review" <?= $statusFilter === 'in_review' ? 'selected' : '' ?>>In Review</option>
        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
      </select>
      <button type="submit" class="byoc-btn">Apply</button>
    </form>
    <form method="post" style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
      <input type="hidden" name="action" value="save_whatsapp_toggle" />
      <span style="font-size:12px; color:#6f5360; font-weight:600;">WhatsApp Notifications:</span>
      <?php if ($byocWhatsAppEnabled): ?>
        <span style="font-size:12px; color:#1f6b3d; font-weight:600;">ON ✓</span>
        <input type="hidden" name="byoc_whatsapp_enabled" value="0" />
        <button type="submit" class="byoc-btn byoc-btn--secondary" style="background:#fff0f0; color:#9b1f1f;">Turn Off</button>
      <?php else: ?>
        <span style="font-size:12px; color:#9b1f1f; font-weight:600;">OFF ✗</span>
        <input type="hidden" name="byoc_whatsapp_enabled" value="1" />
        <button type="submit" class="byoc-btn" style="background:#1f6b3d;">Turn On</button>
      <?php endif; ?>
    </form>
  </div>

  <div class="byoc-card">
    <table class="byoc-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Lead</th>
          <th>Event</th>
          <th>Status</th>
          <th>Quote</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5">No Build Your Own Cake leads found.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php $meta = json_decode((string)($row['message'] ?? ''), true); if (!is_array($meta)) { $meta = []; } ?>
          <tr>
            <td>#<?= (int)$row['id'] ?></td>
            <td>
              <strong><?= htmlspecialchars((string)($row['name'] ?? '')) ?></strong><br />
              <span class="byoc-meta"><?= htmlspecialchars((string)($row['email'] ?? '')) ?></span><br />
              <span class="byoc-meta"><?= htmlspecialchars((string)($meta['phone_country_code'] ?? '+91')) ?> <?= htmlspecialchars((string)($row['phone'] ?? '')) ?></span>
            </td>
            <td>
              <div><strong><?= htmlspecialchars((string)($meta['event_information'] ?? '-')) ?></strong></div>
              <div class="byoc-meta">Date: <?= htmlspecialchars((string)($meta['event_date'] ?? '-')) ?></div>
              <div class="byoc-meta">Guests: <?= htmlspecialchars((string)($meta['number_of_servings_guests'] ?? '-')) ?></div>
              <div class="byoc-meta">Budget: <?= htmlspecialchars((string)($meta['budget_range'] ?? '-')) ?></div>
              <div class="byoc-meta">Diet: <?= htmlspecialchars((string)($meta['diet_preference'] ?? '-')) ?></div>
              <?php if (!empty($row['reference_file'])): ?>
                <div class="byoc-meta"><a href="<?= htmlspecialchars((string)$row['reference_file']) ?>" target="_blank" rel="noopener noreferrer">View image</a></div>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" class="byoc-actions">
                <input type="hidden" name="action" value="update_status" />
                <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>" />
                <select name="status">
                  <option value="new" <?= (string)$row['status'] === 'new' ? 'selected' : '' ?>>New</option>
                  <option value="in_review" <?= (string)$row['status'] === 'in_review' ? 'selected' : '' ?>>In Review</option>
                  <option value="closed" <?= (string)$row['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
                <button type="submit" class="byoc-btn byoc-btn--secondary">Update</button>
              </form>
            </td>
            <td>
              <form method="post" class="byoc-grid" data-send-form="<?= (int)$row['id'] ?>">
                <input type="hidden" name="action" value="send_quote" />
                <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>" />
                <input type="text" name="quote_subject" placeholder="Quote subject" required />
                <input type="number" name="quote_amount" min="1" step="0.01" placeholder="Quote amount (INR)" required />
                <input type="number" name="expiry_hours" min="1" max="240" value="72" placeholder="Link expiry hours" />
                <textarea name="quote_message" id="quoteMessageArea" placeholder="Custom quote message (managed template fallback context)" required></textarea>
                <div class="qt-wrap">
                  <span class="qt-label">Quick templates:</span>
                  <button type="button" class="qt-chip" data-subject="Custom Cake Quote" data-msg="Hi {{first_name}},&#10;&#10;Thank you for reaching out to Cakeouflage! We're excited to create your perfect custom cake.&#10;&#10;Based on your requirements, we have prepared a personalised quote for you. Our cakes are baked fresh with premium ingredients, customised to your design and dietary preferences.&#10;&#10;Quote Details:&#10;• Occasion: {{event_information}}&#10;• Servings: {{number_of_servings_guests}}&#10;• Delivery/Pickup: as discussed&#10;&#10;Kindly accept the quote via the link below to confirm your order. Once accepted, a 50% advance payment will lock in your slot.">🎂 Standard</button>
                  <button type="button" class="qt-chip" data-subject="Fondant Designer Cake Quote" data-msg="Hi {{first_name}},&#10;&#10;Thank you for your interest in our Fondant Designer Cakes!&#10;&#10;Creating a fondant masterpiece requires extra preparation time for sculpting, hand-painting, and intricate detailing. We've factored all of this into your quote.&#10;&#10;Please note:&#10;• Custom fondant figures/toppers take 2–3 extra days&#10;• Final design will be shared for your approval before baking&#10;• We recommend confirming at least 5–7 days before your event&#10;&#10;Kindly accept the quote and pay the 50% advance to begin crafting your design.">🎨 Fondant Special</button>
                  <button type="button" class="qt-chip" data-subject="Corporate Cake Quote" data-msg="Hi {{first_name}},&#10;&#10;Thank you for considering Cakeouflage for your corporate celebration!&#10;&#10;We specialise in branded and logo cakes for corporate events, product launches, and team celebrations. Here's your customised quote for the order:&#10;&#10;• Logo/branding will be printed/crafted as provided&#10;• Bulk orders (5+) receive priority scheduling&#10;• Delivery available with advance coordination&#10;&#10;Please accept the quote and make the advance payment to confirm your order. We look forward to being a part of your celebration!">🏢 Corporate</button>
                  <button type="button" class="qt-chip" data-subject="Multi-Tier Celebration Cake Quote" data-msg="Hi {{first_name}},&#10;&#10;We're thrilled to create a stunning multi-tier cake for your special occasion!&#10;&#10;Multi-tier cakes involve:&#10;• Structural supports (pillars / dowels / boards) for each tier&#10;• Each tier can have a different flavour and frosting&#10;• Assembly at venue is recommended for 3+ tiers&#10;&#10;We recommend booking at least 7–10 days in advance to ensure the best results. Once you accept the quote and pay the 50% advance, we'll reach out to finalise the design and tier flavours with you.">🎪 Multi-Tier</button>
                  <button type="button" class="qt-chip" data-subject="Custom Cake Quote — Quick Pickup" data-msg="Hi {{first_name}},&#10;&#10;Great news — your custom cake is ready to be prepared! Based on your requirements, here's a quick quote for you.&#10;&#10;This cake will be:&#10;• Freshly baked and ready for pickup from our store&#10;• Packed with care for safe transport&#10;• Designed as per your brief&#10;&#10;Kindly accept the quote and pay the 50% advance at the earliest to confirm your pickup slot. We'll notify you when it's ready!">🏃 Quick Pickup</button>
                </div>
                  <button type="submit" class="byoc-btn">Send Quote (Email<?= $byocWhatsAppEnabled ? ' + WhatsApp' : '' ?>)</button>
                </div>
              </form>
              <?php $latestQuote = $latestQuoteByLead[(int)$row['id']] ?? null; ?>
              <?php if (is_array($latestQuote)): ?>
                <?php
                  $quoteToken = trim((string)($latestQuote['token'] ?? ''));
                  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                  $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
                  $acceptUrl = $quoteToken !== '' ? ($scheme . '://' . $host . '/quote/accept/' . $quoteToken) : '';
                  $quoteStatusLabel = (string)($latestQuote['status'] ?? 'sent');
                  $orderId = (int)($latestQuote['order_id'] ?? 0);
                  $paymentStatus = trim((string)($latestQuote['payment_status'] ?? ''));
                  if ($orderId > 0 && $paymentStatus !== 'paid' && $quoteStatusLabel === 'sent') {
                    $quoteStatusLabel = 'payment_pending';
                  }
                ?>
                <div class="byoc-history-item" style="margin-top:8px;">
                  <div class="byoc-history-title">Latest Quote: <?= htmlspecialchars((string)$latestQuote['quote_number']) ?></div>
                  <div class="byoc-history-meta">Amount: <?= htmlspecialchars((string)$latestQuote['currency']) ?> <?= htmlspecialchars(number_format((float)($latestQuote['quote_amount'] ?? 0), 2, '.', '')) ?> | Status: <?= htmlspecialchars($quoteStatusLabel) ?></div>
                  <div class="byoc-history-meta">Expires: <?= htmlspecialchars((string)($latestQuote['expires_at'] ?? '-')) ?></div>
                  <?php if ($orderId > 0): ?>
                    <div class="byoc-history-meta">Order State: <?= htmlspecialchars((string)($latestQuote['order_status'] ?? 'pending')) ?> | Payment: <?= htmlspecialchars($paymentStatus !== '' ? $paymentStatus : 'pending') ?></div>
                  <?php endif; ?>
                  <?php if ($acceptUrl !== ''): ?>
                    <div class="byoc-accept-link">
                      <input type="text" value="<?= htmlspecialchars($acceptUrl) ?>" readonly onclick="this.select()" title="Click to copy acceptance link" />
                      <a href="<?= htmlspecialchars($acceptUrl) ?>" target="_blank" rel="noopener noreferrer" class="byoc-btn byoc-btn--secondary" style="line-height:28px;display:inline-block;text-decoration:none;white-space:nowrap;">Open ↗</a>
                    </div>
                    <?php if ((string)($latestQuote['status'] ?? '') === 'sent' && empty($latestQuote['used_at']) && $orderId <= 0): ?>
                    <button type="button" class="byoc-btn byoc-btn--edit"
                      data-edit-quote="<?= (int)$row['id'] ?>"
                      data-subject="<?= htmlspecialchars((string)($latestQuote['quote_subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-amount="<?= htmlspecialchars((string)($latestQuote['quote_amount'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-message="<?= htmlspecialchars((string)($latestQuote['quote_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">✏️ Edit &amp; Resend</button>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ((string)($latestQuote['status'] ?? '') === 'sent' && $orderId <= 0): ?>
                    <form method="post" class="byoc-actions" style="margin-top:6px;">
                      <input type="hidden" name="action" value="create_order" />
                      <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>" />
                      <?php if (!empty($byocTopperOptions)): ?>
                      <select name="topper_id" style="min-height:32px;border:1px solid #e4c9d2;border-radius:8px;padding:0 8px;font-size:12px;background:#fff;" title="Select topper (optional)">
                        <option value="">— No Topper —</option>
                        <?php foreach ($byocTopperOptions as $t): ?>
                          <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?><?= (float)$t['price'] > 0 ? ' (+₹' . number_format((float)$t['price'], 0) . ')' : '' ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php endif; ?>
                      <button type="submit" class="byoc-btn" onclick="return confirm('Create order from latest quote for this lead?');">☎ Create Order (Tele-calling)</button>
                    </form>
                  <?php endif; ?>
                  <?php if (!empty($latestQuote['used_at'])): ?>
                    <div class="byoc-history-meta">Link Used At: <?= htmlspecialchars((string)$latestQuote['used_at']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($latestQuote['accepted_at'])): ?>
                    <div class="byoc-history-meta">Accepted (Payment Confirmed) At: <?= htmlspecialchars((string)$latestQuote['accepted_at']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($latestQuote['order_id'])): ?>
                    <div class="byoc-history-meta">Converted Order ID: #<?= (int)$latestQuote['order_id'] ?></div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div class="byoc-history">
                <div class="byoc-meta"><strong>Quote Timeline</strong></div>
                <?php $leadHistory = $quoteHistoryByLead[(int)$row['id']] ?? []; ?>
                <?php if (empty($leadHistory)): ?>
                  <div class="byoc-meta">No quote dispatch history yet.</div>
                <?php else: ?>
                  <?php foreach ($leadHistory as $h): ?>
                    <div class="byoc-history-item">
                      <div class="byoc-history-title">
                        <?= htmlspecialchars((string)($h['channel'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        •
                        <?= htmlspecialchars((string)($h['event_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                      </div>
                      <div class="byoc-history-meta">
                        Status: <?= htmlspecialchars((string)($h['status'] ?? 'queued'), ENT_QUOTES, 'UTF-8') ?>
                        | Recipient: <?= htmlspecialchars((string)($h['recipient'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        | At: <?= htmlspecialchars((string)($h['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                      </div>
                      <?php if (!empty($h['provider_message_id'])): ?>
                        <div class="byoc-history-meta">Provider ID: <?= htmlspecialchars((string)$h['provider_message_id'], ENT_QUOTES, 'UTF-8') ?></div>
                      <?php endif; ?>
                      <?php if (!empty($h['error_message'])): ?>
                        <div class="byoc-history-meta">Error: <?= htmlspecialchars((string)$h['error_message'], ENT_QUOTES, 'UTF-8') ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
document.querySelectorAll('[data-edit-quote]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var form = document.querySelector('[data-send-form="' + btn.dataset.editQuote + '"]');
    if (!form) return;
    form.querySelector('[name="quote_subject"]').value = btn.dataset.subject || '';
    form.querySelector('[name="quote_amount"]').value = btn.dataset.amount || '';
    form.querySelector('[name="quote_message"]').value = btn.dataset.message || '';
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.querySelector('[name="quote_subject"]').focus();
  });
});

document.querySelectorAll('.qt-chip').forEach(function(chip) {
  chip.addEventListener('click', function() {
    var form = chip.closest('form');
    if (!form) return;
    var subjectEl = form.querySelector('[name="quote_subject"]');
    var messageEl = form.querySelector('[name="quote_message"]');
    if (subjectEl && subjectEl.value.trim() === '') {
      subjectEl.value = chip.dataset.subject || '';
    }
    if (messageEl) {
      messageEl.value = chip.dataset.msg || '';
      messageEl.focus();
    }
  });
});
</script>
