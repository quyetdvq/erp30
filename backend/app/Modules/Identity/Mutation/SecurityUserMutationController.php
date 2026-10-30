<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mutation;

use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exactly five reviewed baseline Shield User mutations, disabled by default. */
final class SecurityUserMutationController extends Controller
{
    public function createUser(): ResponseInterface
    {
        return $this->dispatch(fn (SecurityUserMutationService $s) => $s->create($this->json()), 201);
    }

    public function updateUser(string $id): ResponseInterface
    {
        return $this->dispatch(fn (SecurityUserMutationService $s) => $s->update($id, $this->json()));
    }

    public function setUserState(string $id): ResponseInterface
    {
        return $this->dispatch(fn (SecurityUserMutationService $s) => $s->setState($id, $this->json()));
    }

    public function resetUserPassword(string $id): ResponseInterface
    {
        return $this->dispatch(fn (SecurityUserMutationService $s) => $s->resetPassword($id, $this->json()));
    }

    public function replaceUserPermissions(string $id): ResponseInterface
    {
        return $this->dispatch(fn (SecurityUserMutationService $s) => $s->replacePermissions($id, $this->json()));
    }

    /** @return array<string,mixed> */
    private function json(): array
    {
        $body = trim((string) $this->request->getBody());
        if ($body === '') { return []; }
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new BadRequestException('Malformed JSON request body.');
        }
        return $data;
    }

    /** @param callable(SecurityUserMutationService):mixed $call */
    private function dispatch(callable $call, int $status = 200): ResponseInterface
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        return ApiResponder::success($this->response, $call(new SecurityUserMutationService($actor)), $status);
    }
}
