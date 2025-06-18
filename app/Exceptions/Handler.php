<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Throwable;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Request;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function(LogicException $e){
            // logic 异常, 不处理日志记录
            $this->notifyException($e);
        })->stop();

        $this->renderable(function(InternalException $e, $request){
            if($request->is('api/*')) {
                return $this->fail($e->getMessage());
            }
        });

        // validate 校验错误改造json返回
        $this->renderable(function(ValidationException $e, $request){
            if ($request->is('api/*')) {
                $errorBag = $e->validator->errors()->toArray() ?? [];
                if (!$errorBag) {
                    return $this->fail(__('Server Error'));
                }
                $errors = [];
                foreach($errorBag as $field=>$msg) {
                    $errors[$field] = array_pop($msg);
                }
                return $this->validation($errors);
            }
        });


        $this->renderable(function(LogicException $e, $request){
            if($request->is('api/*')) {
                return $this->fail($e->getMessage(), $e->getCode());
            }
        });

        $this->renderable(function(MethodNotAllowedHttpException $e, $request){
            if($request->is('api/*')) {
                Log::error('api request method err : '.$e->getMessage(),[$e]);
                return $this->notAllowed();
            }
        });

        $this->renderable(function(NotFoundHttpException $e, $request){
            Log::error('api request route err : '.$e->getMessage(),[$e]);
            return $this->notFound();
        });

        $this->renderable(function(PostTooLargeException $e){
            Log::error('api request route err : '.$e->getMessage(),[$e]);
            return $this->bodyTooLarge();
        });

        $this->renderable(function(AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return $this->unauthorized();
            }
        });

        $this->renderable(function(\RuntimeException $e) {
            $this->notifyException($e);
            return $this->fail(__('Server Error,'));
        });

        $this->renderable(function(FatalError $e, $request){
            $this->notifyException($e);
            return $this->fail(__('Server Error,'));
        });
    }

    public function notifyException(Throwable $e)
    {
        $key = env('PUSHER_KEY');
        if(!$key){
            return null;
        }

        $file   = $e->getFile();
        $line   = $e->getLine();
        $trace  = $e->getTrace();
        foreach ($trace as $item) {
            if (isset($item['file']) && strpos($item['file'], 'app/') !== false) {
                $file = $item['file'];
                $line = $item['line'];
                break;
            }
        }

        $request    = Request::instance();
        $token      = $request->header('Authorization');
        $token      = $token?str_replace('Bearer ', '', $token):'';
        $token      = $token?str_replace('|', '', $token):'';
        $detail     = [
            auth()->id() . ' ' . $request->method() . ' ' . $request->path(),
            $line . ' ' . $file,
            "\n" . $e->getMessage() . "\n",
            json_encode($request->all()) . ($token? "\n" . $token : ''),
//            "\n" . $e->getTraceAsString(),
        ];

        pusher(implode("\n", $detail), $key);
    }

}
