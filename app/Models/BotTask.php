<?php

namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
    
    protected function startAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value,config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value,config('app.timezone'))->format('Y-m-d H:i:s') : null,
        );
    }
    
    protected function endAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value,config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value,config('app.timezone'))->format('Y-m-d H:i:s') : null,
        );
    }

}
