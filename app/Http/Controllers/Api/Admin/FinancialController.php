<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Enums\FinancialEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\Financial;
use App\Models\Scopes\SalesmanScope;
use App\Models\User;
use App\Models\UserOrderFinancial;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;


/** @package App\Http\Controllers\Api\Admin */
class FinancialController extends ApiController {

    /**
     * 产品列表
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function products(Request $request) {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
        ]);
        $query = Financial::query()->orderBy('sort');
        return $this->ok(listResp($query->paginate($request->get('page_size', 15))));
    }

    /**
     * 新增
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     * @throws BindingResolutionException
     */
    public function create(Request $request) {
        $request->validate([
            'category' => ['required', Rule::in(FinancialEnums::CategoryAll)],
            'logo' => 'required|string',
            'name' => 'required|string',
            'duration' => 'required|array',
            'min_daily_rate' => 'required|numeric',
            'max_daily_rate' => 'required|numeric',
            'min_amount' => 'required|numeric',
            'max_amount' => 'required|numeric',
            'penalty_rate' => 'required|numeric',
            'sort' => 'required|numeric',
        ]);

        $category = $request->get('category');
        $logo = $request->get('logo');
        $name = $request->get('name');
        $duration = $request->get('duration');
        $min_daily_rate = $request->get('min_daily_rate');
        $max_daily_rate = $request->get('max_daily_rate');
        $min_amount = $request->get('min_amount');
        $max_amount = $request->get('max_amount');
        $penalty_rate = $request->get('penalty_rate');
        $sort = $request->get('sort');

        Financial::where('name', $name)->first() && throw new LogicException('产品名称已存在');
        $financial = new Financial();
        $financial->category = $category;
        $financial->logo = $logo;
        $financial->name = $name;
        $financial->duration = $duration;
        $financial->min_daily_rate = $min_daily_rate;
        $financial->max_daily_rate = $max_daily_rate;
        $financial->min_amount = $min_amount;
        $financial->max_amount = $max_amount;
        $financial->penalty_rate = $penalty_rate;
        $financial->sort = $sort;
        $financial->status = CommonEnums::Yes;
        $financial->save();
        return $this->ok(true);
    }

    /**
     * 新增
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     * @throws BindingResolutionException
     */
    public function edit(Request $request) {
        $request->validate([
            'id'=>'required|numeric',
            'category' => ['required', Rule::in(FinancialEnums::CategoryAll)],
            'logo' => 'required|string',
            'name' => 'required|string',
            'duration' => 'required|array',
            'min_daily_rate' => 'required|numeric',
            'max_daily_rate' => 'required|numeric',
            'min_amount' => 'required|numeric',
            'max_amount' => 'required|numeric',
            'penalty_rate' => 'required|numeric',
            'sort' => 'required|numeric',
            'status'=> ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
        ]);

        $id = $request->get('id');
        $category = $request->get('category');
        $logo = $request->get('logo');
        $name = $request->get('name');
        $duration = $request->get('duration');
        $min_daily_rate = $request->get('min_daily_rate');
        $max_daily_rate = $request->get('max_daily_rate');
        $min_amount = $request->get('min_amount');
        $max_amount = $request->get('max_amount');
        $penalty_rate = $request->get('penalty_rate');
        $sort = $request->get('sort');
        $status = $request->get('status');

        $financial = Financial::find($id);
        if (!$financial) {
            throw new LogicException('产品不存在');
        }
        Financial::where('name', $name)->where('id', '<>', $id)->first() && throw new LogicException('产品名称已存在');
        $financial->category = $category;
        $financial->logo = $logo;
        $financial->name = $name;
        $financial->duration = $duration;
        $financial->min_daily_rate = $min_daily_rate;
        $financial->max_daily_rate = $max_daily_rate;
        $financial->min_amount = $min_amount;
        $financial->max_amount = $max_amount;
        $financial->penalty_rate = $penalty_rate;
        $financial->sort = $sort;
        $financial->status = $status;
        $financial->save();
        return $this->ok(true);
    }

    /**
     * 订单列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function orders(Request $request) {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'status' => ['nullable', Rule::in(FinancialEnums::StatusAll)],
            'uid'=>'nullable|numeric',
            'email'=>'nullable|string',
            'phone'=>'nullable|string',
            'salesman'=>'nullable|integer',
        ]);

        $query = UserOrderFinancial::with(['financial', 'user'])->orderByDesc('created_at');
        $status = $request->get('status');
        if ($status !== null) {
            $query->where('status', $status);
        }
        $uid = $request->get('uid');
        if ($uid) {
            $query->where('uid', $uid);
        }
        $email = $request->get('email');
        if ($email !== null) {
            $queryUser = User::select(['id'])->where('email', $email)->get();
            if ($queryUser->isEmpty()) {
                return $this->ok([]);
            }
            $query->whereIn('uid', $queryUser->pluck('id')->toArray());
        }
        $phone = $request->get('phone');
        if ($phone !== null) {
            $queryUser = User::select(['id'])->where('phone', $phone)->get();
            if ($queryUser->isEmpty()) {
                return $this->ok([]);
            }
            $query->whereIn('uid', $queryUser->pluck('id')->toArray());
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        return $this->ok(listResp($query->paginate($request->get('page_size', 15))));
    }
}

