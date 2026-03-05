<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundingProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundingProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Funding/Products', [
            'products' => FundingProduct::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'freeze_days' => 'required|integer|min:1|max:3650',
            'profit_percent' => 'required|numeric|min:0|max:1000',
            'max_total_volume' => 'required|numeric|min:0',
            'min_amount' => 'required|numeric|min:1',
            'max_per_trader' => 'required|integer|min:0|max:1000',
            'is_active' => 'required|boolean',
        ]);

        FundingProduct::create($data + ['current_volume' => 0]);

        return back()->with('success', 'Позиция создана');
    }

    public function update(Request $request, FundingProduct $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'freeze_days' => 'required|integer|min:1|max:3650',
            'profit_percent' => 'required|numeric|min:0|max:1000',
            'max_total_volume' => 'required|numeric|min:0',
            'min_amount' => 'required|numeric|min:1',
            'max_per_trader' => 'required|integer|min:0|max:1000',
            'is_active' => 'required|boolean',
        ]);

        $product->update($data);

        return back()->with('success', 'Позиция обновлена');
    }
}
