<?php

namespace App\Http\Resources;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Models\PlatformActiveSupport;
use App\Models\UserInbox;
use App\Models\UserOrderPledge;
use App\Models\UserPunchLog;
use App\Models\UserWalletFutures;
use App\Models\UserWalletSpot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allowSupport = 0;
        $exists       = PlatformActiveSupport::where('uid', $this->id)->first();
        if (!$exists) {
            $spot = UserWalletSpot::where('uid', $this->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->first();

            if ($spot && bcsub($spot->amount, 100, FundsEnums::DecimalPlaces) >= 0) {
                $allowSupport = 1;
            } else {
                $futures = UserWalletFutures::where('uid', $this->id)->first();
                if ($futures && bcsub($futures->balance, 100, FundsEnums::DecimalPlaces) >= 0) {
                    $allowSupport = 1;
                }
            }
        }

        $onPledge = UserOrderPledge::query()
            ->where('uid', $this->id)
            ->whereNotIn('status', ['rejected', 'closed'])
            ->exists();

        return [
            'id'                   => $this->id,
            'avatar'               => $this->avatar,
            'name'                 => $this->name,
            'phone_code'           => $this->phone_code,
            'phone'                => $this->phone ? maskPhoneNumber($this->phone) : '',
            'email'                => $this->email ? maskEmailAddr($this->email) : '',
            'invite_code'          => $this->invite_code,
            'is_verified_identity' => $this->is_verified_identity,
            'lang'                 => $this->lang,
            'level'                => $this->level,
            'has_trade_password'   => $this->trade_password ? true : false,
            'latest_login_time'    => $this->latest_login_time,
            'latest_login_ip'      => $this->latest_login_ip,
            'today_punch'          => UserPunchLog::query()->where('uid', $this->id)->where('punch_date', Carbon::now()->toDateString())->exists(),
            'punch_rewards'        => $this->punch_rewards,
            'allow_support'        => $allowSupport,
            'unread_message'       => UserInbox::where('uid', $this->id)->where('is_read', CommonEnums::No)->count(),
            'created_at'           => $this->created_at,
            'on_pledge'            => $onPledge,
        ];
    }
}
