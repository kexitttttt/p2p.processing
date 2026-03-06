<?php

namespace App\Console\Commands;

use App\Models\TraderCycle;
use Illuminate\Console\Command;

class CheckFundingStatus extends Command
{
	protected $signature = 'funding:check-status';
	protected $description = 'Checks funding cycles and moves them to ready_to_close';

	public function handle()
	{
		$cycles = TraderCycle::where('status', 'active')
			->where('return_at', '<=', now())
			->get();

		foreach ($cycles as $cycle) {
			$cycle->update(['status' => 'ready_to_close', 'is_overdue' => false]);
			$cycle->update(['status' => 'ready_to_close']);
			$this->info("Cycle ID {$cycle->id} is ready to close.");
		}
	}
}
