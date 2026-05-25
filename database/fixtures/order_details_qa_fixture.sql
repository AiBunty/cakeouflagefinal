-- Order Details QA Fixture
-- Purpose: repeatable local validation dataset for admin/order_details.php
-- Usage (inside DB container):
--   mysql -u root -p"$MYSQL_ROOT_PASSWORD" cakeouflage_local < /var/www/html/database/fixtures/order_details_qa_fixture.sql

START TRANSACTION;

-- Clean prior fixture rows so reruns are idempotent.
DELETE FROM payment_receipts
WHERE source_event = 'qa_seed'
  AND source_reference LIKE 'qa-seed-QA-DETAIL-%';

DELETE FROM order_items
WHERE order_id IN (
  SELECT id FROM orders WHERE order_number LIKE 'QA-DETAIL-%'
);

DELETE FROM orders
WHERE order_number LIKE 'QA-DETAIL-%';

-- Use first available product for item snapshots.
SET @qa_product_id := (
  SELECT id
  FROM products
  ORDER BY id ASC
  LIMIT 1
);
SET @qa_product_name := (
  SELECT name
  FROM products
  ORDER BY id ASC
  LIMIT 1
);

INSERT INTO orders (
  order_number,
  user_id,
  customer_name,
  customer_email,
  customer_phone,
  customer_phone_e164,
  fulfilment_mode,
  order_status,
  payment_status,
  refund_status,
  refund_amount,
  total_refunded,
  settlement_reference,
  payment_confirmed_at,
  payment_confirmed_by_admin_id,
  payment_method,
  order_source,
  order_mode,
  scheduled_slot,
  scheduled_slot_label,
  requires_kitchen_production,
  production_status,
  delivery_postal_code,
  delivery_street,
  billing_address_line1,
  billing_city,
  billing_state,
  billing_postal_code,
  delivery_fee,
  subtotal,
  discount_total,
  tax_total,
  grand_total,
  gross_amount,
  advance_amount,
  advance_received_amount,
  net_collected_amount,
  balance_due_amount,
  collection_status,
  followup_status,
  followup_count,
  collection_priority,
  admin_note,
  created_at,
  updated_at
) VALUES
(
  'QA-DETAIL-PENDING',
  1,
  'QA Pending Customer',
  'qa.pending.customer@cakeouflage.local',
  '9999901001',
  '+919999901001',
  'pickup',
  'pending_payment',
  'pending',
  'none',
  0,
  0,
  '',
  NULL,
  NULL,
  'upi_manual',
  'retail',
  'online',
  '2026-05-26 11:00:00',
  '26 May 2026 | 11:00 AM - 01:00 PM',
  1,
  'pending',
  '422005',
  'Cakeouflage QA Lane, Nashik',
  'Cakeouflage QA Lane, Nashik',
  'Nashik',
  'Maharashtra',
  '422005',
  80,
  1250,
  50,
  0,
  1280,
  1280,
  0,
  0,
  0,
  1280,
  'payment_pending',
  'no_reminder',
  0,
  'normal',
  'Operator note for pending QA order',
  '2026-05-25 10:01:00',
  '2026-05-25 10:01:00'
),
(
  'QA-DETAIL-CONFIRMED',
  1,
  'QA Confirmed Customer',
  'qa.confirmed.customer@cakeouflage.local',
  '9999901002',
  '+919999901002',
  'delivery',
  'confirmed',
  'paid',
  'none',
  0,
  0,
  'QA-TXN-CONFIRMED',
  '2026-05-25 10:02:00',
  1,
  'upi_manual',
  'retail',
  'online',
  '2026-05-26 11:00:00',
  '26 May 2026 | 11:00 AM - 01:00 PM',
  1,
  'pending',
  '422005',
  'Cakeouflage QA Lane, Nashik',
  'Cakeouflage QA Lane, Nashik',
  'Nashik',
  'Maharashtra',
  '422005',
  100,
  1450,
  0,
  0,
  1550,
  1550,
  1550,
  1550,
  1550,
  0,
  'fully_paid',
  'no_reminder',
  0,
  'normal',
  'Confirmed order note',
  '2026-05-25 10:02:00',
  '2026-05-25 10:02:00'
),
(
  'QA-DETAIL-PREPARING',
  1,
  'QA Preparing Customer',
  'qa.preparing.customer@cakeouflage.local',
  '9999901003',
  '+919999901003',
  'pickup',
  'preparing',
  'paid',
  'none',
  0,
  0,
  'QA-TXN-PREPARING',
  '2026-05-25 10:03:00',
  1,
  'upi_manual',
  'retail',
  'online',
  '2026-05-26 11:00:00',
  '26 May 2026 | 11:00 AM - 01:00 PM',
  1,
  'in_production',
  '422005',
  'Cakeouflage QA Lane, Nashik',
  'Cakeouflage QA Lane, Nashik',
  'Nashik',
  'Maharashtra',
  '422005',
  120,
  1600,
  100,
  0,
  1620,
  1620,
  1620,
  1620,
  1620,
  0,
  'fully_paid',
  'no_reminder',
  0,
  'normal',
  'Preparing order special handling',
  '2026-05-25 10:03:00',
  '2026-05-25 10:03:00'
),
(
  'QA-DETAIL-DELIVERED',
  1,
  'QA Delivered Customer',
  'qa.delivered.customer@cakeouflage.local',
  '9999901004',
  '+919999901004',
  'delivery',
  'delivered',
  'paid',
  'none',
  0,
  0,
  'QA-TXN-DELIVERED',
  '2026-05-25 10:04:00',
  1,
  'upi_manual',
  'retail',
  'online',
  '2026-05-26 11:00:00',
  '26 May 2026 | 11:00 AM - 01:00 PM',
  1,
  'delivered',
  '422005',
  'Cakeouflage QA Lane, Nashik',
  'Cakeouflage QA Lane, Nashik',
  'Nashik',
  'Maharashtra',
  '422005',
  150,
  1750,
  0,
  0,
  1900,
  1900,
  1900,
  1900,
  1900,
  0,
  'fully_paid',
  'no_reminder',
  0,
  'normal',
  'Delivered order audit note',
  '2026-05-25 10:04:00',
  '2026-05-25 10:04:00'
),
(
  'QA-DETAIL-REFUNDED',
  1,
  'QA Refunded Customer',
  'qa.refunded.customer@cakeouflage.local',
  '9999901005',
  '+919999901005',
  'pickup',
  'refunded',
  'refunded',
  'fully_refunded',
  1500,
  1500,
  'QA-TXN-REFUNDED',
  '2026-05-25 10:05:00',
  1,
  'upi_manual',
  'retail',
  'online',
  '2026-05-26 11:00:00',
  '26 May 2026 | 11:00 AM - 01:00 PM',
  1,
  'delivered',
  '422005',
  'Cakeouflage QA Lane, Nashik',
  'Cakeouflage QA Lane, Nashik',
  'Nashik',
  'Maharashtra',
  '422005',
  100,
  1400,
  0,
  0,
  1500,
  0,
  1500,
  1500,
  0,
  0,
  'refunded',
  'no_reminder',
  0,
  'normal',
  'Refunded QA order',
  '2026-05-25 10:05:00',
  '2026-05-25 10:05:00'
);

