<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Five API-010 Contacts endpoints; list/show are never write-gated. */
final class ContactController extends Controller
{
    public function listContacts(): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        $page = (new ContactService($actor))->list($this->request->getGet());
        return ApiResponder::success($this->response, $page['data'], 200,
            ['page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total']]);
    }

    public function getContact(string $id): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response, (new ContactService($actor))->show($id));
    }

    public function createContact(): ResponseInterface
    {
        return $this->write(fn (ContactService $service): array => $service->create(JsonRequest::readObject($this->request)), 201);
    }

    public function updateContact(string $id): ResponseInterface
    {
        return $this->write(fn (ContactService $service): array => $service->update($id, JsonRequest::readObject($this->request)));
    }

    public function setContactState(string $id): ResponseInterface
    {
        return $this->write(fn (ContactService $service): array => $service->setState($id, JsonRequest::readObject($this->request)));
    }

    private function actor(): ?User
    {
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }

    /** @param callable(ContactService):array<string,mixed> $task */
    private function write(callable $task, int $status = 200): ResponseInterface
    {
        $actor = $this->actor();
        if ($actor === null) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE', 'off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return ApiResponder::success($this->response, $task(new ContactService($actor)), $status);
    }
}
