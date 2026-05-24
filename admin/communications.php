<?php
$pageTitle = 'Communications';
require_once __DIR__ . '/layout.php';
require_admin_permission('crm_settings');

if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
  $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$commCsrfToken = (string)$_SESSION['_csrf_token'];
$commRoutingFlash = '';

function comm_build_email_template(array $cfg): string
{
  $accent = (string)($cfg['accent'] ?? '#80001F');
  $bg = (string)($cfg['bg'] ?? '#f5eef2');
  $panelBg = (string)($cfg['panel_bg'] ?? '#fff7f9');
  $panelBorder = (string)($cfg['panel_border'] ?? '#f0d7df');
  $footer = (string)($cfg['footer'] ?? '#140b0f');
  $tagline = (string)($cfg['tagline'] ?? '{{business_name}} Notifications');
  $heading = (string)($cfg['heading'] ?? 'Hi {{customer_name}}');
  $lead = (string)($cfg['lead'] ?? 'Cakeouflage has an update for you.');
  $notice = (string)($cfg['notice'] ?? '');
  $detailsHtml = trim((string)($cfg['details_html'] ?? ''));
  $ctaHtml = '';
  if (!empty($cfg['cta_text'])) {
    $ctaBg = !empty($cfg['cta_bg']) ? $cfg['cta_bg'] : $accent;
    $ctaHtml = '<div style="margin-top:30px;"><a href="' . htmlspecialchars((string)$cfg['cta_link'], ENT_QUOTES, 'UTF-8') . '" style="background:' . $ctaBg . ';color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;">' . htmlspecialchars((string)$cfg['cta_text'], ENT_QUOTES, 'UTF-8') . '</a></div>';
  }

  $detailsSection = $detailsHtml !== ''
    ? '<div style="margin-top:28px;background:' . $panelBg . ';border:1px solid ' . $panelBorder . ';border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;">' . $detailsHtml . '</div>'
    : '';
  $noticeSection = $notice !== ''
    ? '<div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">' . $notice . '</div>'
    : '';

  return '<div style="background:' . $bg . ';padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:' . $accent . ';padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{email_logo_url}}" alt="{{business_name}} Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">' . $tagline . '</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">' . $heading . '</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">' . $lead . '</p>' . $detailsSection . $noticeSection . $ctaHtml . '</div><div style="background:' . $footer . ';padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team {{business_name}}</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p><p style="color:#d7c6cc;font-size:14px;">&#9993; {{support_email}} &nbsp;|&nbsp; &#128222; {{support_phone}}</p></div></div></div>';
}

