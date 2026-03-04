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
        $user = auth()->user();
        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $wallet ? $this->extractTrustBalance($wallet) : 0;

        return Inertia::render('Funding/Index', [
            'products' => FundingProduct::where('is_active', true)->get(),
            'cycles' => TraderCycle::where('user_id', $user->id)
                ->with('product')
                ->orderByDesc('created_at')
                ->get(),
            'balance' => $balance,
        ]);
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:funding_products,id',
            'amount' => 'required|numeric|min:10',
        ]);

        $user = auth()->user();
        $product = FundingProduct::findOrFail($request->product_id);
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();
        if (!$product->is_active) {
            return back()->withErrors(['amount' => 'Пакет недоступен для покупки']);
        }

        $currentBalance = $this->extractTrustBalance($wallet);

        if ($currentBalance < $request->amount) {
            return back()->withErrors(['amount' => 'Недостаточно средств на Trust балансе']);
        }

        $errorMessage = null;

        DB::transaction(function () use ($user, $wallet, $product, $request, &$errorMessage) {
            $lockedWallet = Wallet::query()
                ->where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProduct = FundingProduct::query()
                ->where('id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentBalance = $this->extractTrustBalance($lockedWallet);

            if ($currentBalance < $request->amount) {
                $errorMessage = 'Недостаточно средств на Trust балансе';

                return;
            }

            $traderActiveVolume = TraderCycle::where('user_id', $user->id)
                ->where('product_id', $lockedProduct->id)
                ->whereIn('status', ['active', 'ready_to_close'])
                ->lockForUpdate()
                ->sum('amount');

            if ($lockedProduct->max_per_trader > 0 && ($traderActiveVolume + $request->amount) > $lockedProduct->max_per_trader) {
                $errorMessage = 'Превышен лимит на трейдера для этого пакета';

                return;
            }

            if ($lockedProduct->max_total_volume > 0 &&
               ($lockedProduct->current_volume + $request->amount) > $lockedProduct->max_total_volume) {
                $errorMessage = 'Лимит продукта исчерпан';

                return;
            }

            $lockedWallet->update([
                'trust_balance' => $currentBalance - $request->amount,
                'locked_balance' => (float) $lockedWallet->locked_balance + (float) $request->amount,
            ]);

            TraderCycle::create([
                'user_id' => $user->id,
                'product_id' => $lockedProduct->id,
                'amount' => $request->amount,
                'profit_percent' => $lockedProduct->profit_percent,
                'funded_at' => now(),
                'return_at' => now()->addDays($lockedProduct->freeze_days),
                'status' => 'active',
            ]);

            $lockedProduct->increment('current_volume', $request->amount);
        });

        if ($errorMessage) {
            return back()->withErrors(['amount' => $errorMessage]);
        }

        return back()->with('success', 'Пакет успешно приобретен');
    }
}
