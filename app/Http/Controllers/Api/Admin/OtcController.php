<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Enums\OrderEnums;
use App\Http\Controllers\Api\ApiController;
use App\Internal\Order\Actions\AuditOtcOrder;
use App\Internal\Order\Actions\OtcOrders;
use App\Models\OtcProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class OtcController extends ApiController {
    
    /**
     * 获取OTC产品列表
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function list(Request $request)
    {
        $config = OtcProduct::get();
        
        return $this->ok($config);
    }
    
    /**
     * 新增OTC产品配置
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function create(Request $request)
    {
        $request->validate([
            'title'          => 'required|string',
            'min_limit'      => 'required|numeric',
            'max_limit'      => 'required|numeric',
            'sell_min_limit' => 'required|numeric',
            'sell_max_limit' => 'required|numeric',
            'buy_price'      => 'required|numeric',
            'sell_price'     => 'required|numeric',
        ]);
        
        $title        = $request->get('title');
        $minLimit     = $request->get('min_limit');
        $maxLimit     = $request->get('max_limit');
        $sellMinLimit = $request->get('sell_min_limit');
        $sellMaxLimit = $request->get('sell_max_limit');
        $buyPrice     = $request->get('buy_price');
        $sellPrice    = $request->get('sell_price');
        
        $config                 = new OtcProduct();
        $config->title          = $title;
        $config->duration       = 1;
        $config->coin_id        = CommonEnums::USDCCoinID;
        $config->min_limit      = $minLimit;
        $config->max_limit      = $maxLimit;
        $config->sell_min_limit = $sellMinLimit;
        $config->sell_max_limit = $sellMaxLimit;
        $config->buy_price      = $buyPrice;
        $config->sell_price     = $sellPrice;
        $config->save();
        
        return $this->ok($config);
    }
    
    
    /**
     * 修改OTC产品配置
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function update(Request $request)
    {
        $request->validate([
            'id'         => 'required|numeric',
            'title'      => 'required|string',
            'min_limit'  => 'required|numeric',
            'max_limit'  => 'required|numeric',
            'buy_price'  => 'required|numeric',
            'sell_price' => 'required|numeric',
        ]);
        
        $id        = $request->get('id');
        $title     = $request->get('title');
        $minLimit  = $request->get('min_limit');
        $maxLimit  = $request->get('max_limit');
        $buyPrice  = $request->get('buy_price');
        $sellPrice = $request->get('sell_price');
        
        $config             = OtcProduct::find($id);
        $config->title      = $title;
        $config->min_limit  = $minLimit;
        $config->max_limit  = $maxLimit;
        $config->buy_price  = $buyPrice;
        $config->sell_price = $sellPrice;
        $config->save();
        
        return $this->ok($config);
    }
    
    /**
     * 删除OTC产品配置
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function delete(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
        ]);
        
        $id = $request->get('id');
        OtcProduct::where('id', $id)->delete();
        
        return $this->ok();
    }
    
    /**
     * otc产品下拉框
     *
     * @param Request   $request
     * @param OtcOrders $otcOrders
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function otcProductOption(Request $request, OtcOrders $otcOrders)
    {
        $options = OtcProduct::pluck('title', 'id')->toArray();
        return $this->ok($options);
    }
    
    /**
     * otc订单列表
     *
     * @param Request   $request
     * @param OtcOrders $otcOrders
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function orders(Request $request, OtcOrders $otcOrders)
    {
        $request->validate([
            'page'       => 'integer|nullable|min:1',
            'page_size'  => 'integer|nullable|min:1|max:100',
            'trade_type' => ['nullable', Rule::in(OrderEnums::CommonTradeTypeMap)],
            'status'     => ['nullable', Rule::in(OrderEnums::TradeStatusMap)],
            'uid'        => 'integer|nullable',
            'product_id' => 'integer|nullable',
            'salesman'   => 'nullable|integer',
        ]);
        return $this->ok($otcOrders->auditList($request));
    }
    
    /**
     * otc订单操作
     *
     * @param Request       $request
     * @param AuditOtcOrder $auditOtcOrder
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function audit(Request $request, AuditOtcOrder $auditOtcOrder)
    {
        $request->validate([
            'id'     => 'required|numeric',
            'status' => [Rule::in(OrderEnums::TradeStatusMap)],
        ]);
        
        return $this->ok($auditOtcOrder($request));
    }
    
}
