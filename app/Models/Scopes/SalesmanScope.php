<?php
namespace App\Models\Scopes;

use App\Enums\CommonEnums;
use App\Exceptions\LogicException;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

class SalesmanScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // 从请求中获取参数
        $request    = Request::instance();
        $roleId     = $request->user()->role_id;
        $userId     = $request->user()->id;
        $salesman   = $request->get('salesman',0);


        if($roleId == CommonEnums::salesmanRoleId){
            // 业务员只能查自己的
            $builder->whereHas('user', function($query) use ($userId) {
                $query->where('salesman', $userId);
            });
            return null;
        }

        if($salesman){
            // 只查询单个业务员的数据
            if($roleId==CommonEnums::salesmanLeaderRoleId && AdminUser::where('id', $salesman)->value('parent_id') != $salesman){
                throw new LogicException('您无权查看该业务员信息');
            }

            $builder->whereHas('user', function($query) use ($salesman) {
                $query->where('salesman', $salesman);
            });
            return null;
        }

        if($roleId==CommonEnums::salesmanLeaderRoleId){
            // 组长只能查自己的下属业务员
            $builder->whereHas('user', function($query) use ($userId) {
                $query->whereIn('salesman', function ($query) use($userId) {
                    $query->select('id')
                        ->from('admin_user')
                        ->where('parent_id', $userId);
                });
            });
            return null;
        }
    }
}
