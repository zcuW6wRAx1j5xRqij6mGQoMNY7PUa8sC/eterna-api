<?php

namespace Internal\User\Actions;

use App\Enums\CommonEnums;
use App\Enums\UserIdentityEnums;
use App\Events\AuditIdentity as EventsAuditIdentity;
use App\Exceptions\LogicException;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditIdentity {

    public function __invoke(Request $request)
    {
        return DB::transaction(function() use($request){
            $id = $request->get('id');
            $audit = $request->get('audit');
            $reason = $request->get('reason','');

            $model = UserIdentity::find($id);
            if (!$model || $model->process_status != UserIdentityEnums::ProcessWaiting) {
                throw new LogicException('数据状态不正确');
            }

            $model->process_status = $audit;
            if ($reason) {
                $model->reason = $reason;
            }
            $model->save();

            if ($audit === UserIdentityEnums::ProcessPassed) {
                $user = User::find($model->uid);
                $user->is_verified_identity = CommonEnums::Yes;
                $user->save();
            }
            EventsAuditIdentity::dispatch($model);
            return true;
        });
    }
}