// ── Auto-seed and auto-repair email templates so none remain blank/minimal ──
(function () use ($conn) {
  $templates = [
    'online_order_received_customer' => [
      'subject' => 'Order Received - {{order_number}}',
      'tagline' => 'Online Order Received',
      'lead' => 'Thank you for your order with Cakeouflage. We have received it and our team is preparing your celebration.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Total:</strong> &#8377;{{grand_total}}</p>',
      'notice' => 'We will keep you posted as your order moves forward.',
    ],
    'online_order_received_admin' => [
      'subject' => 'New Online Order - {{order_number}}',
      'tagline' => 'New Online Order',
      'lead' => 'A new online order has been received and is ready for team review.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p>',
      'notice' => 'Please review fulfilment details and continue the workflow.',
    ],
    'manual_order_received_customer' => [
      'subject' => 'Manual Order Received - {{order_number}}',
      'tagline' => 'Manual Order Received',
      'lead' => 'Your order has been recorded by the Cakeouflage team and is now in processing.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Total:</strong> &#8377;{{grand_total}}</p>',
      'notice' => 'If we need any clarification, we will contact you shortly.',
    ],
    'manual_order_received_admin' => [
      'subject' => 'New Manual Order - {{order_number}}',
      'tagline' => 'Manual Order Alert',
      'lead' => 'A manual order has been punched in from admin and needs fulfilment review.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p>',
      'notice' => 'Please verify the order details and continue the workflow.',
    ],
    'payment_confirmed_customer' => [
      'subject' => 'Payment Confirmed - {{order_number}}',
      'tagline' => 'Payment Confirmed',
      'accent' => '#166534',
      'bg' => '#eef8f1',
      'panel_bg' => '#f0fdf4',
      'panel_border' => '#bbf7d0',
      'footer' => '#052e16',
      'lead' => 'We have received your payment and your order is now confirmed.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Paid:</strong> &#8377;{{grand_total}}</p>',
      'notice' => 'Our team has started preparing your order.',
    ],
    'payment_confirmed_admin' => [
      'subject' => 'Payment Confirmed - {{order_number}}',
      'tagline' => 'Payment Confirmed',
      'accent' => '#166534',
      'bg' => '#eef8f1',
      'panel_bg' => '#f0fdf4',
      'panel_border' => '#bbf7d0',
      'footer' => '#052e16',
      'lead' => 'Payment has been confirmed and the order can move into production.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Customer Email:</strong> {{customer_email}}</p>',
      'notice' => 'Please update the operations timeline as needed.',
    ],
    'ready_order_customer' => [
      'subject' => 'Order Ready - {{order_number}}',
      'tagline' => 'Order Ready',
      'accent' => '#1d4ed8',
      'bg' => '#eff6ff',
      'panel_bg' => '#eff6ff',
      'panel_border' => '#bfdbfe',
      'footer' => '#172554',
      'lead' => 'Great news, your Cakeouflage order is now ready.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p>',
      'notice' => 'Your order is ready for pickup or delivery.',
    ],
    'ready_order_admin' => [
      'subject' => 'Order Ready - {{order_number}}',
      'tagline' => 'Order Ready',
      'accent' => '#1d4ed8',
      'bg' => '#eff6ff',
      'panel_bg' => '#eff6ff',
      'panel_border' => '#bfdbfe',
      'footer' => '#172554',
      'lead' => 'The order is ready and the team should coordinate dispatch or pickup.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Customer Email:</strong> {{customer_email}}</p>',
      'notice' => 'Please update the fulfilment status in the admin flow.',
    ],
    'order_delivered_customer' => [
      'subject' => 'Order Delivered - {{order_number}}',
      'tagline' => 'Order Delivered',
      'accent' => '#0f766e',
      'bg' => '#ecfdf5',
      'panel_bg' => '#f0fdfa',
      'panel_border' => '#99f6e4',
      'footer' => '#134e4a',
      'lead' => 'Your Cakeouflage order has been delivered. Thank you for celebrating with us.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p>',
      'notice' => 'We hope you loved every bite.',
    ],
    'order_delivered_admin' => [
      'subject' => 'Order Delivered - {{order_number}}',
      'tagline' => 'Delivery Alert',
      'accent' => '#0f766e',
      'bg' => '#ecfdf5',
      'panel_bg' => '#f0fdfa',
      'panel_border' => '#99f6e4',
      'footer' => '#134e4a',
      'lead' => 'The order is marked delivered and follow-up tracking can begin.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p>',
      'notice' => 'Please make any follow-up updates required for operations.',
    ],
    'reject_order_customer' => [
      'subject' => 'Order Rejected - {{order_number}}',
      'tagline' => 'Order Rejected',
      'accent' => '#991b1b',
      'bg' => '#fff1f2',
      'panel_bg' => '#fef2f2',
      'panel_border' => '#fecaca',
      'footer' => '#450a0a',
      'lead' => 'We could not verify your payment successfully, so the order could not be processed.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p>',
      'notice' => 'If you would like to place the order again, please try again when ready.',
      'cta_text' => 'Place Order Again',
      'cta_link' => 'https://cakeouflage.com',
      'cta_bg' => '#991b1b',
    ],
    'reject_order_admin' => [
      'subject' => 'Order Rejected - {{order_number}}',
      'tagline' => 'Order Rejected',
      'accent' => '#991b1b',
      'bg' => '#fff1f2',
      'panel_bg' => '#fef2f2',
      'panel_border' => '#fecaca',
      'footer' => '#450a0a',
      'lead' => 'The order was rejected after payment verification and needs admin visibility.',
      'details_html' => '<p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p>',
      'notice' => 'Please review the rejection details in the admin workflow.',
    ],
    'follow_up_review_email' => [
      'subject' => 'Share Your Cakeouflage Experience',
      'tagline' => 'Quarterly Follow-up Reminder',
      'lead' => 'You last ordered in {{last_order_month}}. We will be happy to serve you again for your next celebration.',
      'notice' => 'If you loved your order, please leave a quick Google review. It helps us grow and serve you better.',
      'cta_text' => 'Write Google Review',
      'cta_link' => '{{google_review_link}}',
      'cta_bg' => '#80001F',
    ],
    'annual_reorder_email' => [
      'subject' => 'Your Celebration Date Is Coming Soon',
      'tagline' => 'Annual Celebration Reminder',
      'accent' => '#166534',
      'bg' => '#eef8f1',
      'panel_bg' => '#f0fdf4',
      'panel_border' => '#bbf7d0',
      'footer' => '#052e16',
      'lead' => 'Your yearly celebration date is just one week away.',
      'notice' => 'To avoid any last moment rush, order your celebration cake now and get it ready on your desired date.',
      'cta_text' => 'Book Your Cake Now',
      'cta_link' => '{{profile_link}}',
      'cta_bg' => '#166534',
    ],
    'birthday_greeting_email' => [
      'subject' => 'Happy Birthday from Cakeouflage',
      'tagline' => 'Birthday Wishes',
      'accent' => '#6d002f',
      'bg' => '#fff2f6',
      'panel_bg' => '#fff7fa',
      'panel_border' => '#f5d0df',
      'footer' => '#2f0a18',
      'lead' => 'Wishing you a beautiful birthday filled with joy, warmth, and sweet memories.',
      'notice' => 'Reserve your signature Cakeouflage creation for a celebration made to remember.',
      'cta_text' => 'Design My Birthday Cake',
      'cta_link' => 'https://cakeouflage.com/shop',
      'cta_bg' => '#80001F',
    ],
    'birthday_preorder_email' => [
      'subject' => 'Your Birthday Celebration Is Near',
      'tagline' => 'Birthday Preorder Reminder',
      'accent' => '#7a123a',
      'bg' => '#fff4f8',
      'panel_bg' => '#fff8fb',
      'panel_border' => '#f8d7e4',
      'footer' => '#3a1021',
      'lead' => 'Your birthday date is approaching. Secure your preferred cake style and delivery slot in advance.',
      'notice' => 'Early booking helps us craft every detail exactly the way you want.',
      'cta_text' => 'Preorder Birthday Cake',
      'cta_link' => 'https://cakeouflage.com/shop',
      'cta_bg' => '#80001F',
    ],
    'anniversary_greeting_email' => [
      'subject' => 'Happy Anniversary from Cakeouflage',
      'tagline' => 'Anniversary Wishes',
      'accent' => '#7a0017',
      'bg' => '#fff3f2',
      'panel_bg' => '#fff8f7',
      'panel_border' => '#f6d5d1',
      'footer' => '#3a0a0f',
      'lead' => 'Wishing you an elegant anniversary celebration filled with love and beautiful moments.',
      'notice' => 'We would be delighted to craft a centerpiece cake for your special day.',
      'cta_text' => 'Explore Anniversary Cakes',
      'cta_link' => 'https://cakeouflage.com/shop',
      'cta_bg' => '#80001F',
    ],
    'anniversary_preorder_email' => [
      'subject' => 'Plan Your Anniversary Cake In Advance',
      'tagline' => 'Anniversary Preorder Reminder',
      'accent' => '#6b1227',
      'bg' => '#fff4f4',
      'panel_bg' => '#fff9f9',
      'panel_border' => '#f4d8dd',
      'footer' => '#35101a',
      'lead' => 'Your anniversary celebration is near. Book now to secure your preferred design and schedule.',
      'notice' => 'Advance planning ensures a seamless celebration experience.',
      'cta_text' => 'Preorder Anniversary Cake',
      'cta_link' => 'https://cakeouflage.com/shop',
      'cta_bg' => '#80001F',
    ],
    'celebration_combined_email' => [
      'subject' => 'Special Celebration Wishes from Cakeouflage',
      'tagline' => 'Celebration Wishes',
      'accent' => '#5f0017',
      'bg' => '#fff1f6',
      'panel_bg' => '#fff7fa',
      'panel_border' => '#f0ccda',
      'footer' => '#280811',
      'lead' => 'Sending warm wishes for your special celebration from all of us at Cakeouflage.',
      'notice' => 'Let us craft a refined cake experience that matches your occasion perfectly.',
      'cta_text' => 'Plan My Celebration Cake',
      'cta_link' => 'https://cakeouflage.com/shop',
      'cta_bg' => '#80001F',
    ],
    'build_your_cake_inquiry_customer_email' => [
      'subject' => 'We Received Your Custom Cake Inquiry #{{inquiry_id}}',
      'tagline' => 'Build Your Cake Inquiry',
      'lead' => 'Thank you for sharing your custom cake brief with Cakeouflage. Our design team will review your requirements and get back with a quote soon.',
      'details_html' => '<p><strong>Inquiry ID:</strong> {{inquiry_id}}</p><p><strong>Event:</strong> {{event_information}}</p><p><strong>Event Date:</strong> {{event_date}}</p><p><strong>Servings:</strong> {{number_of_servings_guests}}</p><p><strong>Budget Range:</strong> {{budget_range}}</p><p><strong>Diet Preference:</strong> {{diet_preference}}</p><p><strong>Design Notes:</strong> {{design_brief_notes}}</p>',
      'notice' => 'Need to add more details? Reply to this email and our team will assist you.',
    ],
    'build_your_cake_inquiry_admin_email' => [
      'subject' => 'New BYOC Inquiry #{{inquiry_id}} - {{customer_name}}',
      'tagline' => 'New Build Your Cake Lead',
      'lead' => 'A new Build Your Cake inquiry has been submitted and is ready for quote action.',
      'details_html' => '<p><strong>Inquiry ID:</strong> {{inquiry_id}}</p><p><strong>Name:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Mobile:</strong> {{phone_country_code}} {{customer_phone}}</p><p><strong>Event:</strong> {{event_information}}</p><p><strong>Event Date:</strong> {{event_date}}</p><p><strong>Servings:</strong> {{number_of_servings_guests}}</p><p><strong>Budget:</strong> {{budget_range}}</p><p><strong>Diet:</strong> {{diet_preference}}</p><p><strong>Quote Description:</strong> {{quote_description}}</p>',
      'notice' => 'Review the lead quickly and send quote from tele-calling panel for best conversion.',
    ],
    'build_your_cake_quote_email' => [
      'subject' => 'Your Custom Cake Quote Is Ready - {{quote_number}}',
      'tagline' => 'Custom Cake Quote Ready',
      'accent' => '#1d4ed8',
      'bg' => '#eff6ff',
      'panel_bg' => '#eff6ff',
      'panel_border' => '#bfdbfe',
      'footer' => '#172554',
      'lead' => 'Your custom cake quote has been prepared by Cakeouflage. Please review and confirm if you would like us to proceed.',
      'details_html' => '<p><strong>Quote Number:</strong> {{quote_number}}</p><p><strong>Inquiry ID:</strong> {{inquiry_id}}</p><p><strong>Amount:</strong> {{currency}} {{quote_amount}}</p><p><strong>Quote Summary:</strong> {{quote_description}}</p>',
      'notice' => 'The quote link remains active for the configured validity window.',
      'cta_text' => 'View & Accept Quote',
      'cta_link' => '{{quote_accept_link}}',
      'cta_bg' => '#1d4ed8',
    ],
    'password_reset' => [
      'subject' => 'Password Reset Request',
      'tagline' => 'Password Reset',
      'lead' => 'We received a request to reset your password. If this was you, use the secure link below.',
      'notice' => 'If you did not request this, you can safely ignore this email.',
      'cta_text' => 'Reset Password',
      'cta_link' => '{{reset_link}}',
      'cta_bg' => '#80001F',
    ],
    'invoice_paid' => [
      'subject' => 'Invoice - {{order_number}}',
      'tagline' => 'Invoice Paid',
      'accent' => '#0f766e',
      'bg' => '#ecfdf5',
      'panel_bg' => '#f0fdfa',
      'panel_border' => '#99f6e4',
      'footer' => '#134e4a',
      'lead' => 'Thank you for your payment. Your invoice is attached below.',
      'details_html' => '<div>{{invoice_html}}</div>',
      'notice' => 'If you need help with billing, please contact Team Cakeouflage.',
    ],
    'byoc_order_confirmed_customer' => [
      'subject' => 'Your Custom Cake Order Is Confirmed - {{order_number}}',
      'tagline' => 'BYOC Order Confirmed (Customer)',
      'accent' => '#166534',
      'bg' => '#f0fdf4',
      'panel_bg' => '#dcfce7',
      'panel_border' => '#bbf7d0',
      'footer' => '#14532d',
      'lead' => 'Your custom cake order has been confirmed by Cakeouflage.',
      'details_html' => '<p><strong>Order Number:</strong> {{order_number}}</p><p><strong>Order Total:</strong> {{currency}} {{grand_total}}</p><p><strong>Advance Paid:</strong> {{currency}} {{advance_amount}}</p><p><strong>Remaining Balance:</strong> {{currency}} {{remaining_balance}}</p><p><strong>Delivery Address:</strong> {{delivery_address}}</p>',
      'notice' => 'Our team will contact you to confirm delivery details.',
    ],
    'byoc_order_confirmed_admin' => [
      'subject' => 'New BYOC Order - {{order_number}} from {{customer_name}}',
      'tagline' => 'BYOC Order Confirmed (Admin)',
      'accent' => '#5b21b6',
      'bg' => '#f5f3ff',
      'panel_bg' => '#ede9fe',
      'panel_border' => '#ddd6fe',
      'footer' => '#4c1d95',
      'lead' => 'A BYOC quote has been accepted by a customer and converted to an order.',
      'details_html' => '<p><strong>Order Number:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p><p><strong>Order Total:</strong> {{currency}} {{grand_total}}</p><p><strong>Advance Paid:</strong> {{currency}} {{advance_amount}}</p><p><strong>Payment Status:</strong> {{payment_status}}</p><p><strong>Delivery Address:</strong> {{delivery_address}}</p>',
      'notice' => 'Please review and confirm this order in the admin panel.',
    ],
  ];

  $tpls = [];
  foreach ($templates as $eventKey => $cfg) {
    $tpls[] = [
      'email',
      $eventKey,
      $cfg['subject'],
      comm_build_email_template($cfg),
    ];
  }

  $sql = 'INSERT INTO communication_templates (channel, event_key, subject, body_template, is_active) VALUES (?,?,?,?,1) AS new
      ON DUPLICATE KEY UPDATE
      subject = CASE
        WHEN new.event_key IN ("follow_up_review_email", "annual_reorder_email", "birthday_greeting_email", "birthday_preorder_email", "anniversary_greeting_email", "anniversary_preorder_email", "celebration_combined_email") THEN new.subject
        WHEN TRIM(COALESCE(communication_templates.subject, "")) = "" THEN new.subject
        ELSE communication_templates.subject
      END,
      body_template = CASE
        WHEN new.event_key IN ("follow_up_review_email", "annual_reorder_email", "birthday_greeting_email", "birthday_preorder_email", "anniversary_greeting_email", "anniversary_preorder_email", "celebration_combined_email") THEN new.body_template
        WHEN TRIM(COALESCE(communication_templates.body_template, "")) = "" THEN new.body_template
        WHEN LOWER(REPLACE(TRIM(communication_templates.body_template), " ", "")) IN ("<p><br></p>", "<p></p>") THEN new.body_template
        WHEN LENGTH(COALESCE(communication_templates.body_template, "")) < 180 THEN new.body_template
        WHEN communication_templates.body_template NOT LIKE "%<div%" THEN new.body_template
        ELSE communication_templates.body_template
      END,
      is_active = COALESCE(communication_templates.is_active, 1)';

  $stmt = $conn->prepare($sql);
  foreach ($tpls as $t) {
    $stmt->bind_param('ssss', $t[0], $t[1], $t[2], $t[3]);
    $stmt->execute();
  }
  $stmt->close();

  // ── One-time migration: replace any legacy hardcoded imgbb logo with the
  //    {{email_logo_url}} placeholder so branding is now dynamic. ──────────
  $conn->query(
    "UPDATE communication_templates
     SET body_template = REPLACE(body_template, 'https://i.ibb.co/hRytXC3F/whitelogo.png', '{{email_logo_url}}')
     WHERE body_template LIKE '%i.ibb.co%'"
  );
  // Also replace old hardcoded alt text and footer brand name.
  $conn->query(
    "UPDATE communication_templates
     SET body_template = REPLACE(body_template, 'alt=\"Cakeouflage Logo\"', 'alt=\"{{business_name}} Logo\"')
     WHERE body_template LIKE '%alt=\"Cakeouflage Logo\"%'"
  );
  $conn->query(
    "UPDATE communication_templates
     SET body_template = REPLACE(body_template, 'Team Cakeouflage', 'Team {{business_name}}')
     WHERE body_template LIKE '%Team Cakeouflage%'"
  );

  // Communication template table is master-of-truth for all queue-driven email events.
  $emailEventKeys = array_keys($templates);
  if (!empty($emailEventKeys)) {
    $in = implode(',', array_fill(0, count($emailEventKeys), '?'));
    $types = str_repeat('s', count($emailEventKeys));
    $purgeSql = 'DELETE FROM communication_templates WHERE channel = "email" AND event_key NOT IN (' . $in . ')';
    $purgeStmt = $conn->prepare($purgeSql);
    if ($purgeStmt) {
      $purgeStmt->bind_param($types, ...$emailEventKeys);
      $purgeStmt->execute();
      $purgeStmt->close();
    }
  }

  // OTP is intentionally excluded from communication templates.
  $conn->query("DELETE FROM communication_templates WHERE channel = 'email' AND (event_key = 'otp' OR event_key LIKE 'otp_%')");
})();