INSERT INTO order_items (
  order_id,
  product_id,
  product_name_snapshot,
  variant_snapshot,
  unit_price,
  quantity,
  line_total,
  customisation_note,
  cake_message,
  topper_name_snapshot,
  topper_price_snapshot,
  created_at
)
SELECT
  o.id,
  @qa_product_id,
  @qa_product_name,
  v.variant_snapshot,
  v.unit_price,
  v.quantity,
  v.line_total,
  'Fondant finish, QA customisation block',
  v.cake_message,
  v.topper_name_snapshot,
  120.00,
  o.created_at
FROM orders o
JOIN (
  SELECT 'QA-DETAIL-PENDING' AS order_number, '1.5 kg' AS variant_snapshot, 1250.00 AS unit_price, 1 AS quantity, 1250.00 AS line_total, 'Happy QA Team' AS cake_message, 'Gold Script Topper' AS topper_name_snapshot
  UNION ALL
  SELECT 'QA-DETAIL-CONFIRMED', '2 kg', 1450.00, 1, 1450.00, 'Bake fast, scan faster', 'Birthday Crown'
  UNION ALL
  SELECT 'QA-DETAIL-PREPARING', '1.5 kg', 1600.00, 1, 1600.00, 'Happy QA Team', 'Gold Script Topper'
  UNION ALL
  SELECT 'QA-DETAIL-DELIVERED', '2 kg', 1750.00, 1, 1750.00, 'Bake fast, scan faster', 'Birthday Crown'
  UNION ALL
  SELECT 'QA-DETAIL-REFUNDED', '1.5 kg', 1400.00, 1, 1400.00, 'Happy QA Team', 'Gold Script Topper'
) v ON v.order_number = o.order_number
WHERE o.order_number LIKE 'QA-DETAIL-%'
  AND @qa_product_id IS NOT NULL;

INSERT INTO payment_receipts (
  order_id,
  receipt_number,
  receipt_type,
  sequence_no,
  amount,
  balance_due,
  payment_method,
  payment_status_snapshot,
  collection_status_snapshot,
  source_event,
  source_reference,
  issued_by_admin_id,
  issued_at,
  created_at,
  updated_at
)
SELECT
  o.id,
  r.receipt_number,
  r.receipt_type,
  1,
  r.amount,
  r.balance_due,
  'upi_manual',
  r.payment_status_snapshot,
  r.collection_status_snapshot,
  'qa_seed',
  CONCAT('qa-seed-', o.order_number),
  1,
  o.created_at,
  NOW(),
  NOW()
FROM orders o
JOIN (
  SELECT 'QA-DETAIL-CONFIRMED' AS order_number, 'RCPT-CONFIRMED' AS receipt_number, 'advance' AS receipt_type, 1550.00 AS amount, 0.00 AS balance_due, 'paid' AS payment_status_snapshot, 'fully_paid' AS collection_status_snapshot
  UNION ALL
  SELECT 'QA-DETAIL-PREPARING', 'RCPT-PREPARING', 'advance', 1620.00, 0.00, 'paid', 'fully_paid'
  UNION ALL
  SELECT 'QA-DETAIL-DELIVERED', 'RCPT-DELIVERED', 'advance', 1900.00, 0.00, 'paid', 'fully_paid'
  UNION ALL
  SELECT 'QA-DETAIL-REFUNDED', 'RCPT-REFUNDED', 'final', 1500.00, 0.00, 'refunded', 'refunded'
) r ON r.order_number = o.order_number;

COMMIT;
