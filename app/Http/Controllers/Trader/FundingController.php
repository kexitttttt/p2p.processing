<?php

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\Controller;
use App\Models\FundingProduct;
use App\Models\TraderCycle;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FundingController extends Controller
{
    private function extractTrustBalance(?Wallet $wallet): float
    {
        if (! $wallet || ! $wallet->trust_balance) {
            return 0;
        }

        return (float) (string) $wallet->trust_balance;
    }

    private function contractProfit(TraderCycle $cycle): float
    {
        if (! is_null($cycle->profit_amount)) {
            return (float) $cycle->profit_amount;
        }

        return round((float) $cycle->amount * ((float) $cycle->profit_percent / 100), 2);
    }

    private function contractPayout(TraderCycle $cycle): float
    {
        if (! is_null($cycle->payout_amount)) {
            return (float) $cycle->payout_amount;
        }

        return round((float) $cycle->amount + $this->contractProfit($cycle), 2);
    }

    public function index()
    {
        $user = auth()->user();

        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $this->extractTrustBalance($wallet);

        $activeContracts = TraderCycle::where('user_id', $user->id)
            ->with('product')
            ->whereIn('status', ['active', 'ready_to_close'])
            ->orderBy('return_at')
            ->get();

        $historyContracts = TraderCycle::where('user_id', $user->id)
            ->with('product')
            ->whereIn('status', ['completed', 'cancelled'])
            ->orderByDesc('confirmed_at')
            ->orderByDesc('updated_at')
            ->get();

        $activePrincipal = (float) $activeContracts->sum('amount');
        $activeExpectedProfit = (float) $activeContracts->sum(fn (TraderCycle $cycle) => $this->contractProfit($cycle));
        $activePayout = (float) $activeContracts->sum(fn (TraderCycle $cycle) => $this->contractPayout($cycle));

        $completedContracts = $historyContracts->where('status', 'completed');
        $completedProfit = (float) $completedContracts->sum(fn (TraderCycle $cycle) => $this->contractProfit($cycle));

        return Inertia::render('Funding/Index', [
            'products' => FundingProduct::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'activeContracts' => $activeContracts,
            'historyContracts' => $historyContracts,

            'summary' => [
                'active_count' => $activeContracts->count(),
                'principal_total' => round($activePrincipal, 2),
                'expected_profit_total' => round($activeExpectedProfit, 2),
                'payout_obligation_total' => round($activePayout, 2),
            ],

            'historySummary' => [
                'completed_count' => $completedContracts->count(),
                'completed_profit_total' => round($completedProfit, 2),
            ],

            'balance' => $balance,
        ]);
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:funding_products,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $user = auth()->user();
        $product = FundingProduct::findOrFail($request->product_id);

        if (! $product->is_active) {
            return back()->withErrors([
                'quantity' => 'Пакет недоступен для покупки',
            ]);
        }

        $quantity = (int) $request->quantity;
        $unitAmount = (float) $product->min_amount;
        $totalAmount = $unitAmount * $quantity;

        $errorMessage = null;

        DB::transaction(function () use ($user, $product, $quantity, $unitAmount, $totalAmount, &$errorMessage) {
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProduct = FundingProduct::query()->lockForUpdate()->findOrFail($product->id);

            $balance = $this->extractTrustBalance($wallet);

            if ($balance < $totalAmount) {
                $errorMessage = 'Недостаточно средств';
                return;
            }

            $activeContracts = TraderCycle::where('user_id', $user->id)
                ->where('product_id', $lockedProduct->id)
                ->whereIn('status', ['active', 'ready_to_close'])
                ->lockForUpdate()
                ->count();

            if ($lockedProduct->max_per_trader > 0 && ($activeContracts + $quantity) > $lockedProduct->max_per_trader) {
                $errorMessage = 'Превышен лимит пакетов для трейдера';
                return;
            }

            if ($lockedProduct->max_total_volume > 0 && ((float) $lockedProduct->current_volume + $totalAmount) > (float) $lockedProduct->max_total_volume) {
                $errorMessage = 'Лимит продукта исчерпан';
                return;
            }

            $wallet->update([
                'trust_balance' => $balance - $totalAmount,
                'locked_balance' => (float) $wallet->locked_balance + $totalAmount,
            ]);

            $profitPerUnit = round($unitAmount * ((float) $lockedProduct->profit_percent / 100), 2);
            $payoutPerUnit = round($unitAmount + $profitPerUnit, 2);

            for ($i = 0; $i < $quantity; $i++) {
                TraderCycle::create([
                    'user_id' => $user->id,
                    'product_id' => $lockedProduct->id,
                    'product_name' => $lockedProduct->name,
                    'amount' => $unitAmount,
                    'packages_quantity' => 1,
                    'profit_percent' => $lockedProduct->profit_percent,
                    'profit_amount' => $profitPerUnit,
                    'payout_amount' => $payoutPerUnit,
                    'funded_at' => now(),
                    'return_at' => now()->addHours((int) $lockedProduct->freeze_days),
                    'status' => 'active',
                    'is_overdue' => false,
                ]);
            }

            $lockedProduct->increment('current_volume', $totalAmount);
        });

        if ($errorMessage) {
            return back()->withErrors([
                'quantity' => $errorMessage,
            ]);
        }

        return back()->with('success', 'Пакет успешно приобретен');
    }
}