$triggerDiagnostics = [];
$crmTriggerDiagnostics = [];
$commRoutingSettings = [
  'communication_admin_to_email' => 'cakeouflage@gmail.com',
  'communication_admin_cc_admin_ids' => '',
];
$selectedCommCcAdminIds = [];
$activeAdminUsers = [];

if (isset($conn) && $conn instanceof mysqli) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_admin_email_routing') {
    $postedToken = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals($commCsrfToken, $postedToken)) {
      $commRoutingFlash = 'Invalid CSRF token. Please refresh and try again.';
    } else {
      $adminEmail = trim((string)($_POST['communication_admin_to_email'] ?? ''));
      if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $commRoutingFlash = 'Please provide a valid admin destination email.';
      } else {
        $ccAdminIds = $_POST['communication_admin_cc_admin_ids'] ?? [];
        if (!is_array($ccAdminIds)) {
          $ccAdminIds = [];
        }
        $ccAdminIds = array_values(array_unique(array_filter(array_map(static function ($value): int {
          return (int)$value;
        }, $ccAdminIds), static function (int $id): bool {
          return $id > 0;
        })));

        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        $saveStmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value, updated_by_admin_id) VALUES (?, ?, ?) AS new ON DUPLICATE KEY UPDATE setting_value = new.setting_value, updated_by_admin_id = new.updated_by_admin_id');
        if ($saveStmt) {
          $keyTo = 'communication_admin_to_email';
          $saveStmt->bind_param('ssi', $keyTo, $adminEmail, $adminId);
          $saveStmt->execute();

          $keyCc = 'communication_admin_cc_admin_ids';
          $ccCsv = implode(',', $ccAdminIds);
          $saveStmt->bind_param('ssi', $keyCc, $ccCsv, $adminId);
          $saveStmt->execute();
          $saveStmt->close();
          $commRoutingFlash = 'Communication admin routing saved successfully.';
        } else {
          $commRoutingFlash = 'Unable to save communication routing settings right now.';
        }
      }
    }
  }

  $settingsSql = 'SELECT setting_key, setting_value FROM settings WHERE setting_key IN ("communication_admin_to_email", "communication_admin_cc_admin_ids")';
  $settingsRes = $conn->query($settingsSql);
  while ($settingsRes && ($settingRow = $settingsRes->fetch_assoc())) {
    $settingKey = (string)($settingRow['setting_key'] ?? '');
    if (array_key_exists($settingKey, $commRoutingSettings)) {
      $commRoutingSettings[$settingKey] = (string)($settingRow['setting_value'] ?? '');
    }
  }

  $selectedCommCcAdminIds = array_values(array_unique(array_filter(array_map(static function (string $value): int {
    return (int)$value;
  }, preg_split('/\s*,\s*/', (string)$commRoutingSettings['communication_admin_cc_admin_ids']) ?: []), static function (int $id): bool {
    return $id > 0;
  })));

  $activeAdminUsers = [];
  $adminRes = $conn->query('SELECT id, full_name, email FROM admins WHERE is_active = 1 ORDER BY full_name ASC, id ASC');
  while ($adminRes && ($adminRow = $adminRes->fetch_assoc())) {
    $activeAdminUsers[] = [
      'id' => (int)($adminRow['id'] ?? 0),
      'full_name' => trim((string)($adminRow['full_name'] ?? 'Admin User')),
      'email' => trim((string)($adminRow['email'] ?? '')),
    ];
  }

  $emailDiagSql = 'SELECT id, event_key, recipient, status, payload_json, created_at FROM communication_logs WHERE channel = "email" ORDER BY id DESC LIMIT 25';
  $emailDiag = $conn->query($emailDiagSql);
  while ($emailDiag && ($row = $emailDiag->fetch_assoc())) {
    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : array();
    $triggerDiagnostics[] = array(
      'id' => (int)($row['id'] ?? 0),
      'requested_key' => trim((string)($payload['trigger_requested_key'] ?? ($row['event_key'] ?? ''))),
      'resolved_key' => trim((string)($payload['trigger_resolved_key'] ?? ($row['event_key'] ?? ''))),
      'recipient' => trim((string)($row['recipient'] ?? '')),
      'status' => trim((string)($row['status'] ?? 'queued')),
      'created_at' => trim((string)($row['created_at'] ?? '')),
    );
  }

  $crmDiagSql = 'SELECT id, status, payload_json, created_at FROM queue_jobs WHERE job_type = "crm_trigger_push" ORDER BY id DESC LIMIT 25';
  $crmDiag = $conn->query($crmDiagSql);
  while ($crmDiag && ($row = $crmDiag->fetch_assoc())) {
    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : array();
    $crmTriggerDiagnostics[] = array(
      'id' => (int)($row['id'] ?? 0),
      'requested_key' => trim((string)($payload['setting_key_requested'] ?? ($payload['setting_key'] ?? ''))),
      'resolved_key' => trim((string)($payload['setting_key_resolved'] ?? ($payload['setting_key'] ?? ''))),
      'status' => trim((string)($row['status'] ?? 'queued')),
      'created_at' => trim((string)($row['created_at'] ?? '')),
    );
  }
}
?>
<!-- Visual editor styles -->

<style>
/* ── layout ─────────────────────────────── */
.comm-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 4px 0 32px;
}

/* quick-nav bar */
.comm-quicknav {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.comm-quicknav a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 9px;
  border: 1px solid rgba(128,0,31,.14);
  background: #fff8fa;
  color: #80001F;
  font-size: 0.88rem;
  font-weight: 600;
  text-decoration: none;
  transition: background 130ms, box-shadow 130ms, transform 130ms;
}
.comm-quicknav a:hover {
  background: #ede4e8;
  box-shadow: 0 3px 10px rgba(128,0,31,.10);
  transform: translateY(-1px);
}

