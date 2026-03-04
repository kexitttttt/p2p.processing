<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TraderCycle;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FundingCycleController extends Controller
{
    private function extractTrustBalance(Wallet $wallet): float
    {
        if (!$wallet->trust_balance) {
            return 0;
        }

        $moneyArray = $wallet->trust_balance->toArray();

        return (float) ($moneyArray['amount'] ?? $moneyArray['value'] ?? 0);
    }

    public function index()
    {
        $cycles = TraderCycle::query()
            ->with(['product', 'user.teamLeader'])
            ->where('status', 'ready_to_close')
            ->orderBy('return_at')
            ->get();

        return Inertia::render('Admin/Funding/Cycles', [
            'cycles' => $cycles,
        ]);
    }

    public function confirm(TraderCycle $cycle)
    {
        if ($cycle->status !== 'ready_to_close') {
            return back()->withErrors(['cycle' => 'Цикл не готов к подтверждению']);
        }

        DB::transaction(function () use ($cycle) {
            $lockedCycle = TraderCycle::query()->lockForUpdate()->findOrFail($cycle->id);

            if ($lockedCycle->status !== 'ready_to_close') {
                return;
            }

            $wallet = Wallet::query()
                ->where('user_id', $lockedCycle->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $profit = round((float) $lockedCycle->amount * ((float) $lockedCycle->profit_percent / 100), 2);
            $payoutAmount = (float) $lockedCycle->amount + $profit;
            $availableBalance = $this->extractTrustBalance($wallet);

            $wallet->update([
                'trust_balance' => $availableBalance + $payoutAmount,
                'locked_balance' => max(0, (float) $wallet->locked_balance - (float) $lockedCycle->amount),
            ]);

            $lockedCycle->update([
                'status' => 'completed',
                'confirmed_by_admin_id' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            $lockedCycle->product()->decrement('current_volume', $lockedCycle->amount);
        });

        return back()->with('success', 'Выплата подтверждена');
    }
}
