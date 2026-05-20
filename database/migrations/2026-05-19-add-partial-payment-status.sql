-- Add 'partial' to orders.payment_status ENUM (advance / BYOC partial pay support)
-- Also adds 'credit' to payment_method for consistency with live DB

ALTER TABLE orders
  MODIFY COLUMN payment_status ENUM('pending','paid','failed','refunded','credit','partial') NOT NULL DEFAULT 'pending';

ALTER TABLE orders
  MODIFY COLUMN payment_method ENUM('upi_manual','cod','gateway','credit') NOT NULL DEFAULT 'upi_manual';
