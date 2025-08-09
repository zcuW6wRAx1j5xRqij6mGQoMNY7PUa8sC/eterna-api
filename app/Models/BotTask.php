<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotTask extends Model {
	protected $table = 'bot_task';

	protected $fillable = [
		'symbol_id',
		'symbol_type',
		'close',
		'open',
		'high',
		'low',
		'sigma',
		'status',
		'start_at',
		'end_at',
		'creator',
		'updater',
	];

	public function coin()
	{
		return $this->belongsTo(SymbolCoin::class, 'coin_id');
	}

    public function symbol() {
        return $this->belongsTo(Symbol::class,'symbol_id');
    }
}
