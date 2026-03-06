<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TraderCycle;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FundingCycleController extends Controller
{
    private function extractTrustBalance(Wallet $wallet): float
    {
        if (! $wallet || ! $wallet->trust_balance) {
            return 0;
        }

        return (float) (string) $wallet->trust_balance;
    }

    private function profit(TraderCycle $cycle): float
    {
        if (! is_null($cycle->profit_amount)) {
            return (float) $cycle->profit_amount;
        }

        return round((float) $cycle->amount * ((float) $cycle->profit_percent / 100), 2);
    }

    private function payout(TraderCycle $cycle): float
    {
        if (! is_null($cycle->payout_amount)) {
            return (float) $cycle->payout_amount;
        }

        return (float) $cycle->amount + $this->profit($cycle);
    }

    public function index(Request $request)
    {
        $status = $request->string('status')->value() ?: 'ready_to_close';
        $allowed = ['active', 'ready_to_close', 'completed', 'cancelled'];

        if (! in_array($status, $allowed, true)) {
            $status = 'ready_to_close';
        }

        $cycles = TraderCycle::query()
            ->with(['product', 'user.teamLeader'])
            ->where('status', $status)
            ->orderBy($status === 'ready_to_close' || $status === 'active' ? 'return_at' : 'updated_at')
            ->when($status === 'completed' || $status === 'cancelled', fn ($q) => $q->orderByDesc('confirmed_at'))
            ->get();

        return Inertia::render('Admin/Funding/Cycles', [
            'cycles' => $cycles,
            'currentStatus' => $status,
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

            $payoutAmount = $this->payout($lockedCycle);
            $availableBalance = $this->extractTrustBalance($wallet);

            $wallet->update([
                'trust_balance' => $availableBalance + $payoutAmount,
                'locked_balance' => max(0, (float) $wallet->locked_balance - (float) $lockedCycle->amount),
            ]);

            $lockedCycle->update([
                'status' => 'completed',
                'confirmed_by_admin_id' => auth()->id(),
                'confirmed_at' => now(),
                'is_overdue' => false,
            ]);

            $lockedCycle->product()->decrement('current_volume', $lockedCycle->amount);
        });

        return back()->with('success', 'Выплата подтверждена');
    }

    public function cancel(Request $request, TraderCycle $cycle)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (! in_array($cycle->status, ['active', 'ready_to_close'], true)) {
            return back()->withErrors(['cycle' => 'Цикл нельзя аннулировать в текущем статусе']);
        }

        DB::transaction(function () use ($request, $cycle) {
            $lockedCycle = TraderCycle::query()->lockForUpdate()->findOrFail($cycle->id);

            if (! in_array($lockedCycle->status, ['active', 'ready_to_close'], true)) {
                return;
            }

            $wallet = Wallet::query()
                ->where('user_id', $lockedCycle->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $availableBalance = $this->extractTrustBalance($wallet);

            $wallet->update([
                'trust_balance' => $availableBalance + (float) $lockedCycle->amount,
                'locked_balance' => max(0, (float) $wallet->locked_balance - (float) $lockedCycle->amount),
            ]);

            $lockedCycle->update([
                'status' => 'cancelled',
                'cancelled_by_admin_id' => auth()->id(),
                'cancellation_reason' => $request->input('reason'),
                'cancelled_at' => now(),
                'is_overdue' => false,
            ]);

            $lockedCycle->product()->decrement('current_volume', $lockedCycle->amount);
        });

        return back()->with('success', 'Договор аннулирован');
    }
}
