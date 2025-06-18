<?php

namespace Internal\User\Actions;

use App\Enums\CommonEnums;
use App\Enums\UserIdentityEnums;
use App\Events\NewIdentitySubmit;
use App\Exceptions\LogicException;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmitIdentity {

    public function __invoke(Request $request)
    {
        return DB::transaction(function() use($request){
            $uid = $request->get('uid');
            $user = null;
            if (!$uid) {
                $user = $request->user();
            } else {
                $user = User::find($uid);
            }
            if ($user->is_verified_identity == CommonEnums::Yes) {
                throw new LogicException(__('You have passed the Identity authentication, please do not submit again'));
            }
            

            $waiting = UserIdentity::where('uid', $user->id)->where('process_status',UserIdentityEnums::ProcessWaiting)->first();
            if ($waiting) {
                throw new LogicException(__('Your request is being processed, please do not submit again'));
            }

            $model = new UserIdentity();
            $model->uid = $user->id;
            $model->country_id = $request->get('country_id');
            $model->first_name = $request->get('first_name');
            $model->last_name = $request->get('last_name');
            $model->document_number = $request->get('document_number');
            $model->face = $request->get('face');
            $model->document_type = $request->get('document_type');
            $model->document_frontend = $request->get('document_frontend');
            $backend = $request->get('document_backend','');
            if ($backend) {
                $model->document_backend = $request->get('document_backend');
            }
            $model->save();

            NewIdentitySubmit::dispatch($model);
            return true;
        });
    }
}
