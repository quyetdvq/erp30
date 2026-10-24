<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/** Five existing /api/v1/auth operations; deliberately no new public routes. */
final class AuthController extends Controller
{
    public function csrf(): ResponseInterface
    {
        $security = service('security');
        return ApiResponder::success($this->response, [
            'csrf_token' => $security->getHash(),
            'header_name' => $security->getHeaderName(),
        ]);
    }

    public function login(): ResponseInterface
    {
        $payload = JsonRequest::readObject($this->request);
        try {
            $user = (new IdentityService())->login($payload);
        } catch (\InvalidArgumentException) {
            return ApiResponder::error($this->response, 'INVALID_CREDENTIALS', 422, [[
                'field' => 'password',
                'code' => 'INVALID_CREDENTIALS',
                'message' => ApiResponder::message('INVALID_CREDENTIALS', 422),
            ]]);
        }
        if ($user === null) {
            return ApiResponder::error($this->response, 'INVALID_CREDENTIALS', 401);
        }
        return ApiResponder::success($this->response, ['user' => (new UserPresenter())->present($user)]);
    }

    public function logout(): ResponseInterface
    {
        (new IdentityService())->logout();
        return ApiResponder::success($this->response, ['ok' => true]);
    }

    public function me(): ResponseInterface
    {
        $user = (new IdentityService())->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        return ApiResponder::success($this->response, ['user' => (new UserPresenter())->present($user)]);
    }

    public function profile(): ResponseInterface
    {
        $user = (new IdentityService())->currentUser();
        if ($user === null) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $payload = JsonRequest::readObject($this->request);
        try {
            (new ProfileService())->update($user, $payload);
        } catch (\InvalidArgumentException) {
            return ApiResponder::error($this->response, 'INVALID_PROFILE', 422);
        } catch (Throwable $exception) {
            log_message('error', 'Identity profile update failed: {exception}', ['exception' => get_class($exception)]);
            return ApiResponder::error($this->response, 'PROFILE_UPDATE_FAILED', 422);
        }
        return ApiResponder::success($this->response, ['user' => (new UserPresenter())->present($user)]);
    }
}
