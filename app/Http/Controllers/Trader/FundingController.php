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
    private const MAX_ACTIVE_CYCLES = 3;
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

        $activeCyclesCount = TraderCycle::where('user_id', $user->id)
            ->whereIn('status', ['active', 'ready_to_close'])
            ->count();

        if ($activeCyclesCount >= self::MAX_ACTIVE_CYCLES) {
            return back()->withErrors(['amount' => 'Достигнут лимит активных циклов (3 одновременно)']);
        }

        $traderActiveVolume = TraderCycle::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->whereIn('status', ['active', 'ready_to_close'])
            ->sum('amount');

        if ($product->max_per_trader > 0 && ($traderActiveVolume + $request->amount) > $product->max_per_trader) {
            return back()->withErrors(['amount' => 'Превышен лимит на трейдера для этого пакета']);
        }

        if ($product->max_total_volume > 0 &&
           ($product->current_volume + $request->amount) > $product->max_total_volume) {
            return back()->withErrors(['amount' => 'Лимит продукта исчерпан']);
        }

        DB::transaction(function () use ($user, $wallet, $product, $request, $currentBalance) {
            $newBalance = $currentBalance - $request->amount;
            $wallet->update(['trust_balance' => $newBalance]);
            $wallet->increment('locked_balance', $request->amount);

            TraderCycle::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'amount' => $request->amount,
                'profit_percent' => $product->profit_percent,
                'funded_at' => now(),
                'return_at' => now()->addDays($product->freeze_days),
                'status' => 'active',
            ]);

            $product->increment('current_volume', $request->amount);
        });

        return back()->with('success', 'Пакет успешно приобретен');
    }
}
