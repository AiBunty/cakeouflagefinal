<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use Throwable;

/**
 * SlotService — Production-grade bakery slot reservation engine.
 *
 * Design principles:
 *  - Zero double-booking via atomic INSERT … ON DUPLICATE KEY UPDATE
 *  - Explicit DB transactions with rollback protection on every reservation
 *  - No N+1 queries — availability fetched in one JOIN
 *  - Fallback mode auto-activates when slots are unavailable
 *  - All events written to slot_booking_logs for audit
 *  - Legacy scheduled_slot_label compatibility preserved
 *
 * Usage:
 *   $svc = new SlotService($pdo);
 *   $result = $svc->getAvailableSlots('delivery', '2026-06-15');
 *   $reservation = $svc->reserveSlot(2, '2026-06-15', $orderId);
 */
final class SlotService
{
    private PDO $pdo;

    // ── Fallback mode threshold: if < this many active slots exist, activate fallback ──
    private const MIN_ACTIVE_SLOTS = 1;

    // ── Fast-selling threshold: show warning badge if remaining < this % of capacity ──
    private const FAST_SELLING_THRESHOLD = 0.30;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // PUBLIC: AVAILABILITY ENGINE
    // =========================================================================

    /**
     * Returns available slot data for a given mode and date.
     *
     * Return shape:
     * [
     *   'mode'  => 'slots' | 'fallback',
     *   'items' => [ SlotRow[], ... ]    // empty array in fallback mode
     * ]
     */
    public function getAvailableSlots(string $mode, string $date): array
    {
        try {
            // Validate mode
            if (!in_array($mode, ['delivery', 'pickup'], true)) {
                $mode = 'delivery';
            }

            // Validate date
            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj === false || $dateObj->format('Y-m-d') !== $date) {
                return $this->fallbackResponse('invalid_date');
            }

            // Check if slot system is usable
            if ($this->isFallbackMode()) {
                $this->logEvent(null, null, null, 'fallback', null, null, 'No active slots configured — fallback mode');
                return $this->fallbackResponse('no_slots_configured');
            }

            $now = new \DateTimeImmutable('now');
            $today = $now->format('Y-m-d');
            $isSameDay = ($date === $today);

            // Fetch slots + current capacities in ONE query (no N+1)
            $slots = $this->fetchSlotsWithCapacity($mode, $date, $isSameDay);

            if (empty($slots)) {
                return $this->fallbackResponse('no_slots_for_date');
            }

            $items = [];
            foreach ($slots as $row) {
                $slotItem = $this->buildSlotItem($row, $now, $date, $isSameDay);
                if ($slotItem !== null) {
                    $items[] = $slotItem;
                }
            }

            // If all slots filtered out (cutoff, full, etc.) → fallback
            if (empty($items)) {
                return $this->fallbackResponse('all_slots_unavailable');
            }

            return ['mode' => 'slots', 'items' => $items];

        } catch (Throwable $e) {
            $this->logEvent(null, null, null, 'fallback', null, null, 'SlotService exception: ' . $e->getMessage());
            error_log('[SlotService] getAvailableSlots error: ' . $e->getMessage());
            return $this->fallbackResponse('service_error');
        }
    }

    /**
     * Atomically reserves a slot for an order.
     *
     * Uses INSERT … ON DUPLICATE KEY UPDATE for lock-free atomic increment,
     * then immediately validates capacity before committing.
     *
     * Returns [
     *   'success'       => bool,
     *   'message'       => string,
     *   'slot_label'    => string,   // for legacy scheduled_slot_label
     *   'slot_name'     => string,
     *   'booked_count'  => int,
     *   'capacity'      => int,
     * ]
     *
     * @throws \RuntimeException on hard DB failure
     */
    public function reserveSlot(int $slotId, string $date, ?int $orderId = null): array
    {
        $alreadyInTransaction = $this->pdo->inTransaction();

        if (!$alreadyInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            // ── 1. Atomic increment ──────────────────────────────────────────
            $this->pdo->prepare(
                'INSERT INTO slot_capacities (slot_id, booking_date, booked_count)
                 VALUES (:slot_id, :booking_date, 1)
                 ON DUPLICATE KEY UPDATE booked_count = booked_count + 1'
            )->execute([
                'slot_id'      => $slotId,
                'booking_date' => $date,
            ]);

            // ── 2. Immediately read back current count + capacity ────────────
            $stmt = $this->pdo->prepare(
                'SELECT
                   s.id,
                   s.slot_label,
                   s.slot_name,
                   s.max_orders,
                   s.is_active,
                   sc.booked_count,
                   COALESCE(ex.override_capacity, s.max_orders) AS effective_capacity,
                   ex.is_closed
                 FROM slot_capacities sc
                 JOIN order_slots s    ON s.id = sc.slot_id
                 LEFT JOIN order_slot_exceptions ex
                   ON ex.slot_id = sc.slot_id AND ex.exception_date = :date
                 WHERE sc.slot_id = :slot_id
                   AND sc.booking_date = :date2
                 LIMIT 1'
            );
            $stmt->execute([
                'slot_id' => $slotId,
                'date'    => $date,
                'date2'   => $date,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException("Slot #{$slotId} not found after capacity insert");
            }

            // ── 3. Validate slot is active ───────────────────────────────────
            if ((int)($row['is_active'] ?? 0) !== 1) {
                $this->safeDecrement($slotId, $date);
                if (!$alreadyInTransaction) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'This slot is no longer accepting orders.',
                ];
            }

            // ── 4. Validate exception closure ────────────────────────────────
            if ((int)($row['is_closed'] ?? 0) === 1) {
                $this->safeDecrement($slotId, $date);
                if (!$alreadyInTransaction) {
                    $this->pdo->rollBack();
                }
                $this->logEvent($slotId, $date, $orderId, 'exception_closed',
                    (int)$row['booked_count'], (int)$row['effective_capacity'],
                    'Slot closed via exception override');
                return [
                    'success' => false,
                    'message' => 'This time slot is closed for the selected date.',
                ];
            }

            $bookedCount       = (int)$row['booked_count'];
            $effectiveCapacity = (int)$row['effective_capacity'];

            // ── 5. Validate capacity ─────────────────────────────────────────
            if ($bookedCount > $effectiveCapacity) {
                $this->safeDecrement($slotId, $date);
                if (!$alreadyInTransaction) {
                    $this->pdo->rollBack();
                }
                $this->logEvent($slotId, $date, $orderId, 'over_capacity',
                    $bookedCount, $effectiveCapacity, 'Reservation rejected: over capacity');
                error_log("[SlotService] OVER_CAPACITY slot={$slotId} date={$date} booked={$bookedCount} cap={$effectiveCapacity}");
                return [
                    'success' => false,
                    'message' => 'Sorry, this time slot just filled up. Please choose another slot.',
                ];
            }

            // ── 6. All checks passed — commit ────────────────────────────────
            $this->logEvent($slotId, $date, $orderId, 'reserved',
                $bookedCount, $effectiveCapacity, null);

            if (!$alreadyInTransaction) {
                $this->pdo->commit();
            }

            return [
                'success'      => true,
                'message'      => 'Slot reserved successfully.',
                'slot_label'   => (string)($row['slot_label']   ?? ''),
                'slot_name'    => (string)($row['slot_name']    ?? ''),
                'booked_count' => $bookedCount,
                'capacity'     => $effectiveCapacity,
            ];

        } catch (Throwable $e) {
            if (!$alreadyInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[SlotService] reserveSlot exception: ' . $e->getMessage());
            throw new \RuntimeException('Slot reservation failed. Please try again.', 0, $e);
        }
    }

    // =========================================================================
    // HOLD → CONFIRMED LIFECYCLE (new architecture)
    // =========================================================================

    /**
     * Creates a TEMPORARY HOLD reservation for an order.
     * Does NOT increment slot_capacities — only writes to slot_reservations.
     * Holds auto-expire after $expiryMinutes.
     *
     * Returns ['success'=>bool, 'message'=>string, 'slot_label'=>string, ...]
     */
    public function holdSlot(int $slotId, string $date, int $orderId, int $expiryMinutes = 60): array
    {
        try {
            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj === false || $dateObj->format('Y-m-d') !== $date) {
                $this->logSlotError('hold_invalid_date', [
                    'slot_id' => $slotId,
                    'booking_date' => $date,
                    'order_id' => $orderId,
                ]);
                return ['success' => false, 'message' => 'Invalid slot date.', 'slot_label' => ''];
            }

            // Validate slot exists and is active
            $slotStmt = $this->pdo->prepare(
                'SELECT s.id, s.slot_label, s.slot_name, s.max_orders,
                        COALESCE(ex.override_capacity, s.max_orders) AS effective_capacity,
                        COALESCE(ex.is_closed, 0) AS is_closed
                 FROM order_slots s
                 LEFT JOIN order_slot_exceptions ex
                   ON ex.slot_id = s.id AND ex.exception_date = :date
                 WHERE s.id = :slot_id AND s.is_active = 1 LIMIT 1'
            );
            $slotStmt->execute(['slot_id' => $slotId, 'date' => $date]);
            $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

            if (!$slot) {
                $this->logSlotError('hold_slot_missing', [
                    'slot_id' => $slotId,
                    'booking_date' => $date,
                    'order_id' => $orderId,
                ]);
                return ['success' => false, 'message' => 'This slot is not available.', 'slot_label' => ''];
            }
            if ((int)($slot['is_closed'] ?? 0) === 1) {
                $this->logSlotError('hold_slot_closed', [
                    'slot_id' => $slotId,
                    'booking_date' => $date,
                    'order_id' => $orderId,
                ]);
                return ['success' => false, 'message' => 'This time slot is closed for the selected date.', 'slot_label' => ''];
            }

            // Optional: check max hold percentage to prevent spam holds
            $effectiveCapacity = (int)($slot['effective_capacity'] ?? (int)$slot['max_orders']);
            $holdCount = $this->countHolds($slotId, $date);
            $confirmedCount = $this->countConfirmed($slotId, $date);

            if ($confirmedCount >= $effectiveCapacity) {
                $this->logEvent($slotId, $date, $orderId, 'hold_rejected_full',
                    $confirmedCount, $effectiveCapacity, 'Slot full at hold time');
                return ['success' => false, 'message' => 'Sorry, this time slot is fully booked.', 'slot_label' => ''];
            }

            if ($orderId <= 0) {
                $this->logEvent($slotId, $date, null, 'hold_validated',
                    $holdCount, $effectiveCapacity, 'Pre-order slot availability check only');

                return [
                    'success'      => true,
                    'message'      => 'Slot available for hold.',
                    'slot_label'   => (string)($slot['slot_label'] ?? ''),
                    'slot_name'    => (string)($slot['slot_name']  ?? ''),
                    'hold_count'   => $holdCount,
                    'confirmed'    => $confirmedCount,
                    'capacity'     => $effectiveCapacity,
                ];
            }

            $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));

            // Insert or update reservation (idempotent per order)
            $this->pdo->prepare(
                'INSERT INTO slot_reservations
                   (order_id, slot_id, booking_date, reservation_status, expires_at)
                 VALUES (:order_id, :slot_id, :booking_date, "hold", :expires_at)
                 ON DUPLICATE KEY UPDATE
                   slot_id = VALUES(slot_id),
                   booking_date = VALUES(booking_date),
                   reservation_status = "hold",
                   expires_at = VALUES(expires_at),
                   updated_at = NOW()'
            )->execute([
                'order_id'     => $orderId,
                'slot_id'      => $slotId,
                'booking_date' => $date,
                'expires_at'   => $expiresAt,
            ]);

            $this->logEvent($slotId, $date, $orderId, 'hold_created',
                $holdCount + 1, $effectiveCapacity,
                "Hold expires at {$expiresAt}. Confirmed: {$confirmedCount}");

            return [
                'success'      => true,
                'message'      => 'Slot hold created successfully.',
                'slot_label'   => (string)($slot['slot_label'] ?? ''),
                'slot_name'    => (string)($slot['slot_name']  ?? ''),
                'expires_at'   => $expiresAt,
                'hold_count'   => $holdCount + 1,
                'confirmed'    => $confirmedCount,
                'capacity'     => $effectiveCapacity,
            ];

        } catch (Throwable $e) {
            $this->logSlotError('hold_exception', [
                'slot_id' => $slotId,
                'booking_date' => $date,
                'order_id' => $orderId,
                'expiry_minutes' => $expiryMinutes,
                'error' => $e->getMessage(),
                'sql_state' => $e instanceof PDOException ? $e->getCode() : null,
            ]);
            throw new \RuntimeException('Slot hold failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Confirms a slot reservation — called by admin after payment approval.
     * Transactionally checks capacity then increments slot_capacities.booked_count.
     *
     * Returns ['success'=>bool, 'message'=>string]
     */
    public function confirmSlotReservation(int $orderId): array
    {
        $alreadyInTransaction = $this->pdo->inTransaction();
        if (!$alreadyInTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            // Lock the reservation row
            $resStmt = $this->pdo->prepare(
                'SELECT sr.id, sr.slot_id, sr.booking_date, sr.reservation_status,
                        s.slot_label, s.slot_name, s.max_orders,
                        COALESCE(ex.override_capacity, s.max_orders) AS effective_capacity
                 FROM slot_reservations sr
                 JOIN order_slots s ON s.id = sr.slot_id
                 LEFT JOIN order_slot_exceptions ex
                   ON ex.slot_id = sr.slot_id AND ex.exception_date = sr.booking_date
                 WHERE sr.order_id = :order_id
                 LIMIT 1 FOR UPDATE'
            );
            $resStmt->execute(['order_id' => $orderId]);
            $res = $resStmt->fetch(PDO::FETCH_ASSOC);

            if (!$res) {
                // No hold record — order had no slot, confirm is a no-op
                if (!$alreadyInTransaction) { $this->pdo->commit(); }
                return ['success' => true, 'message' => 'No slot reservation to confirm.'];
            }

            if ((string)$res['reservation_status'] === 'confirmed') {
                if (!$alreadyInTransaction) { $this->pdo->commit(); }
                return ['success' => true, 'message' => 'Slot already confirmed.'];
            }

            $slotId  = (int)$res['slot_id'];
            $date    = (string)$res['booking_date'];
            $cap     = (int)$res['effective_capacity'];

            // Re-check confirmed count inside transaction
            $confirmed = $this->countConfirmed($slotId, $date, $orderId); // exclude self
            if ($confirmed >= $cap) {
                if (!$alreadyInTransaction) { $this->pdo->rollBack(); }
                $this->logEvent($slotId, $date, $orderId, 'confirm_rejected_full', $confirmed, $cap,
                    'Slot full at admin confirmation time');
                return [
                    'success' => false,
                    'message' => 'Slot is now fully booked by other confirmed orders. Please review manually.',
                    'waitlist' => true,
                ];
            }

            // Confirm reservation
            $this->pdo->prepare(
                'UPDATE slot_reservations
                 SET reservation_status="confirmed", confirmed_at=NOW(), updated_at=NOW()
                 WHERE order_id = :order_id'
            )->execute(['order_id' => $orderId]);

            // Increment confirmed capacity counter
            $this->pdo->prepare(
                'INSERT INTO slot_capacities (slot_id, booking_date, booked_count)
                 VALUES (:slot_id, :date, 1)
                 ON DUPLICATE KEY UPDATE booked_count = booked_count + 1'
            )->execute(['slot_id' => $slotId, 'date' => $date]);

            if (!$alreadyInTransaction) { $this->pdo->commit(); }

            $this->logEvent($slotId, $date, $orderId, 'confirmed',
                $confirmed + 1, $cap, 'Admin confirmed payment');

            return [
                'success'    => true,
                'message'    => 'Slot confirmed successfully.',
                'slot_label' => (string)($res['slot_label'] ?? ''),
            ];

        } catch (Throwable $e) {
            if (!$alreadyInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[SlotService] confirmSlotReservation exception: ' . $e->getMessage());
            throw new \RuntimeException('Could not confirm slot. Please try again.', 0, $e);
        }
    }

    /**
     * Releases/cancels a reservation (hold or confirmed).
     * Only decrements slot_capacities if the reservation was confirmed.
     *
     * @param string $status 'released' | 'cancelled' | 'expired'
     */
    public function releaseReservation(int $orderId, string $status = 'released'): void
    {
        $allowed = ['released', 'cancelled', 'expired'];
        if (!in_array($status, $allowed, true)) { $status = 'released'; }
        try {
            $resStmt = $this->pdo->prepare(
                'SELECT slot_id, booking_date, reservation_status
                 FROM slot_reservations WHERE order_id = :order_id LIMIT 1'
            );
            $resStmt->execute(['order_id' => $orderId]);
            $res = $resStmt->fetch(PDO::FETCH_ASSOC);
            if (!$res) { return; }

            $wasConfirmed = ((string)$res['reservation_status'] === 'confirmed');

            $this->pdo->prepare(
                'UPDATE slot_reservations
                 SET reservation_status=:status, released_at=NOW(), updated_at=NOW()
                 WHERE order_id = :order_id
                   AND reservation_status NOT IN ("released","cancelled","expired")'
            )->execute(['status' => $status, 'order_id' => $orderId]);

            // Only decrement confirmed capacity counter if it was confirmed
            if ($wasConfirmed) {
                $this->safeDecrement((int)$res['slot_id'], (string)$res['booking_date']);
            }

            $this->logEvent((int)$res['slot_id'], (string)$res['booking_date'], $orderId,
                $status, null, null, "Reservation {$status}");

        } catch (Throwable $e) {
            error_log('[SlotService] releaseReservation error: ' . $e->getMessage());
        }
    }

    /**
     * Auto-expires all holds that have passed their expires_at.
     * Returns count of expired reservations.
     */
    public function expireStaleHolds(): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE slot_reservations
                 SET reservation_status="expired", released_at=NOW(), updated_at=NOW()
                 WHERE reservation_status="hold"
                   AND expires_at IS NOT NULL
                   AND expires_at < NOW()'
            );
            $stmt->execute();
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("[SlotService] expired {$count} stale holds");
            }
            return $count;
        } catch (Throwable $e) {
            error_log('[SlotService] expireStaleHolds error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Returns a reservation record by order_id, or null.
     *
     * @return array<string,mixed>|null
     */
    public function getReservationByOrderId(int $orderId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT sr.*, s.slot_label, s.slot_name
                 FROM slot_reservations sr
                 JOIN order_slots s ON s.id = sr.slot_id
                 WHERE sr.order_id = :order_id LIMIT 1'
            );
            $stmt->execute(['order_id' => $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('[SlotService] getReservationByOrderId error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns slot hold/confirm stats for admin dashboard.
     *
     * @return array{confirmed:int, holds:int, available:int, capacity:int}[]
     */
    public function getSlotHoldStats(string $date): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                   s.id,
                   s.slot_label,
                   s.slot_name,
                   COALESCE(ex.override_capacity, s.max_orders) AS capacity,
                   COALESCE(sc.booked_count, 0) AS confirmed,
                   COUNT(CASE WHEN sr.reservation_status="hold" THEN 1 END) AS holds
                 FROM order_slots s
                 LEFT JOIN order_slot_exceptions ex
                   ON ex.slot_id = s.id AND ex.exception_date = :date1
                 LEFT JOIN slot_capacities sc
                   ON sc.slot_id = s.id AND sc.booking_date = :date2
                 LEFT JOIN slot_reservations sr
                   ON sr.slot_id = s.id AND sr.booking_date = :date3
                 WHERE s.is_active = 1
                 GROUP BY s.id, s.slot_label, s.slot_name, capacity, confirmed
                 ORDER BY s.display_order, s.start_time'
            );
            $stmt->execute(['date1' => $date, 'date2' => $date, 'date3' => $date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(function (array $r): array {
                $cap = (int)$r['capacity'];
                $confirmed = (int)$r['confirmed'];
                $holds = (int)$r['holds'];
                return [
                    'id'        => (int)$r['id'],
                    'slot_label'=> (string)$r['slot_label'],
                    'slot_name' => (string)$r['slot_name'],
                    'capacity'  => $cap,
                    'confirmed' => $confirmed,
                    'holds'     => $holds,
                    'available' => max(0, $cap - $confirmed),
                ];
            }, $rows);
        } catch (Throwable $e) {
            error_log('[SlotService] getSlotHoldStats error: ' . $e->getMessage());
            return [];
        }
    }

    // ── Count helpers ─────────────────────────────────────────────────────────

    private function countHolds(int $slotId, string $date): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM slot_reservations
             WHERE slot_id=:slot_id AND booking_date=:date AND reservation_status="hold"'
        );
        $stmt->execute(['slot_id' => $slotId, 'date' => $date]);
        return (int)$stmt->fetchColumn();
    }

    private function countConfirmed(int $slotId, string $date, ?int $excludeOrderId = null): int
    {
        if ($excludeOrderId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM slot_reservations
                 WHERE slot_id=:slot_id AND booking_date=:date
                   AND reservation_status="confirmed" AND order_id != :exclude'
            );
            $stmt->execute(['slot_id' => $slotId, 'date' => $date, 'exclude' => $excludeOrderId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM slot_reservations
                 WHERE slot_id=:slot_id AND booking_date=:date AND reservation_status="confirmed"'
            );
            $stmt->execute(['slot_id' => $slotId, 'date' => $date]);
        }
        return (int)$stmt->fetchColumn();
    }

    /**
     * Releases (decrements) a slot reservation.
     * Called on order cancellation — legacy method, kept for compat.
     * Prefer releaseReservation() for new code.
     */
    public function releaseSlot(int $slotId, string $date, ?int $orderId = null): void
    {
        try {
            if ($orderId !== null) {
                $this->releaseReservation($orderId);
                return;
            }
            $this->safeDecrement($slotId, $date);
            $this->logEvent($slotId, $date, $orderId, 'released', null, null, 'Slot released');
        } catch (Throwable $e) {
            error_log('[SlotService] releaseSlot error: ' . $e->getMessage());
        }
    }

    /**
     * Returns true if the slot system should activate fallback mode.
     */
    public function isFallbackMode(): bool
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT COUNT(*) FROM order_slots WHERE is_active = 1 LIMIT 1'
            );
            if (!$stmt) {
                return true;
            }
            return (int)$stmt->fetchColumn() < self::MIN_ACTIVE_SLOTS;
        } catch (Throwable $e) {
            error_log('[SlotService] isFallbackMode check failed: ' . $e->getMessage());
            return true;  // fail-safe: activate fallback
        }
    }

    // =========================================================================
    // ADMIN: CRUD OPERATIONS
    // =========================================================================

    /** @return array<int, array<string, mixed>> */
    public function listAllSlots(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT s.*,
                        COALESCE(sc_today.booked_count, 0)    AS booked_today,
                        COALESCE(sc_today.booked_count, 0)    AS usage_today
                 FROM order_slots s
                 LEFT JOIN slot_capacities sc_today
                   ON sc_today.slot_id = s.id AND sc_today.booking_date = CURDATE()
                 ORDER BY s.slot_type, s.display_order, s.start_time'
            );
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log('[SlotService] listAllSlots: ' . $e->getMessage());
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    public function createSlot(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_slots
               (slot_type, slot_name, slot_label, start_time, end_time,
                max_orders, prep_buffer_minutes, cutoff_hour, display_order, is_active, is_recommended)
             VALUES
               (:slot_type, :slot_name, :slot_label, :start_time, :end_time,
                :max_orders, :prep_buffer_minutes, :cutoff_hour, :display_order, :is_active, :is_recommended)'
        );
        $stmt->execute([
            'slot_type'            => $this->validateEnum($data['slot_type'] ?? '', ['delivery', 'pickup']),
            'slot_name'            => substr(trim((string)($data['slot_name']  ?? '')), 0, 120),
            'slot_label'           => substr(trim((string)($data['slot_label'] ?? '')), 0, 120),
            'start_time'           => $this->validateTime($data['start_time'] ?? '00:00'),
            'end_time'             => $this->validateTime($data['end_time']   ?? '00:00'),
            'max_orders'           => max(1, min(500, (int)($data['max_orders']           ?? 10))),
            'prep_buffer_minutes'  => max(0, min(1440, (int)($data['prep_buffer_minutes'] ?? 120))),
            'cutoff_hour'          => max(0, min(23, (int)($data['cutoff_hour']           ?? 20))),
            'display_order'        => (int)($data['display_order'] ?? 0),
            'is_active'            => (int)(bool)($data['is_active']    ?? 1),
            'is_recommended'       => (int)(bool)($data['is_recommended'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateSlot(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE order_slots SET
               slot_type           = :slot_type,
               slot_name           = :slot_name,
               slot_label          = :slot_label,
               start_time          = :start_time,
               end_time            = :end_time,
               max_orders          = :max_orders,
               prep_buffer_minutes = :prep_buffer_minutes,
               cutoff_hour         = :cutoff_hour,
               display_order       = :display_order,
               is_active           = :is_active,
               is_recommended      = :is_recommended
             WHERE id = :id'
        );
        $stmt->execute([
            'id'                   => $id,
            'slot_type'            => $this->validateEnum($data['slot_type'] ?? '', ['delivery', 'pickup']),
            'slot_name'            => substr(trim((string)($data['slot_name']  ?? '')), 0, 120),
            'slot_label'           => substr(trim((string)($data['slot_label'] ?? '')), 0, 120),
            'start_time'           => $this->validateTime($data['start_time'] ?? '00:00'),
            'end_time'             => $this->validateTime($data['end_time']   ?? '00:00'),
            'max_orders'           => max(1, min(500, (int)($data['max_orders']           ?? 10))),
            'prep_buffer_minutes'  => max(0, min(1440, (int)($data['prep_buffer_minutes'] ?? 120))),
            'cutoff_hour'          => max(0, min(23, (int)($data['cutoff_hour']           ?? 20))),
            'display_order'        => (int)($data['display_order'] ?? 0),
            'is_active'            => (int)(bool)($data['is_active']    ?? 1),
            'is_recommended'       => (int)(bool)($data['is_recommended'] ?? 0),
        ]);
        return $stmt->rowCount() > 0;
    }

    public function toggleSlotActive(int $id, bool $active): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE order_slots SET is_active = :active WHERE id = :id'
        );
        $stmt->execute(['active' => (int)$active, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Exception / holiday management ────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public function upsertException(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_slot_exceptions
               (slot_id, exception_date, override_capacity, is_closed, note)
             VALUES
               (:slot_id, :exception_date, :override_capacity, :is_closed, :note)
             ON DUPLICATE KEY UPDATE
               override_capacity = VALUES(override_capacity),
               is_closed         = VALUES(is_closed),
               note              = VALUES(note)'
        );
        $stmt->execute([
            'slot_id'           => (int)$data['slot_id'],
            'exception_date'    => $data['exception_date'],
            'override_capacity' => isset($data['override_capacity']) ? (int)$data['override_capacity'] : null,
            'is_closed'         => (int)(bool)($data['is_closed'] ?? 0),
            'note'              => isset($data['note']) ? substr((string)$data['note'], 0, 255) : null,
        ]);
    }

    public function deleteException(int $slotId, string $date): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM order_slot_exceptions WHERE slot_id = :slot_id AND exception_date = :date'
        );
        $stmt->execute(['slot_id' => $slotId, 'date' => $date]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listExceptions(int $slotId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_slot_exceptions
             WHERE slot_id = :slot_id
             ORDER BY exception_date DESC
             LIMIT 90'
        );
        $stmt->execute(['slot_id' => $slotId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Live usage stats ──────────────────────────────────────────────────────

    /**
     * Returns per-date booking counts for admin dashboard range.
     *
     * @return array<string, array<int, int>>  keyed date → [slot_id => booked_count]
     */
    public function getUsageRange(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT booking_date, slot_id, booked_count
             FROM slot_capacities
             WHERE booking_date BETWEEN :from AND :to
             ORDER BY booking_date, slot_id'
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = $row['booking_date'];
            $result[$d][(int)$row['slot_id']] = (int)$row['booked_count'];
        }
        return $result;
    }

    // =========================================================================
    // PRIVATE: QUERY HELPERS
    // =========================================================================

    /**
     * Fetch slots with capacity data for a given mode and date.
     * Single query — no N+1.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchSlotsWithCapacity(string $mode, string $date, bool $isSameDay): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
               s.id,
               s.slot_name,
               s.slot_label,
               s.slot_type,
               s.start_time,
               s.end_time,
               s.max_orders,
               s.prep_buffer_minutes,
               s.cutoff_hour,
               s.is_recommended,
               COALESCE(sc.booked_count, 0)                    AS booked_count,
               COALESCE(ex.override_capacity, s.max_orders)    AS effective_capacity,
               COALESCE(ex.is_closed, 0)                       AS is_exception_closed,
               COUNT(CASE WHEN sr.reservation_status="hold" THEN 1 END) AS hold_count
             FROM order_slots s
             LEFT JOIN slot_capacities sc
               ON sc.slot_id = s.id AND sc.booking_date = :date1
             LEFT JOIN order_slot_exceptions ex
               ON ex.slot_id = s.id AND ex.exception_date = :date2
             LEFT JOIN slot_reservations sr
               ON sr.slot_id = s.id AND sr.booking_date = :date3
             WHERE s.is_active = 1
               AND s.slot_type = :mode
             GROUP BY s.id, s.slot_name, s.slot_label, s.slot_type, s.start_time,
               s.end_time, s.max_orders, s.prep_buffer_minutes, s.cutoff_hour,
               s.is_recommended, sc.booked_count, effective_capacity, is_exception_closed
             ORDER BY s.display_order ASC, s.start_time ASC'
        );
        $stmt->execute([
            'date1' => $date,
            'date2' => $date,
            'date3' => $date,
            'mode'  => $mode,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Builds the public-facing slot item array, applying all filtering rules.
     *
     * Rules:
     *  R1: Slot visible only if slot_start_time - now > prep_buffer_minutes
     *  R2: If same day AND now > cutoff_hour → hide ALL same-day slots
     *  R3: If booked_count >= effective_capacity → slot disabled (not hidden)
     *  R4: If exception override exists → use overridden capacity
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null  null = filter out entirely
     */
    private function buildSlotItem(array $row, \DateTimeImmutable $now, string $date, bool $isSameDay): ?array
    {
        // R2: Same-day hard cutoff
        if ($isSameDay) {
            $cutoffHour = (int)($row['cutoff_hour'] ?? 20);
            if ((int)$now->format('G') >= $cutoffHour) {
                return null;  // hide entirely
            }
        }

        // R1: Prep buffer check
        $startTimeStr = (string)($row['start_time'] ?? '00:00:00');
        try {
            $slotStart = new \DateTimeImmutable($date . 'T' . $startTimeStr);
        } catch (Throwable $e) {
            return null;
        }

        $minutesUntilSlot = (int)round(($slotStart->getTimestamp() - $now->getTimestamp()) / 60);
        $prepBuffer = (int)($row['prep_buffer_minutes'] ?? 120);

        // Filter out slots where we don't have enough prep time
        // Only apply prep buffer check for today/same-day
        if ($isSameDay && $minutesUntilSlot < $prepBuffer) {
            return null;
        }

        // Exception closure
        if ((int)($row['is_exception_closed'] ?? 0) === 1) {
            return null;  // completely closed — hide
        }

        $bookedCount       = (int)($row['booked_count']       ?? 0);   // confirmed only
        $holdCount         = (int)($row['hold_count']         ?? 0);
        $effectiveCapacity = (int)($row['effective_capacity'] ?? (int)($row['max_orders'] ?? 10));
        $remaining         = max(0, $effectiveCapacity - $bookedCount);
        $isFull            = $bookedCount >= $effectiveCapacity;
        $isFastSelling     = !$isFull && $remaining < ceil($effectiveCapacity * self::FAST_SELLING_THRESHOLD);
        // High demand: holds exceed 50% of remaining capacity
        $isHighDemand      = !$isFull && $holdCount > max(1, (int)ceil($remaining * 0.5));

        return [
            'id'             => (int)$row['id'],
            'slot_name'      => (string)($row['slot_name']  ?? ''),
            'slot_label'     => (string)($row['slot_label'] ?? ''),
            'slot_type'      => (string)($row['slot_type']  ?? ''),
            'start_time'     => $startTimeStr,
            'end_time'       => (string)($row['end_time']   ?? ''),
            'capacity'       => $effectiveCapacity,
            'booked'         => $bookedCount,
            'holds'          => $holdCount,
            'remaining'      => $remaining,
            'is_full'        => $isFull,
            'is_fast_selling'=> $isFastSelling,
            'is_high_demand' => $isHighDemand,
            'is_recommended' => (int)($row['is_recommended'] ?? 0) === 1,
            'available'      => !$isFull,
        ];
    }

    // =========================================================================
    // PRIVATE: INTERNALS
    // =========================================================================

    /**
     * Safely decrements booked_count without going below 0.
     */
    private function safeDecrement(int $slotId, string $date): void
    {
        $this->pdo->prepare(
            'UPDATE slot_capacities
             SET booked_count = GREATEST(0, booked_count - 1)
             WHERE slot_id = :slot_id AND booking_date = :date'
        )->execute(['slot_id' => $slotId, 'date' => $date]);
    }

    /**
     * @param array<string, mixed> $extra  (unused, reserved for structured logging)
     */
    private function logEvent(
        ?int    $slotId,
        ?string $date,
        ?int    $orderId,
        string  $eventType,
        ?int    $bookedAfter,
        ?int    $capacityAt,
        ?string $note
    ): void {
        try {
            $this->pdo->prepare(
                'INSERT INTO slot_booking_logs
                   (slot_id, booking_date, order_id, event_type, booked_count_after, capacity_at_event, note)
                 VALUES
                   (:slot_id, :booking_date, :order_id, :event_type, :booked_after, :capacity_at, :note)'
            )->execute([
                'slot_id'      => $slotId,
                'booking_date' => $date,
                'order_id'     => $orderId,
                'event_type'   => $eventType,
                'booked_after' => $bookedAfter,
                'capacity_at'  => $capacityAt,
                'note'         => $note ? substr($note, 0, 499) : null,
            ]);
        } catch (Throwable $e) {
            // Log failure must not break the main flow
            error_log('[SlotService] logEvent failed: ' . $e->getMessage());
        }
    }

    private function logSlotError(string $event, array $context = []): void
    {
        error_log('[SlotService] ' . $event . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param string[] $allowed */
    private function validateEnum(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    private function validateTime(string $value): string
    {
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            return $value;
        }
        return '00:00:00';
    }

    /** @return array{mode: string, items: array<mixed>, reason: string} */
    private function fallbackResponse(string $reason): array
    {
        return [
            'mode'   => 'fallback',
            'items'  => [],
            'reason' => $reason,
        ];
    }
}
