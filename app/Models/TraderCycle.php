<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TraderCycle extends Model
{
	protected $guarded = [];
	protected $casts = [
		'funded_at' => 'datetime',
		'return_at' => 'datetime',
		'confirmed_at' => 'datetime',
		'cancelled_at' => 'datetime',
		'is_overdue' => 'boolean',
	];

	public function product()
	{
		return $this->belongsTo(FundingProduct::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
