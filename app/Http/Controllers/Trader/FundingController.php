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
        if (!$wallet || !$wallet->trust_balance) {
            return 0;
        }

        return (float) (string) $wallet->trust_balance;
    }

    public function index()
    {
        $user = auth()->user();

        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $this->extractTrustBalance($wallet);

        $cycles = TraderCycle::where('user_id', $user->id)
            ->with('product')
            ->orderByDesc('created_at')
            ->get();

        $activeStatuses = ['active', 'ready_to_close'];

        $activeContracts = $cycles
            ->whereIn('status', $activeStatuses)
            ->values();

        $historyContracts = $cycles
            ->whereIn('status', ['completed', 'cancelled'])
            ->values();

        $activePrincipal = (float) $activeContracts->sum('amount');

        $activeExpectedProfit = (float) $activeContracts->sum(
            fn (TraderCycle $cycle) =>
                (float) $cycle->amount * ((float) $cycle->profit_percent / 100)
        );

        $completedContracts = $cycles->where('status', 'completed');

        $completedProfit = (float) $completedContracts->sum(
            fn (TraderCycle $cycle) =>
                (float) $cycle->amount * ((float) $cycle->profit_percent / 100)
        );

        return Inertia::render('Funding/Index', [
            'products' => FundingProduct::where('is_active', true)->get(),

            'activeContracts' => $activeContracts,
            'historyContracts' => $historyContracts,

            'summary' => [
                'active_count' => $activeContracts->count(),
                'principal_total' => round($activePrincipal, 2),
                'expected_profit_total' => round($activeExpectedProfit, 2),
                'payout_obligation_total' => round($activePrincipal + $activeExpectedProfit, 2),
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

        if (!$product->is_active) {
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

            $balance = $this->extractTrustBalance($wallet);

            if ($balance < $totalAmount) {
                $errorMessage = 'Недостаточно средств';
                return;
            }

            $activeContracts = TraderCycle::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->whereIn('status', ['active', 'ready_to_close'])
                ->lockForUpdate()
                ->count();

            if ($product->max_per_trader > 0 && ($activeContracts + $quantity) > $product->max_per_trader) {
                $errorMessage = 'Превышен лимит пакетов для трейдера';
                return;
            }

            if ($product->max_total_volume > 0 && ($product->current_volume + $totalAmount) > $product->max_total_volume) {
                $errorMessage = 'Лимит продукта исчерпан';
                return;
            }

            $wallet->update([
                'trust_balance' => $balance - $totalAmount,
                'locked_balance' => (float) $wallet->locked_balance + $totalAmount,
            ]);

            for ($i = 0; $i < $quantity; $i++) {

                TraderCycle::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'amount' => $unitAmount,
                    'packages_quantity' => 1,
                    'profit_percent' => $product->profit_percent,
                    'funded_at' => now(),
                    'return_at' => now()->addHours($product->freeze_days),
                    'status' => 'active',
                    'is_overdue' => false,
                ]);

            }

            $product->increment('current_volume', $totalAmount);
        });

        if ($errorMessage) {
            return back()->withErrors([
                'quantity' => $errorMessage,
            ]);
        }

        return back()->with('success', 'Пакет успешно приобретен');
    }
}
