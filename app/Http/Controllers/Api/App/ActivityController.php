<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\PhoneCodeEnums;
use App\Enums\PlatformEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Jobs\HandelEngineClosePositionCallabck;
use App\Jobs\HandleEngineLimitOrderCallback;
use App\Jobs\ReceiveClosePosition;
use App\Jobs\SendRefreshOrder;
use App\Models\Mentor;
use App\Models\MentorVotes;
use App\Models\PlatformActiveDividend;
use App\Models\PlatformActiveSupport;
use App\Models\PlatformCountry;
use App\Models\PlatformNews;
use App\Models\PlatformProtocol;
use App\Models\PlatformVersion;
use App\Models\User;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Exception;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Internal\Common\Actions\Banners;
use Internal\Common\Actions\Notices;
use Internal\Common\Services\R2Service;
use Internal\Security\Services\CloudflareCaptcha;
use Internal\Tools\Services\CaptchaService;
use InvalidArgumentException;
use LogicException as GlobalLogicException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;

/** @package App\Http\Controllers\Api\App */
class ActivityController extends ApiController
{

    public function submitSupport(Request $request)
    {
        throw new LogicException(__('Die Veranstaltung ist vorbei'));

        DB::transaction(function () use ($request) {

            if (PlatformActiveSupport::query()->where('uid', $request->user()->id)->exists()) {
                return true;
            }
            $spotWallet = UserWalletSpot::where('uid', $request->user()->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
            if (!$spotWallet) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            if (bcsub($spotWallet->amount, 100, FundsEnums::DecimalPlaces) < 0) {
                throw new LogicException(__('You are currently unable to join this activity'));
            }

            $support = new PlatformActiveSupport();
            $support->uid = $request->user()->id;
            $support->rewards = 1000;
            $support->settlement_time = Carbon::now()->addDays(8)->toDateTimeString();
            $support->save();


            $before = $spotWallet->amount;
            $spotWallet->amount = bcadd($spotWallet->amount, 1000, FundsEnums::DecimalPlaces);
            $spotWallet->save();

            $quoteFlow = new UserWalletSpotFlow();
            $quoteFlow->uid = $request->user()->id;
            $quoteFlow->coin_id = CoinEnums::DefaultUSDTCoinID;
            $quoteFlow->flow_type = SpotWalletFlowEnums::FlowTypeSystemDeposit;
            $quoteFlow->before_amount = $before;
            $quoteFlow->amount = 1000;
            $quoteFlow->after_amount = $spotWallet->amount;
            $quoteFlow->relation_id = 0;
            $quoteFlow->save();


            return true;
        });

        return $this->ok(true);
    }

    /**
     * 获取红利
     * @param Request $request 
     * @return JsonResponse 
     * @throws BindingResolutionException 
     */
    public function getDividend(Request $request) {
        // 规则
        // | 10000 ~ 50,000           | Level 1 | 3,777 |
        // | 50,000 ~ 150,000     | Level 2 | 7,777 |
        // | 150,000 ~ 300,000    | Level 3 | 17,777 |
        // | 300,000 ~ 500,000    | Level 4 | 27,777 |
        // | 500,000 ~ 800,000    | Level 5 | 57,777 |
        // | 800,000 ~ 1,200,000   | Level 6 | 77,777 |
        // | 1,200,000 ~ 1,500,000  | Level 7 | 117,777 |
        // | 1,500,000 ~ 2,000,000  | Level 8 | 157,777 |
        // | 2,000,000 ~ 3,000,000  | Level 9 | 377,777 |
        // | 超过 3,000,000      | Level 10 | 777,777 |

        DB::transaction(function() use($request){
            $wallet = UserWalletSpot::where('uid', $request->user()->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->first();
            if (!$wallet) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            $rewards = 0;
            if ($wallet->amount >= 10000 && $wallet->amount < 50000) {
                $rewards = 3777;
            } elseif ($wallet->amount >= 50000 && $wallet->amount < 150000) {
                $rewards = 7777;
            } elseif ($wallet->amount >= 150000 && $wallet->amount < 300000) {
                $rewards = 17777;
            } elseif ($wallet->amount >= 300000 && $wallet->amount < 500000) {
                $rewards = 27777;
            } elseif ($wallet->amount >= 500000 && $wallet->amount < 800000) {
                $rewards = 57777;
            } elseif ($wallet->amount >= 800000 && $wallet->amount < 1200000) {
                $rewards = 77777;
            } elseif ($wallet->amount >= 1200000 && $wallet->amount < 1500000) {
                $rewards = 117777;
            } elseif ($wallet->amount >= 1500000 && $wallet->amount < 2000000) {
                $rewards = 157777;
            } elseif ($wallet->amount >= 2000000 && $wallet->amount < 3000000) {
                $rewards = 377777;
            } elseif ($wallet->amount >= 3000000) {
                $rewards = 777777;
            }
            if (!$rewards) {
                throw new LogicException(__('You are currently unable to join this activity'));
            }

            if (PlatformActiveDividend::where('uid', $request->user()->id)->lockForUpdate()->first()) {
                throw new LogicException(__('You have already received the dividend'));
            }

            $model = new PlatformActiveDividend();
            $model->uid = $request->user()->id;
            $model->exchange_balance = $wallet->amount;
            $model->exchange_amount = $rewards;
            $model->save();

            $before = $wallet->amount;
            $wallet->amount = bcadd($wallet->amount, $rewards, FundsEnums::DecimalPlaces);
            $wallet->save();


            $Flow = new UserWalletSpotFlow();
            $Flow->uid = $request->user()->id;
            $Flow->coin_id = CoinEnums::DefaultUSDTCoinID;
            $Flow->flow_type = SpotWalletFlowEnums::FlowTypeDividend;
            $Flow->before_amount = $before;
            $Flow->amount = $rewards;
            $Flow->after_amount = $wallet->amount;
            $Flow->relation_id = $model->id;
            $Flow->save();

            return true;
        });

        return $this->ok(true);
    }

    public function hasDividend(Request $request) {
        $exists = PlatformActiveDividend::where('uid', $request->user()->id)->exists();
        return $this->ok([
            'exsits' => $exists
        ]);
    }

    /**
     * 导师列表
     * @param Request $request 
     * @return JsonResponse 
     * @throws InvalidArgumentException 
     * @throws BindingResolutionException 
     */
    public function mentos(Request $request) {
        $mentors = Mentor::where('status', CommonEnums::Yes)->get();
        $data = [
            'mentors' => $mentors,
            'today_vote'=>MentorVotes::query()->where('user_id', $request->user()->id)->where('vote_date', Carbon::now()->toDateString())->exists(),
        ];
        return $this->ok($data);
    }

    /**
     * 导师投票
     * @param Request $request 
     * @return JsonResponse 
     * @throws BindingResolutionException 
     * @throws NotFoundExceptionInterface 
     * @throws ContainerExceptionInterface 
     * @throws LogicException 
     * @throws BadRequestException 
     * @throws InvalidArgumentException 
     * @throws InvalidCastException 
     */
    public function mentoVote(Request $request) {
        $request->validate([
            'id'=>'required|integer'
        ]);

        if (MentorVotes::where('user_id', $request->user()->id)->where('vote_date', Carbon::now()->toDateString())->first()) {
            throw new LogicException(__('You have already voted today'));
        }

        $mentor = Mentor::find($request->get('id'));
        if (!$mentor) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        $mentor->votes += 1;
        $mentor->save();
        
        $log = new MentorVotes();
        $log->user_id = $request->user()->id;
        $log->mentor_id = $request->get('id');
        $log->vote_date = Carbon::now()->toDateString();
        $log->save();

        return $this->ok(true);
    }

}
