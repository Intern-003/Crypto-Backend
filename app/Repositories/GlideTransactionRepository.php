<?php

namespace App\Repositories;

use App\Models\GlideTransaction;

class GlideTransactionRepository
{
    public function store(array $data)
    {
        return GlideTransaction::create($data);
    }

    public function findBySessionId($sessionId)
    {
        return GlideTransaction::where('session_id', $sessionId)->first();
    }

    public function all($filters = [])
    {
        return GlideTransaction::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('payment_status', $status))
            ->when($filters['wallet'] ?? null, fn ($q, $wallet) => $q->where('payer_wallet_address', $wallet))
            ->latest()
            ->paginate(20);
    }
}
