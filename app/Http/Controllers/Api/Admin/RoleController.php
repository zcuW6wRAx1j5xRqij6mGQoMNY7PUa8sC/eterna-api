<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Exceptions\InternalException;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminRelation;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends ApiController
{

    public function selector()
    {
        $menus = AdminRole::select('show_name', 'id')->get();
        return $this->ok($menus);
    }

    //列表
    public function show()
    {
        $items = AdminRole::orderBy('created_at', 'asc')
            ->paginate(CommonEnums::Paginate);
        return $this->ok($items);
    }

    //新增
    public function store(Request $request)
    {
        $rules      = [
            'show_name' => 'bail|required|string',
            'desc'      => 'string',
        ];
        $input      = $request->only(...array_keys($rules));
        $validator  = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new LogicException(__($validator->errors()->first()));
        }

        AdminRole::create($input);
        return $this->ok(true);
    }

    //删除
    public function destroy(Request $request, $id)
    {
        $deleted = AdminRole::destroy($id);
        if (!$deleted){
            throw new InternalException(__("drop failure"));
        }
        // 移除已关联的用户属性
        AdminUser::where('role_id', $id)->update(['role_id'=>0]);

        // 移除已关联的菜单属性
        AdminRelation::where('role_id', $id)->delete();

        return $this->ok('success');
    }

    //修改
    public function update(Request $request, $id)
    {
        $rules      = [
            'show_name' => 'required|string',
            'desc'      => 'nullable|string',
            'status'    => 'nullable|numeric',
        ];
        $input      = $request->only(...array_keys($rules));
        $validator  = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new LogicException($validator->errors()->first());
        }

        $role       = AdminRole::find($id);
        if(!$role){
            throw new LogicException('Menu not exists.');
        }

        $data       = [];
        foreach ($input as $field=>$val) {
            if($val && $val != $role->$field){
                $data[$field] = $val;
            }
        }
        $ok = $role->update($data);

        return $this->ok(['status'=>$ok, 'new'=>$role]);
    }

    //明细
    public function detail(Request $request, $id)
    {
        $role       = AdminRole::find($id);
        $menuIds    = AdminRelation::where('role_id', $id)->pluck('menu_id');
        return $this->ok([
            'role'      => $role,
            'menuIds'   => $menuIds,
        ]);
    }

    //关联菜单
    public function assignMenus(Request $request, $id)
    {
        $exists     = AdminRole::find($id);
        if(!$exists){
            throw new LogicException('Role not exists.');
        }
        $collect    = $request->input('menuIds');
        if(!$collect){
            AdminRelation::where('role_id', $id)->delete();
            return $this->ok(true);
        }

        $old        = AdminRelation::Where("role_id", $id)->pluck("menu_id")->toArray();
        if($old){
            $unexpect   = [];
            foreach ($old as $item) {
                if(!in_array($item, $collect)){
                    $unexpect[] = $item;
                }
            }
            AdminRelation::where('role_id', $id)->whereIn("menu_id", $unexpect)->delete();
        }

        $new = [];
        foreach ($collect as $menuId) {
            if(!$old || !in_array($menuId, $old)){
                $new[] = [
                    'role_id' => $id,
                    'menu_id' => $menuId,
                ];
            }
        }

        AdminRelation::insert($new);
        return $this->ok(true);
    }

}
