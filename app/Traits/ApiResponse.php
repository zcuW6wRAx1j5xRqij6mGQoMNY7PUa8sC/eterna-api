<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

trait ApiResponse {

    private function response($data, int $code = Response::HTTP_OK) {
        return response()->json($data,$code);
    }

    /**
     * 原始json返回
     * @param array $data
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function rawJson($data = []) {
        return $this->response($data);
    }

    /**
     * 成功返回
     * @param array $data
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function ok($data = [], $simplePage=false) {
        if ( $data  instanceof ResourceCollection ) {
            if ($data instanceof AnonymousResourceCollection) {

            } else {
                $data = [
                    'pages'=>$data->lastPage() ?? 0,
                    'items'=>$data->items() ?? [],
                    'total'=>$data->total() ?? 0,
                ];
            }
        }

        if($simplePage && is_a($data, 'Illuminate\Pagination\LengthAwarePaginator')){
            $data = formatPaginate($data);
        }
        return $this->response([
            'code'=>0,
            'message'=>'success',
            'data'=>$data
        ]);
    }


    /**
     * 失败返回
     * @param string $msg
     * @param int $code
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function fail(string $msg, int $code = 1, int $httpCode = Response::HTTP_OK) {
        if (!$code) {
            $code = 1;
        }
        return $this->response([
            'code'=>$code,
            'message'=>$msg,
            'data'=>[],
        ],$httpCode);
    }

    /**
     * 500 类型异常错误
     * @param string $msg
     * @param int $code
     * @param int $httpCode
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function error(string $msg, int $code = 500, int $httpCode = Response::HTTP_INTERNAL_SERVER_ERROR) {
        return $this->response([
            'code'=>$code,
            'message'=>$msg,
            'data'=>[],
        ],$httpCode);
    }

    /**
     * validation 校验失败返回
     * @param array $erros
     * @param int $code
     * @param int $httpCode
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function validation(array $erros, int $code = Response::HTTP_UNPROCESSABLE_ENTITY, int $httpCode =  Response::HTTP_UNPROCESSABLE_ENTITY) {
        return $this->response([
            'code'=>$code,
            'message'=>'',
            'data'=>[
                'errors'=>$erros,
            ],
        ],$httpCode);
    }

    /**
     * 未认证返回
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function unauthorized() {
        return $this->response([
            'code'=>Response::HTTP_UNAUTHORIZED,
            'message'=>'Unauthorized',
            'data'=>[]
        ],Response::HTTP_UNAUTHORIZED);
    }

    /**
     * 未找到
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function notFound() {
        return $this->response([
            'code'=>Response::HTTP_NOT_FOUND,
            'message'=>'Not Found',
            'data'=>[],
        ],Response::HTTP_NOT_FOUND);
    }

    public function bodyTooLarge() {
        return $this->response([
            'code'=>Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            'message'=>'Body too large',
            'data'=>[],
        ],Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    /**
     * 方法不允许
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function notAllowed() {
        return $this->response([
            'code' => Response::HTTP_METHOD_NOT_ALLOWED,
            'message' => 'Method Not Allowed',
            'data' => [],
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
