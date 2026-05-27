<?php
declare(strict_types=1);

namespace App\Services;

final class AccountingPostingService
{
    /**
     * @param array<string,mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function postOrderPayment(array $context): array
    {
        $engine = new FinancialTransactionEngine();
        $previousPaymentStatus = strtolower(trim((string)($context['previous_payment_status'] ?? '')));

        if ($previousPaymentStatus === 'credit') {
            return $engine->recordBalanceSettled($context);
        }

        return $engine->recordPaymentReceived($context);
    }
}