.comm-routing-card {
  background: #fff;
  border: 1px solid rgba(128,0,31,.12);
  border-radius: 16px;
  padding: 14px;
}
.comm-routing-head h2 {
  margin: 0;
  color: #80001F;
  font-size: 1.06rem;
}
.comm-routing-note {
  margin: 6px 0 0;
  color: #8f6c79;
  font-size: .85rem;
}
.comm-routing-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}
.comm-routing-field label {
  display: block;
  margin-bottom: 6px;
  color: #6e2a3e;
  font-size: .8rem;
  font-weight: 700;
  letter-spacing: .02em;
}
.comm-routing-field input,
.comm-routing-field select {
  width: 100%;
  border: 1px solid rgba(128,0,31,.18);
  border-radius: 10px;
  min-height: 40px;
  padding: 8px 10px;
  font: inherit;
}
.comm-routing-field select {
  min-height: 130px;
}
.comm-routing-actions {
  margin-top: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.comm-routing-flash {
  font-size: .82rem;
  color: #166534;
}

/* card */
.comm-card {
  background: #fffdfd;
  border-radius: 20px;
  border: 1px solid rgba(128,0,31,.10);
  box-shadow: 0 10px 28px rgba(96,18,45,.07);
  overflow: hidden;
}
.comm-card__head {
  padding: 22px 26px 14px;
  border-bottom: 1px solid rgba(128,0,31,.07);
  background: linear-gradient(180deg,#fff8fa 0%,#fff 100%);
  display: flex;
  align-items: center;
  gap: 14px;
}
.comm-card__head h2 {
  margin: 0;
  font-family: 'DM Serif Display', Georgia, serif;
  color: #80001F;
  font-size: 1.3rem;
  font-weight: 400;
  flex: 1;
}

/* channel tabs */
.comm-tabs {
  display: flex;
  gap: 6px;
  padding: 14px 26px 0;
}
.comm-tab {
  padding: 7px 18px;
.wa-bind-card {
  background: #fff;
  border: 1px solid rgba(128,0,31,.10);
  border-radius: 14px;
  padding: 14px;
}
.wa-bind-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: 1.2fr 1fr auto;
  align-items: end;
}
.wa-bind-grid label {
  display: block;
  font-size: .78rem;
  color: #6e4756;
  margin-bottom: 4px;
}
.wa-bind-grid select {
  width: 100%;
  min-height: 38px;
  border: 1px solid #e6ccd5;
  border-radius: 9px;
  padding: 0 10px;
}
.wa-bind-meta {
  margin-top: 8px;
  color: #7b5f6c;
  font-size: .78rem;
}
.wa-bind-status {
  margin-left: 10px;
  font-size: .8rem;
}
.wa-bind-status.ok { color: #1f6b3d; }
.wa-bind-status.err { color: #9b1f1f; }
  border-radius: 9px 9px 0 0;
  border: 1px solid rgba(128,0,31,.12);
  border-bottom: none;
  .wa-bind-grid { grid-template-columns: 1fr; }
  background: #f9f4f6;
  color: #80001F;
  font-size: 0.89rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 120ms;
}
.comm-tab.active {
  background: #80001F;
  color: #fff;
}

/* editor split */
.comm-editor {
  display: grid;
  grid-template-columns: 280px 1fr;
  min-height: 520px;
}
@media (max-width: 760px) {
  .comm-editor { grid-template-columns: 1fr; }
}
.comm-list {
  border-right: 1px solid rgba(128,0,31,.08);
  overflow-y: auto;
  max-height: 700px;
}
.comm-list-item {
  padding: 13px 18px;
  border-bottom: 1px solid rgba(128,0,31,.06);
  cursor: pointer;
  transition: background 110ms;
}
.comm-list-item:hover { background: #fff2f5; }
.comm-list-item.selected { background: #fff2f5; border-left: 3px solid #80001F; }
.comm-list-item__label {
  font-weight: 600;
  font-size: 0.9rem;
  color: #3d1020;
}
.comm-list-item__key {
  font-size: 0.76rem;
  color: #9a7080;
  margin-top: 2px;
}
.comm-list-item__badge {
  display: inline-block;
  margin-top: 4px;
  padding: 1px 8px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
}
.badge-active { background: #d1fae5; color: #065f46; }
.badge-inactive { background: #fee2e2; color: #991b1b; }

/* right pane */
.comm-pane {
  padding: 22px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
}
.comm-pane-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #c4a0ad;
  font-size: 0.95rem;
}
.comm-field label {
  display: block;
  font-weight: 600;
  font-size: 0.88rem;
  color: #6e2a3e;
  margin-bottom: 5px;
}
.comm-input {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid rgba(128,0,31,.16);
  border-radius: 10px;
  padding: 9px 13px;
  font: inherit;
  font-size: 0.95rem;
  color: #3d1020;
  background: #fffcfd;
  transition: border-color 120ms, box-shadow 120ms;
}
.comm-input:focus {
  outline: none;
  border-color: #80001F;
  box-shadow: 0 0 0 3px rgba(128,0,31,.09);
}
.comm-textarea {
  resize: vertical;
  min-height: 280px;
  font-family: 'Fira Code', 'Cascadia Code', Consolas, monospace;
  font-size: 0.83rem;
  line-height: 1.55;
}
.comm-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.comm-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
  color: #6e2a3e;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
}
.comm-toggle input[type=checkbox] {
  accent-color: #80001F;
  width: 17px;
  height: 17px;
}
.btn-save {
  padding: 9px 26px;
  background: #80001F;
  color: #fff;
  border: none;
  border-radius: 11px;
  font: inherit;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 130ms, box-shadow 130ms, transform 130ms;
}
.btn-save:hover { background: #a0002a; box-shadow: 0 4px 14px rgba(128,0,31,.22); transform: translateY(-1px); }
.btn-save:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-preview {
  padding: 8px 18px;
  background: transparent;
  color: #80001F;
  border: 1.5px solid rgba(128,0,31,.3);
  border-radius: 11px;
  font: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 130ms, border-color 130ms;
}
.btn-preview:hover { background: #fff2f5; border-color: #80001F; }
.comm-status {
  font-size: 0.85rem;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 8px;
  display: none;
}
.comm-status.ok { display: inline-block; background: #d1fae5; color: #065f46; }
.comm-status.err { display: inline-block; background: #fee2e2; color: #991b1b; }

/* preview iframe */
.comm-preview-wrap {
  display: none;
  border: 1px solid rgba(128,0,31,.12);
  border-radius: 12px;
  overflow: hidden;
}
.comm-preview-wrap.visible { display: block; }
.comm-preview-iframe {
  width: 100%;
  height: 420px;
  border: none;
  display: block;
  background: #fff;
}

/* spinner */
.comm-spinner {
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
  vertical-align: middle;
  margin-right: 4px;
  display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* loading overlay for list */
.comm-loading {
  padding: 40px;
  text-align: center;
  color: #c4a0ad;
  font-size: 0.9rem;
}

/* ── Editor toolbar wrapper ────────────────────────────── */
.comm-editor-toolbar-wrap {
  display: flex;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 6px;
  border: 1px solid rgba(128,0,31,.15);
  border-bottom: none;
  border-radius: 12px 12px 0 0;
  background: #fffcfd;
  padding: 6px 10px;
}
.comm-rich-toolbar {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 4px 0 2px;
  border-top: 1px solid rgba(128,0,31,.10);
}
.comm-tool-group {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding-right: 6px;
  margin-right: 2px;
  border-right: 1px solid rgba(128,0,31,.10);
}
.comm-tool-group:last-child {
  border-right: none;
  padding-right: 0;
  margin-right: 0;
}
.comm-tool-btn {
  height: 30px;
  min-width: 30px;
  padding: 0 8px;
  border: 1px solid rgba(128,0,31,.22);
  border-radius: 7px;
  background: #fff;
  color: #3d1020;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
}
.comm-tool-btn:hover {
  background: #fff3f6;
  border-color: rgba(128,0,31,.34);
}
.comm-tool-btn:disabled {
  opacity: .45;
  cursor: not-allowed;
}
.comm-tool-select {
  height: 30px;
  border: 1px solid rgba(128,0,31,.22);
  border-radius: 7px;
  background: #fff;
  color: #3d1020;
  font: inherit;
  font-size: 0.8rem;
  padding: 0 8px;
}
.comm-tool-color {
  width: 32px;
  height: 30px;
  border: 1px solid rgba(128,0,31,.22);
  border-radius: 7px;
  background: #fff;
  padding: 2px;
  cursor: pointer;
}
.comm-tool-title {
  font-size: 0.72rem;
  color: #9a7080;
  padding: 0 2px;
}
/* visual-mode info bar */
#visualInfoBar {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  color: #9a7080;
  padding: 0 4px;
}
.comm-editor-extra-btns {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-shrink: 0;
  padding: 3px 0;
}
.btn-src {
  padding: 5px 11px;
  background: #fff;
  color: #3d1020;
  border: 1px solid rgba(128,0,31,.22);
  border-radius: 8px;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background 120ms, color 120ms;
}
.btn-src:hover, .btn-src.active { background: #3d1020; color: #fff; border-color: #3d1020; }
.btn-vars {
  padding: 5px 11px;
  background: #80001F;
  color: #fff;
  border: 1px solid #80001F;
  border-radius: 8px;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background 120ms;
}
.btn-vars:hover { background: #a0002a; border-color: #a0002a; }

/* Visual editable iframe */
#visualEditWrap {
  border: 1px solid rgba(128,0,31,.15);
  border-radius: 0 0 12px 12px;
  overflow: hidden;
  background: #f2eef0;
}
#visualEditFrame {
  width: 100%;
  min-height: 420px;
  border: none;
  display: block;
  background: transparent;
}

/* Source textarea */
.comm-textarea-source {
  min-height: 420px;
  border-radius: 0 0 12px 12px;
  border-top: none;
  font-family: 'Consolas', 'Courier New', monospace;
  font-size: 12.5px;
  line-height: 1.55;
  white-space: pre;
  word-wrap: normal;
  overflow-x: auto;
  tab-size: 2;
  color: #1d1115;
  background: #fdf8fa;
}

/* ── Custom Values Panel ─────────────────────────────── */
.cv-panel {
  position: fixed;
  top: 0;
  right: -320px;
  width: 300px;
  height: 100vh;
  background: #fff;
  border-left: 1px solid rgba(128,0,31,.15);
  box-shadow: -8px 0 32px rgba(128,0,31,.12);
  z-index: 1000;
  transition: right 0.25s ease;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.cv-panel.open { right: 0; }
.cv-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.22);
  z-index: 999;
}
.cv-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  background: linear-gradient(135deg, #80001F 0%, #a0002a 100%);
  color: #fff;
  font-family: 'DM Serif Display', Georgia, serif;
  font-size: 1.05rem;
  flex-shrink: 0;
}
.cv-close {
  background: none;
  border: none;
  color: #fff;
  font-size: 1.2rem;
  cursor: pointer;
  padding: 2px 8px;
  border-radius: 6px;
  transition: background 120ms;
}
.cv-close:hover { background: rgba(255,255,255,.2); }
.cv-panel__body {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
}
.cv-group { margin-bottom: 14px; }
.cv-group__label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #80001F;
  margin: 0 0 7px;
  padding: 3px 8px;
  background: #fff2f5;
  border-radius: 6px;
}
.cv-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  border-radius: 9px;
  border: 1px solid rgba(128,0,31,.10);
  background: #fffcfd;
  margin-bottom: 5px;
  transition: background 110ms;
}
.cv-chip:hover { background: #fff2f5; }
.cv-chip__token {
  font-family: 'Fira Code', Consolas, monospace;
  font-size: 0.77rem;
  color: #80001F;
  font-weight: 600;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cv-chip__name {
  font-size: 0.72rem;
  color: #9a7080;
  flex-shrink: 0;
}
.cv-chip__actions { display: flex; gap: 3px; }
.cv-btn {
  background: none;
  border: 1px solid rgba(128,0,31,.15);
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.78rem;
  padding: 3px 7px;
  transition: background 110ms, border-color 110ms;
  line-height: 1;
}
.cv-btn:hover { background: #fff2f5; border-color: #80001F; }
.cv-btn--insert { background: #fff5f8; }
.cv-toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: #1d1115;
  color: #fff;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 0.88rem;
  z-index: 2000;
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}

.comm-diag {
  margin-top: 14px;
  border-radius: 16px;
  border: 1px solid rgba(128,0,31,.10);
  background: #fff;
  box-shadow: 0 8px 24px rgba(96,18,45,.06);
  overflow: hidden;
}
.comm-diag__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(128,0,31,.08);
  background: #fff8fa;
}
.comm-diag__head h3 {
  margin: 0;
  font-size: .98rem;
  color: #80001F;
}
.comm-diag__meta {
  margin: 0;
  font-size: .76rem;
  color: #8f6c79;
}
.comm-diag__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.comm-diag__section {
  padding: 14px 16px 16px;
}
.comm-diag__section + .comm-diag__section {
  border-left: 1px solid rgba(128,0,31,.08);
}
.comm-diag__title {
  margin: 0 0 10px;
  font-size: .84rem;
  color: #6f0d2b;
  font-weight: 700;
}
.comm-diag-table-wrap {
  overflow-x: auto;
  border: 1px solid rgba(128,0,31,.08);
  border-radius: 10px;
}
.comm-diag-table {
  width: 100%;
  min-width: 560px;
  border-collapse: collapse;
}
.comm-diag-table th,
.comm-diag-table td {
  font-size: .76rem;
  padding: 8px 9px;
  border-bottom: 1px solid rgba(128,0,31,.08);
  text-align: left;
  white-space: nowrap;
}
.comm-diag-table th {
  background: #fff7f9;
  color: #6f0d2b;
}
.comm-diag-table td.key {
  font-family: 'Fira Code', Consolas, monospace;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.comm-diag-table tr.alias-hit td {
  background: #fff7ed;
}
.comm-diag-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: 6px;
  padding: 2px 6px;
  border-radius: 999px;
  border: 1px solid #fdba74;
  background: #ffedd5;
  color: #9a3412;
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .02em;
  text-transform: uppercase;
}
.comm-diag-empty {
  margin: 0;
  font-size: .78rem;
  color: #8f6c79;
}
@media (max-width: 980px) {
  .comm-diag__grid {
    grid-template-columns: 1fr;
  }
  .comm-diag__section + .comm-diag__section {
    border-left: 0;
    border-top: 1px solid rgba(128,0,31,.08);
  }
}
</style>

<div class="comm-shell">

  <!-- Quick nav -->
  <div class="comm-quicknav">
    <a href="crm_settings.php">⚙️ CRM Settings</a>
    <a href="follow_ups.php">👥 Follow Ups</a>
    <a href="crm_push_logs.php">📋 Push Logs</a>
    <a href="crm_report.php">📊 CRM Report</a>
  </div>

  <form class="comm-routing-card" method="post" action="communications.php">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($commCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="save_admin_email_routing">
    <div class="comm-routing-head">
      <h2>Admin Email Routing</h2>
      <p class="comm-routing-note">Admin-targeted communication events are sent to this primary mailbox. Optional CC users receive the same admin template in parallel.</p>
    </div>
    <div class="comm-routing-grid">
      <div class="comm-routing-field">
        <label for="communication_admin_to_email">Primary Admin Email</label>
        <input type="email" id="communication_admin_to_email" name="communication_admin_to_email" required value="<?= htmlspecialchars((string)($commRoutingSettings['communication_admin_to_email'] ?? 'cakeouflage@gmail.com'), ENT_QUOTES, 'UTF-8') ?>" placeholder="ops@cakeouflage.com">
      </div>
      <div class="comm-routing-field">
        <label for="communication_admin_cc_admin_ids">CC Admin Users (optional)</label>
        <select id="communication_admin_cc_admin_ids" name="communication_admin_cc_admin_ids[]" multiple>
          <?php foreach ($activeAdminUsers as $adminUser): ?>
            <option value="<?= (int)$adminUser['id'] ?>" <?= in_array((int)$adminUser['id'], $selectedCommCcAdminIds, true) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$adminUser['full_name'], ENT_QUOTES, 'UTF-8') ?><?= $adminUser['email'] !== '' ? ' (' . htmlspecialchars((string)$adminUser['email'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="comm-routing-actions">
      <button class="btn-save" type="submit">Save Admin Routing</button>
      <?php if ($commRoutingFlash !== ''): ?><span class="comm-routing-flash"><?= htmlspecialchars($commRoutingFlash, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
    </div>
  </form>

  <div class="wa-bind-card">
    <div class="comm-card__head" style="padding:8px 0 12px;border:0;background:none;">
      <h2 style="font-size:1.1rem;">WhatsApp Event Binding</h2>
    </div>
    <div class="wa-bind-grid">
      <div>
        <label for="byocWaTemplateSelect">Managed Template (approved only)</label>
        <select id="byocWaTemplateSelect">
          <option value="">Loading approved templates...</option>
        </select>
      </div>
      <div>
        <label for="byocWaMappingActive">Mapping Status</label>
        <select id="byocWaMappingActive">
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>
      <div>
        <button class="btn-save" type="button" id="byocWaSaveBtn" onclick="saveByocWhatsAppBinding()">Save Binding</button>
        <span class="wa-bind-status" id="byocWaStatus"></span>
      </div>
    </div>
    <div class="wa-bind-meta" id="byocWaMeta">Event Key: <strong>build_your_cake_quote_whatsapp</strong></div>
  </div>

  <!-- Main card -->
  <div class="comm-card">
    <div class="comm-card__head">
      <h2>📧 Communication Templates</h2>
    </div>

    <!-- Channel tabs -->
    <div class="comm-tabs">
      <div class="comm-tab active" data-channel="email" onclick="switchChannel('email', this)">Email</div>
      <div class="comm-tab" data-channel="whatsapp" onclick="switchChannel('whatsapp', this)">WhatsApp</div>
    </div>

    <div class="comm-editor">
      <!-- Left: template list -->
      <div class="comm-list" id="commList">
        <div class="comm-loading">Loading templates…</div>
      </div>

      <!-- Right: editor pane -->
      <div class="comm-pane" id="commPane">
        <div class="comm-pane-empty" id="commPaneEmpty">← Select a template to edit</div>

        <div id="commEditor" style="display:none; flex-direction:column; gap:14px;">

          <!-- Subject (email only) -->
          <div class="comm-field" id="subjectWrap">
            <label>Subject</label>
            <input type="text" id="subjectInput" class="comm-input" placeholder="Email subject line…">
          </div>

          <!-- TinyMCE editor (email only) -->
          <div id="editorToolbarWrap">
            <div class="comm-editor-toolbar-wrap" style="padding:8px 12px;border-bottom:1px solid #f0d7df;">
              <div class="comm-editor-extra-btns">
                <button type="button" class="btn-vars" onclick="toggleCustomValues()">&#123;&#123;&#125;&#125; Variables</button>
              </div>
            </div>
            <!-- TinyMCE mounts here -->
            <textarea id="tinymceEditor" class="comm-input" placeholder="Email body HTML…" style="width:100%;min-height:420px;"></textarea>
          </div>

          <!-- WhatsApp plain-text body (no toolbar) -->
          <div id="waBodyWrap" style="display:none;" class="comm-field">
            <label>Message Body <span style="font-size:.75rem;color:#9a7080;font-weight:400;">(use {{variable}} placeholders)</span></label>
            <textarea id="waBodyInput" class="comm-input comm-textarea" placeholder="WhatsApp message template…"></textarea>
          </div>

          <!-- Active toggle + actions -->
          <div class="comm-row">
            <label class="comm-toggle">
              <input type="checkbox" id="activeToggle"> Active
            </label>
            <button class="btn-preview" type="button" id="btnPreview" onclick="togglePreview()" style="display:none;">👁 Preview</button>
            <button class="btn-save" type="button" id="btnSave" onclick="saveTemplate()">
              <span class="comm-spinner" id="saveSpin"></span>Save Template
            </button>
            <button class="btn-preview" type="button" id="btnSendTest" onclick="openTestEmailPanel()" style="display:none;">✉ Send Test</button>
            <span class="comm-status" id="commStatus"></span>
          </div>

          <!-- Send Test Email panel (email channel only) -->
          <div id="testEmailPanel" style="display:none;margin-top:14px;background:#fff7f9;border:1px solid #f0d7df;border-radius:12px;padding:18px;">
            <p style="margin:0 0 10px;font-weight:600;color:#80001F;">Send Test Email</p>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <input type="email" id="testEmailRecipient" placeholder="recipient@example.com"
                     style="flex:1;min-width:220px;padding:9px 14px;border:1px solid #e2c8d1;border-radius:8px;font-size:14px;">
              <button type="button" onclick="sendTestEmail()"
                      style="background:#80001F;color:#fff;padding:9px 18px;border:none;border-radius:8px;font-weight:600;cursor:pointer;white-space:nowrap;">
                <span id="testEmailSpin" class="comm-spinner"></span>Send
              </button>
            </div>
            <div id="testEmailStatus" style="margin-top:8px;font-size:13px;"></div>
          </div>

          <!-- Preview iframe (email only) -->
          <div class="comm-preview-wrap" id="previewWrap">
            <iframe class="comm-preview-iframe" id="previewFrame" title="Email Preview"></iframe>
          </div>

        </div><!-- /commEditor -->
      </div><!-- /comm-pane -->
    </div><!-- /comm-editor -->
  </div><!-- /comm-card -->

  <div class="comm-diag">
    <div class="comm-diag__head">
      <h3>🔎 Trigger Resolution Diagnostics</h3>
      <p class="comm-diag__meta">Shows requested vs resolved keys for alias compatibility during rollout.</p>
    </div>
    <div class="comm-diag__grid">
      <section class="comm-diag__section">
        <p class="comm-diag__title">Email Trigger Queue (last 25)</p>
        <?php if (empty($triggerDiagnostics)): ?>
          <p class="comm-diag-empty">No email trigger diagnostics found yet.</p>
        <?php else: ?>
          <div class="comm-diag-table-wrap">
            <table class="comm-diag-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Requested Key</th>
                  <th>Resolved Key</th>
                  <th>Recipient</th>
                  <th>Status</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($triggerDiagnostics as $diag): ?>
                  <?php $isAliasFallback = (string)$diag['requested_key'] !== (string)$diag['resolved_key']; ?>
                  <tr class="<?= $isAliasFallback ? 'alias-hit' : '' ?>">
                    <td><?= (int)$diag['id'] ?></td>
                    <td class="key" title="<?= htmlspecialchars((string)$diag['requested_key'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$diag['requested_key'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="key" title="<?= htmlspecialchars((string)$diag['resolved_key'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars((string)$diag['resolved_key'], ENT_QUOTES, 'UTF-8') ?>
                      <?php if ($isAliasFallback): ?><span class="comm-diag-badge">Alias</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string)$diag['recipient'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$diag['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$diag['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="comm-diag__section">
        <p class="comm-diag__title">CRM Trigger Jobs (last 25)</p>
        <?php if (empty($crmTriggerDiagnostics)): ?>
          <p class="comm-diag-empty">No CRM trigger diagnostics found yet.</p>
        <?php else: ?>
          <div class="comm-diag-table-wrap">
            <table class="comm-diag-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Requested Key</th>
                  <th>Resolved Key</th>
                  <th>Status</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($crmTriggerDiagnostics as $diag): ?>
                  <?php $isAliasFallback = (string)$diag['requested_key'] !== (string)$diag['resolved_key']; ?>
                  <tr class="<?= $isAliasFallback ? 'alias-hit' : '' ?>">
                    <td><?= (int)$diag['id'] ?></td>
                    <td class="key" title="<?= htmlspecialchars((string)$diag['requested_key'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$diag['requested_key'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="key" title="<?= htmlspecialchars((string)$diag['resolved_key'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars((string)$diag['resolved_key'], ENT_QUOTES, 'UTF-8') ?>
                      <?php if ($isAliasFallback): ?><span class="comm-diag-badge">Alias</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string)$diag['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$diag['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>

</div><!-- /comm-shell -->

<!-- ── Custom Values Slide-in Panel ──────────────────────────── -->
<div id="customValuesPanel" class="cv-panel">
  <div class="cv-panel__head">
    <span>Custom Variables</span>
    <button class="cv-close" onclick="toggleCustomValues()">✕</button>
  </div>
  <div id="cvPanelBody" class="cv-panel__body"></div>
</div>
<div id="cvOverlay" class="cv-overlay" onclick="toggleCustomValues()" style="display:none;"></div>
<div id="cvToast" class="cv-toast"></div>

<script>
(function () {
  const commCsrfToken = <?= json_encode($commCsrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  function getCsrfToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    return commCsrfToken || (window.__csrf ?? '') || metaToken;
  }

  // ── state ──────────────────────────────────────────────────────────
  let allTemplates = [];
  let activeChannel = 'email';
  let selectedId    = null;
  let previewOpen   = false;
  const BYOC_WA_EVENT_KEY = 'build_your_cake_quote_whatsapp';

  // ── Custom variable groups ──────────────────────────────────────────
  const CV_GROUPS = [
    {
      label: 'Customer',
      vars: [
        { name: 'Customer Name',  token: '{{customer_name}}' },
        { name: 'First Name',     token: '{{first_name}}' },
        { name: 'Email',          token: '{{customer_email}}' },
        { name: 'Phone',          token: '{{customer_phone}}' },
      ],
    },
    {
      label: 'Order',
      vars: [
        { name: 'Order Number', token: '{{order_number}}' },
        { name: 'Item Names',   token: '{{item_names}}' },
        { name: 'Grand Total',  token: '{{grand_total}}' },
        { name: 'UPI Link',     token: '{{upi_link}}' },
        { name: 'Order ID',     token: '{{order_id}}' },
      ],
    },
  ];

  // ── pretty labels ───────────────────────────────────────────────────
  const EVENT_LABELS = {
    online_order_received_customer: 'Order Placed Online (Customer)',
    online_order_received_admin:    'Order Placed Online (Admin)',
    manual_order_received_customer: 'Order Received (Customer)',
    manual_order_received_admin:    'Order Received (Admin)',
    payment_confirmed_customer:     'Payment Confirmed',
    payment_confirmed_admin:        'Payment Confirmed (Admin)',
    ready_order_customer:           'Order Ready',
    ready_order_admin:              'Order Ready (Admin)',
    order_delivered_customer:       'Order Delivered (Customer)',
    order_delivered_admin:          'Order Delivered (Admin)',
    reject_order_customer:          'Order Rejected',
    reject_order_admin:             'Order Rejected (Admin)',
    follow_up_review_email:         'Follow-up Review',
    annual_reorder_email:           'Annual Reorder',
    birthday_greeting_email:        'Birthday Greeting',
    birthday_preorder_email:        'Birthday Preorder',
    anniversary_greeting_email:     'Anniversary Greeting',
    anniversary_preorder_email:     'Anniversary Preorder',
    celebration_combined_email:     'Celebration Combined',
    password_reset:                 'Password Reset',
    invoice_paid:                   'Invoice Paid',
  };
  function prettyKey(key) {
    return EVENT_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  // ── HTML pretty-printer ────────────────────────────────────────────
  function formatHtml(html) {
    if (!html || !html.trim()) return html;
    // Normalise: collapse existing whitespace between tags, then add newline
    let s = html.replace(/>\s*</g, '>\n<').trim();
    const INLINE = /^<(a|abbr|acronym|b|bdo|big|br|button|cite|code|dfn|em|i|img|input|kbd|label|map|object|output|q|s|samp|select|small|span|strong|sub|sup|textarea|time|tt|u|var)[\s\/>/]/i;
    const VOID   = /^<(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)(\s|>|\/)/i;
    let depth = 0;
    const indent = (n) => '  '.repeat(Math.max(0, n));
    const lines  = s.split('\n');
    const out    = [];
    for (let raw of lines) {
      const line = raw.trim();
      if (!line) continue;
      // DOCTYPE / comments stay at current depth without changing it
      if (/^<!/.test(line)) { out.push(indent(depth) + line); continue; }
      // Closing tag → dedent first
      if (/^<\//.test(line)) {
        depth = Math.max(0, depth - 1);
        out.push(indent(depth) + line);
        continue;
      }
      // Self-closing / void tags → no depth change
      if (VOID.test(line) || /\/>$/.test(line)) {
        out.push(indent(depth) + line);
        continue;
      }
      // Inline tags — keep at current depth, no indent bump
      if (INLINE.test(line)) {
        out.push(indent(depth) + line);
        continue;
      }
      // Opening tag
      out.push(indent(depth) + line);
      if (/^<[^/!][^>]*>(?!.*<\/[^>]+>)/.test(line)) depth++;
    }
    return out.join('\n');
  }

  // ── get editor HTML ─────────────────────────────────────────────────
  function getEditorHTML() {
    const t = allTemplates.find(x => x.id == selectedId);
    if (!t) return '';
    if (t.channel !== 'email') return document.getElementById('waBodyInput').value;
    const editor = window.tinymce && tinymce.get('tinymceEditor');
    return editor ? editor.getContent() : '';
  }

  // ── Custom Values panel ─────────────────────────────────────────────
  window.toggleCustomValues = function () {
    const panel   = document.getElementById('customValuesPanel');
    const overlay = document.getElementById('cvOverlay');
    const isOpen  = panel.classList.contains('open');
    if (isOpen) {
      panel.classList.remove('open');
      overlay.style.display = 'none';
    } else {
      panel.classList.add('open');
      overlay.style.display = '';
    }
  };

  window.copyVar = function (token) {
    navigator.clipboard.writeText(token).then(() => {
      showToast('Copied: ' + token);
    }).catch(() => {
      const tmp = document.createElement('textarea');
      tmp.value = token;
      document.body.appendChild(tmp);
      tmp.select();
      document.execCommand('copy');
      document.body.removeChild(tmp);
      showToast('Copied!');
    });
  };

  window.insertVar = function (token) {
    const t = allTemplates.find(x => x.id == selectedId);
    if (!t) return;
    if (t.channel !== 'email') {
      const ta  = document.getElementById('waBodyInput');
      const pos = ta.selectionStart;
      ta.value  = ta.value.slice(0, pos) + token + ta.value.slice(ta.selectionEnd);
      ta.selectionStart = ta.selectionEnd = pos + token.length;
      ta.focus();
    } else {
      // Insert into TinyMCE at cursor position
      const editor = window.tinymce && tinymce.get('tinymceEditor');
      if (editor) {
        editor.insertContent(token);
        editor.focus();
      } else {
        showToast('Editor not ready — please wait');
        return;
      }
    }
    showToast('Inserted: ' + token);
  };

  function showToast(msg) {
    const el = document.getElementById('cvToast');
    if (!el) return;
    el.textContent   = msg;
    el.style.opacity = '1';
    clearTimeout(el._tid);
    el._tid = setTimeout(() => { el.style.opacity = '0'; }, 1800);
  }

  // ── build Custom Values panel content ──────────────────────────────
  function buildCvPanel() {
    const body = document.getElementById('cvPanelBody');
    if (!body) return;
    body.innerHTML = CV_GROUPS.map(g => `
      <div class="cv-group">
        <div class="cv-group__label">${g.label}</div>
        ${g.vars.map(v => `
          <div class="cv-chip">
            <span class="cv-chip__token">${v.token}</span>
            <span class="cv-chip__name">${v.name}</span>
            <div class="cv-chip__actions">
              <button class="cv-btn cv-btn--copy" title="Copy" onclick="copyVar('${v.token}')">📋</button>
              <button class="cv-btn cv-btn--insert" title="Insert at cursor" onclick="insertVar('${v.token}')">➕</button>
            </div>
          </div>
        `).join('')}
      </div>
    `).join('');
  }

  // ── channel switch ──────────────────────────────────────────────────
  window.switchChannel = function (ch, tab) {
    activeChannel = ch;
    document.querySelectorAll('.comm-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    selectedId = null;
    renderList();
    clearPane();
  };

  // ── fetch all templates once ────────────────────────────────────────
  async function loadTemplates() {
    try {
      const r = await fetch('/api/admin/communication/templates', { credentials: 'same-origin' });
      const j = await r.json();
      allTemplates = j.data?.items ?? [];
      renderList();
    } catch (e) {
      document.getElementById('commList').innerHTML =
        '<div class="comm-loading" style="color:#991b1b;">Failed to load templates.</div>';
    }
  }

  // ── render list ─────────────────────────────────────────────────────
  function renderList() {
    const list     = document.getElementById('commList');
    const filtered = allTemplates.filter(t => t.channel === activeChannel);
    if (!filtered.length) {
      list.innerHTML = '<div class="comm-loading">No ' + activeChannel + ' templates found.</div>';
      return;
    }
    list.innerHTML = filtered.map(t => `
      <div class="comm-list-item${t.id == selectedId ? ' selected' : ''}"
           data-id="${t.id}" onclick="selectTemplate(${t.id})">
        <div class="comm-list-item__label">${prettyKey(t.event_key)}</div>
        <div class="comm-list-item__key">${t.event_key}</div>
        <span class="comm-list-item__badge ${t.is_active == 1 ? 'badge-active' : 'badge-inactive'}">
          ${t.is_active == 1 ? 'Active' : 'Inactive'}
        </span>
      </div>
    `).join('');
  }

  // ── select template ─────────────────────────────────────────────────
  window.selectTemplate = function (id) {
    selectedId    = id;
    const t       = allTemplates.find(x => x.id == id);
    if (!t) return;

    const isEmail = t.channel === 'email';
    renderList();

    // Subject field
    document.getElementById('subjectWrap').style.display = isEmail ? '' : 'none';
    document.getElementById('subjectInput').value        = t.subject ?? '';

    // Show/hide editor areas
    document.getElementById('editorToolbarWrap').style.display = isEmail ? '' : 'none';
    document.getElementById('waBodyWrap').style.display         = isEmail ? 'none' : '';
    document.getElementById('btnPreview').style.display         = isEmail ? '' : 'none';
    document.getElementById('btnSendTest').style.display        = isEmail ? '' : 'none';
    if (!isEmail) {
      const tp = document.getElementById('testEmailPanel');
      if (tp) { tp.style.display = 'none'; }
    }

    if (!isEmail) {
      document.getElementById('waBodyInput').value = t.body_template ?? '';
    } else {
      // Email — load into TinyMCE
      const rawHtml = t.body_template ?? '';
      const editor = window.tinymce && tinymce.get('tinymceEditor');
      if (editor) {
        editor.setContent(rawHtml);
      } else {
        // TinyMCE not yet ready; store for the init callback
        window._pendingEditorContent = rawHtml;
      }
    }

    // Active toggle
    document.getElementById('activeToggle').checked = t.is_active == 1;

    // Reset preview
    previewOpen = false;
    document.getElementById('previewWrap').classList.remove('visible');
    document.getElementById('btnPreview').textContent = '👁 Preview';

    // Show editor
    document.getElementById('commPaneEmpty').style.display = 'none';
    document.getElementById('commEditor').style.display    = 'flex';
    clearStatus();
  };

  // ── preview ─────────────────────────────────────────────────────────
  window.togglePreview = function () {
    previewOpen = !previewOpen;
    const wrap = document.getElementById('previewWrap');
    const btn  = document.getElementById('btnPreview');
    if (previewOpen) {
      const html = getEditorHTML();
      document.getElementById('previewFrame').srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;background:#f2eef0;">' + html + '</body></html>';
      wrap.classList.add('visible');
      btn.textContent = '✕ Close Preview';
    } else {
      wrap.classList.remove('visible');
      btn.textContent = '👁 Preview';
    }
  };

  // ── save ────────────────────────────────────────────────────────────
  window.saveTemplate = async function () {
    if (!selectedId) return;
    const t = allTemplates.find(x => x.id == selectedId);
    if (!t) return;

    const body = getEditorHTML().trim();
    if (!body) { showStatus('Body cannot be empty.', false); return; }

    const payload = {
      body_template: body,
      is_active: document.getElementById('activeToggle').checked ? 1 : 0,
      _csrf: getCsrfToken(),
    };
    if (t.channel === 'email') {
      payload.subject = document.getElementById('subjectInput').value.trim();
    }

    const btn  = document.getElementById('btnSave');
    const spin = document.getElementById('saveSpin');
    btn.disabled       = true;
    spin.style.display = 'inline-block';
    clearStatus();

    try {
      const r = await fetch('/api/admin/communication/templates/' + selectedId, {
        method:      'PATCH',
        credentials: 'same-origin',
        headers:     {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': getCsrfToken(),
        },
        body:        JSON.stringify(payload),
      });
      const j = await r.json();
      if (j.success) {
        const idx = allTemplates.findIndex(x => x.id == selectedId);
        if (idx !== -1) {
          if (payload.subject !== undefined) allTemplates[idx].subject = payload.subject;
          allTemplates[idx].body_template = payload.body_template;
          allTemplates[idx].is_active     = payload.is_active;
        }
        renderList();
        showStatus('Saved!', true);
      } else {
        showStatus(j.message || 'Save failed.', false);
      }
    } catch (e) {
      showStatus('Network error.', false);
    } finally {
      btn.disabled       = false;
      spin.style.display = 'none';
    }
  };

  // ── test email ──────────────────────────────────────────────────────
  window.openTestEmailPanel = function () {
    const panel = document.getElementById('testEmailPanel');
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    document.getElementById('testEmailStatus').textContent = '';
  };

  window.sendTestEmail = async function () {
    if (!selectedId) return;
    const email = document.getElementById('testEmailRecipient').value.trim();
    if (!email || !email.includes('@')) {
      document.getElementById('testEmailStatus').style.color = '#9f1239';
      document.getElementById('testEmailStatus').textContent = '✗ Please enter a valid email address.';
      return;
    }

    const spin = document.getElementById('testEmailSpin');
    spin.style.display = 'inline-block';
    document.getElementById('testEmailStatus').textContent = 'Sending…';

    try {
      const r = await fetch('/api/admin/communication/templates/' + selectedId + '/test', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({ email: email, _csrf: getCsrfToken() }),
      });
      const j = await r.json();
      const statusEl = document.getElementById('testEmailStatus');
      if (j.success) {
        statusEl.style.color = '#166534';
        statusEl.textContent = '✓ ' + j.message;
      } else {
        statusEl.style.color = '#9f1239';
        statusEl.textContent = '✗ ' + (j.message || 'Send failed.');
      }
    } catch (e) {
      document.getElementById('testEmailStatus').style.color = '#9f1239';
      document.getElementById('testEmailStatus').textContent = '✗ Network error: ' + e.message;
    } finally {
      spin.style.display = 'none';
    }
  };

  // ── helpers ─────────────────────────────────────────────────────────
  function clearPane() {
    document.getElementById('commPaneEmpty').style.display = '';
    document.getElementById('commEditor').style.display    = 'none';
    previewOpen = false;
    document.getElementById('previewWrap').classList.remove('visible');
    clearStatus();
  }
  function showStatus(msg, ok) {
    const el   = document.getElementById('commStatus');
    el.textContent = msg;
    el.className   = 'comm-status ' + (ok ? 'ok' : 'err');
  }
  function clearStatus() {
    document.getElementById('commStatus').className = 'comm-status';
  }

  function setByocWaStatus(message, ok) {
    const el = document.getElementById('byocWaStatus');
    if (!el) return;
    el.textContent = message || '';
    el.className = 'wa-bind-status ' + (ok ? 'ok' : 'err');
  }

  async function loadByocWhatsAppBinding() {
    const select = document.getElementById('byocWaTemplateSelect');
    const active = document.getElementById('byocWaMappingActive');
    const meta = document.getElementById('byocWaMeta');
    if (!select || !active || !meta) return;

    try {
      const r = await fetch('/api/admin/whatsapp/mappings', { credentials: 'same-origin' });
      const j = await r.json();
      const approved = j.data?.approved_templates || [];
      const mappings = j.data?.items || [];

      select.innerHTML = '<option value="">Select approved WhatsApp template</option>';
      approved.forEach((t) => {
        const opt = document.createElement('option');
        opt.value = String(t.id || '');
        const name = t.internal_name || t.meta_template_name || ('Template #' + t.id);
        opt.textContent = name + ' (' + (t.meta_template_name || 'no-meta-name') + ')';
        select.appendChild(opt);
      });

      const match = mappings.find((m) => String(m.event_key || '') === BYOC_WA_EVENT_KEY);
      if (match) {
        if (match.template_id) {
          select.value = String(match.template_id);
        }
        active.value = String(Number(match.is_active || 0));
        meta.innerHTML = 'Event Key: <strong>' + BYOC_WA_EVENT_KEY + '</strong> | Current: <strong>' + (match.internal_name || match.meta_template_name || ('Template #' + match.template_id)) + '</strong>';
      } else {
        meta.innerHTML = 'Event Key: <strong>' + BYOC_WA_EVENT_KEY + '</strong> | Current: <em>Not bound</em>';
      }
      setByocWaStatus('', true);
    } catch (e) {
      setByocWaStatus('Unable to load WhatsApp mapping.', false);
    }
  }

  window.saveByocWhatsAppBinding = async function () {
    const select = document.getElementById('byocWaTemplateSelect');
    const active = document.getElementById('byocWaMappingActive');
    const btn = document.getElementById('byocWaSaveBtn');
    if (!select || !active || !btn) return;

    const templateId = parseInt(select.value || '0', 10);
    if (Number.isNaN(templateId) || templateId <= 0) {
      setByocWaStatus('Select an approved template first.', false);
      return;
    }

    btn.disabled = true;
    setByocWaStatus('Saving...', true);
    try {
      const r = await fetch('/api/admin/whatsapp/mappings/' + encodeURIComponent(BYOC_WA_EVENT_KEY), {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({
          template_id: templateId,
          is_active: parseInt(active.value || '1', 10) === 1 ? 1 : 0,
          _csrf: getCsrfToken(),
        }),
      });
      const j = await r.json();
      if (!j.success) {
        setByocWaStatus(j.message || 'Failed to save binding.', false);
        return;
      }
      setByocWaStatus('Binding saved.', true);
      await loadByocWhatsAppBinding();
    } catch (e) {
      setByocWaStatus('Network error while saving binding.', false);
    } finally {
      btn.disabled = false;
    }
  };

  // ── init ────────────────────────────────────────────────────────────
  buildCvPanel();
  loadTemplates();
  loadByocWhatsAppBinding();
})();
</script>
<script src="https://cdn.tiny.cloud/1/91n6hff31u1xzckbtb4b2qmq31gv9orsmjd5cxwxwvtpe0qe/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
<script>
(function () {
  'use strict';
  // Wait for TinyMCE CDN to be available then initialise
  function waitAndInit() {
    if (typeof tinymce !== 'undefined') {
      var ta = document.getElementById('tinymceEditor');
      if (!ta) return;
      tinymce.init({
        selector: '#tinymceEditor',
        plugins: [
          'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'image', 'link',
          'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount',
          'checklist', 'mediaembed', 'casechange', 'formatpainter', 'pageembed',
          'a11ychecker', 'tinymcespellchecker', 'permanentpen', 'powerpaste',
          'advtable', 'advcode', 'advtemplate', 'tinymceai',
          'tableofcontents', 'footnotes', 'mergetags', 'autocorrect',
          'typography', 'inlinecss', 'markdown'
        ],
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | ' +
                 'forecolor backcolor | align lineheight | link image media table mergetags | ' +
                 'checklist numlist bullist indent outdent | emoticons charmap | ' +
                 'a11ycheck typography | searchreplace code | removeformat',
        height: 520,
        entity_encoding: 'raw',
        entities: '160,nbsp',
        verify_html: false,
        mergetags_prefix: '{{',
        mergetags_suffix: '}}',
        mergetags_list: [
          {
            title: 'Branding',
            menu: [
              { value: 'email_logo_url',        title: 'Email Logo URL' },
              { value: 'business_name',          title: 'Business Name' },
              { value: 'brand_primary_color',    title: 'Brand Primary Color' },
              { value: 'brand_secondary_color',  title: 'Brand Secondary Color' },
              { value: 'support_email',          title: 'Support Email' },
              { value: 'support_phone',          title: 'Support Phone' },
            ],
          },
          {
            title: 'Customer',
            menu: [
              { value: 'customer_name', title: 'Customer Name' },
              { value: 'first_name',    title: 'First Name' },
              { value: 'company_name',  title: 'Company Name' },
            ],
          },
          {
            title: 'Order',
            menu: [
              { value: 'order_number',         title: 'Order Number' },
              { value: 'item_details',          title: 'Item Details' },
              { value: 'cake_message',          title: 'Cake Message' },
              { value: 'topper_choice',         title: 'Topper Choice' },
              { value: 'topper_price',          title: 'Topper Price' },
              { value: 'special_instructions',  title: 'Special Instructions' },
              { value: 'delivery_date',         title: 'Delivery Date' },
              { value: 'pickup_time',           title: 'Pickup Time' },
            ],
          },
          {
            title: 'Invoice / Quote',
            menu: [
              { value: 'invoice_number', title: 'Invoice Number' },
              { value: 'invoice_amount', title: 'Invoice Amount' },
              { value: 'due_date',       title: 'Due Date' },
              { value: 'quote_number',   title: 'Quote Number' },
            ],
          },
          {
            title: 'Course / Workshop',
            menu: [
              { value: 'course_name', title: 'Course Name' },
              { value: 'batch_date',  title: 'Batch Date' },
            ],
          },
        ],
        tinymceai_token_provider: async function () {
          var apiKey = '91n6hff31u1xzckbtb4b2qmq31gv9orsmjd5cxwxwvtpe0qe';
          await fetch('https://demo.api.tiny.cloud/1/' + apiKey + '/auth/random', { method: 'POST', credentials: 'include' });
          var token = await fetch('https://demo.api.tiny.cloud/1/' + apiKey + '/jwt/tinymceai', { credentials: 'include' }).then(function (r) { return r.text(); });
          return { token: token };
        },
        images_upload_url: '/api/admin/media/upload',
        images_upload_handler: function (blobInfo) {
          return new Promise(function (resolve, reject) {
            var fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            var csrf = typeof getCsrfToken === 'function' ? getCsrfToken()
                     : ((document.querySelector('meta[name="csrf-token"]') || {}).content || '');
            fd.append('_csrf', csrf);
            fetch('/api/admin/media/upload', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
              body: fd,
            }).then(function (r) { return r.json(); }).then(function (j) {
              if (j && j.success && j.data && j.data.url) { resolve(j.data.url); }
              else { reject({ message: j.message || 'Upload failed', remove: true }); }
            }).catch(function (err) {
              reject({ message: err.message || 'Upload error', remove: true });
            });
          });
        },
        setup: function (editor) {
          window._tinyEmailEditor = editor;
          editor.on('init', function () {
            if (window._pendingEditorContent !== undefined) {
              editor.setContent(window._pendingEditorContent);
              delete window._pendingEditorContent;
            }
          });
        }
      });
    } else {
      setTimeout(waitAndInit, 100);
    }
  }
  waitAndInit();
})();
</script>
