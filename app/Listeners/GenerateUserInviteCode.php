<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class GenerateUserInviteCode implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        $inviteCode = '';
        // 最多5次
        for ($i=0;$i<4;$i++) {
            $inviteCode = generateInviteCode();
            $exists = User::where('invite_code', $inviteCode)->first();
            if (!$exists) {
                break;
            }
        }
        if (!$inviteCode) {
            Log::error('生成用户邀请码失败',[
                'uid'=>$event->user->id,
            ]);
            return;
        }

        $user = User::find($event->user->id);
        $user->invite_code = $inviteCode;
        $user->save();
        return;
    }
}
