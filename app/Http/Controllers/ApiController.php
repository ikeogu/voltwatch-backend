<?php

namespace App\Http\Controllers;

use App\Models\AdminUsers;
use App\Models\LogActivity;
use App\Modules\BackOffice\AdminUsers\Enums\AdminActivityEnum;
use App\Traits\LogActivityHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response as FacadesResponse;
use Jenssegers\Agent\Facades\Agent;

class ApiController extends Controller
{
   // use LogActivityHelper;
    /**
     * Set the server error response.
     *
     * @param $message
     * @param Exception|null $exception
     *
     * @return JsonResponse
     */
    public function serverErrorAlert($message, ?Exception $exception = null): JsonResponse
    {
        logger($exception);
        if (null !== $exception) {
            Log::error("{$exception->getMessage()} on line {$exception->getLine()} in {$exception->getFile()}");
        }

        return $this->jsonResponse($message, 500);
    }

    /**
     * Set the server error response.
     *
     * @param $message
     * @param HttpException|null $exception
     * @return JsonResponse
     */
    public function httpErrorAlert($message, HttpException $exception = null): JsonResponse
    {
        if (null !== $exception) {
            Log::error("{$exception->getMessage()} on line {$exception->getLine()} in {$exception->getFile()}");
        }

        return $this->jsonResponse($message, 422);
    }

    /**
     * Set the form validation error response.
     *
     * @param $message
     * @param $data
     *
     * @return JsonResponse
     */
    public function formValidationErrorAlert($message, $data = null): JsonResponse
    {
        return $this->jsonResponse($message, 422, $data);
    }

    /**
     * Set bad request error response.
     *
     * @param $message
     * @param null $data
     *
     * @return JsonResponse
     */
    public function badRequestAlert($message, $data = null): JsonResponse
    {
        return $this->jsonResponse($message, 400, $data);
    }

    /**
     * @param string $message
     * @param array|null $error
     * @return JsonResponse
     */
    public function notFoundResponse(string $message, ?array $error = null): JsonResponse
    {
        return $this->clientError($message, 404, $error);
    }

    /**
     * @param string $message
     * @param int $status
     * @param null $data
     * @return JsonResponse
     */
    public function clientError(string $message, int $status = 400, $data = null): JsonResponse
    {
        return $this->jsonResponse($message, $status, $data);
    }

    /**
     * Set the success response alert.
     *
     * @param $message
     * @param $data
     *
     * @return JsonResponse
     */
    public function successResponse($message, $data = null): JsonResponse
    {
        $status = 200;
       /*  if ((is_object($data) && property_exists($data, 'data')) || (is_array($data) && isset($data['data']))) {
            $data = collect($data)->toArray();
            return response()->json([
                'status' => true,
                'message' => $message,
                ...$data
            ], $status);
        } */



        return response()->json([
            'status' => true,
            'message' => $message,
            ...$data
        ], $status);
    }

    /**
     * Set the created resource response alert.
     *
     * @param $message
     * @param $data
     *
     * @return JsonResponse
     */
    public function createdResponse($message, $data = null): JsonResponse
    {
        return $this->jsonResponse($message, 201, $data);
    }

    /**
     * Set forbidden request error response.
     *
     * @param $message
     * @param $data
     *
     * @return JsonResponse
     */
    public function forbiddenRequestAlert($message, $data = null): JsonResponse
    {
        return $this->jsonResponse($message, 403, $data);
    }
    /**
     * Set Empty Response.
     *
     * @param $message
     * @param $data
     *
     * @return JsonResponse
     */
    public function emptyResponse($message): JsonResponse
    {
        return $this->jsonResponse($message, 204);
    }

    /**
     * Return a generic HTTP response
     *
     * @param string $message
     * @param int $status
     * @param null $data
     *
     * @return JsonResponse
     */
    public function jsonResponse(string $message, int $status, $data = null, $paginated = false): JsonResponse
    {
        $is_successful = $this->isStatusCodeSuccessful($status);

        $response_data = [
            'status' => ($is_successful) ? true : false,
            'message' => $message,
        ];

        if (!is_null($data)) {
            if ($paginated) {
                $response_data =  array_merge($response_data, $data);
            } else {
                $response_data[$is_successful ? 'data' : 'error'] = $data;
            }
        }


        return FacadesResponse::json($response_data, $status);
    }

    /**
     * Determine if a  HTTP status code indicates success
     *
     * @param int $status
     *
     * @return bool
     */
    private function isStatusCodeSuccessful(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }

    /**
     * @param $data
     * @param string|null $message
     * @return array|object
     */
    public function ok(string $message = null)
    {
        return new Fluent([
            "status" => true,
            "message" => $message
        ]);
    }

    /**
     * @param string $message
     * @param array $data
     * @return array|object
     */
    public function bad($message = null, $data = null)
    {

        return new Fluent([
            "status" => false,
            "data" => $data,
            "message" => $message
        ]);
    }


    public function error(mixed $message, ?int $status, ?array $headers = [])
    {
        return response()->json([
            'status' => 'failed',
            'error' => [
                'message' => $message
            ]
        ], $status, $headers);
    }

     public function errorWithData(mixed $message, ?int $status, ?array $data = [])
    {
        return response()->json([
            'status' => 'failed',
            'error' => [
                'message' => $message
            ],
            'data' => $data
        ], $status);
    }

}