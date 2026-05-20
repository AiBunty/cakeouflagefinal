<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class ByocQuoteExpiryService
{
    /** @return array{expired_quotes:int,closed_inquiries:int,deactivated_links:int,error?:string} */
    public function expireDueQuotes(PDO $pdo): array
    {
        try {
            $pdo->beginTransaction();

            $dueStmt = $pdo->query(
                'SELECT q.id, q.inquiry_id
                 FROM byoc_quotes q
                 INNER JOIN inquiries i ON i.id = q.inquiry_id
                 WHERE q.status = "sent"
                   AND q.accepted_at IS NULL
                   AND q.order_id IS NULL
                   AND q.expires_at IS NOT NULL
                   AND q.expires_at <= NOW()
                   AND i.inquiry_type = "custom_cake"
                 FOR UPDATE'
            );
            $dueRows = $dueStmt instanceof \PDOStatement ? $dueStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            if (!$dueRows) {
                $pdo->commit();
                return [
                    'expired_quotes' => 0,
                    'closed_inquiries' => 0,
                    'deactivated_links' => 0,
                ];
            }

            $quoteIds = array_values(array_unique(array_map(static function (array $row): int {
                return (int)($row['id'] ?? 0);
            }, $dueRows)));
            $inquiryIds = array_values(array_unique(array_map(static function (array $row): int {
                return (int)($row['inquiry_id'] ?? 0);
            }, $dueRows)));

            $quotePlaceholders = implode(',', array_fill(0, count($quoteIds), '?'));
            $inquiryPlaceholders = implode(',', array_fill(0, count($inquiryIds), '?'));

            $linkStmt = $pdo->prepare(
                'UPDATE byoc_quote_links
                 SET is_active = 0
                 WHERE byoc_quote_id IN (' . $quotePlaceholders . ')
                   AND used_at IS NULL'
            );
            $linkStmt->execute($quoteIds);
            $deactivatedLinks = $linkStmt->rowCount();

            $quoteStmt = $pdo->prepare(
                'UPDATE byoc_quotes
                 SET status = "expired", updated_at = NOW()
                 WHERE id IN (' . $quotePlaceholders . ')
                   AND status = "sent"
                   AND accepted_at IS NULL
                   AND order_id IS NULL'
            );
            $quoteStmt->execute($quoteIds);
            $expiredQuotes = $quoteStmt->rowCount();

            $closeStmt = $pdo->prepare(
                'UPDATE inquiries i
                 SET i.status = "closed", i.updated_at = NOW()
                 WHERE i.id IN (' . $inquiryPlaceholders . ')
                   AND i.inquiry_type = "custom_cake"
                   AND i.status <> "closed"
                   AND NOT EXISTS (
                     SELECT 1
                     FROM byoc_quotes q2
                     WHERE q2.inquiry_id = i.id
                       AND q2.status IN ("sent", "accepted")
                   )'
            );
            $closeStmt->execute($inquiryIds);
            $closedInquiries = $closeStmt->rowCount();

            $pdo->commit();

            return [
                'expired_quotes' => $expiredQuotes,
                'closed_inquiries' => $closedInquiries,
                'deactivated_links' => $deactivatedLinks,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'expired_quotes' => 0,
                'closed_inquiries' => 0,
                'deactivated_links' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
