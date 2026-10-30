<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * SUP-T07 Core HTTP adapter (source candidate).
 * Domain validation, canonical PBAC, GROUP scope, History and row_version CAS
 * remain exclusively in SupplierService. No Bank/History/Contact shortcut.
 */
final class SupplierController extends Controller
{
    /** @param callable(SupplierService):ResponseInterface $action */
    private function run(callable $action): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) {
            return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        }
        $this->response->setHeader('Cache-Control', 'no-store');
        return $action(new SupplierService($actor));
    }

    public function index(): ResponseInterface
    {
        return $this->run(function (SupplierService $supplier): ResponseInterface {
            // Flat filters and page default/cap are validated by SupplierSql.
            $page = $supplier->list($this->request->getGet());
            return ApiResponder::success($this->response, $page['data'], 200,
                ['page' => $page['page'], 'page_size' => $page['page_size'], 'total' => $page['total']]);
        });
    }

    public function show(string $id): ResponseInterface
    {
        return $this->run(fn (SupplierService $supplier): ResponseInterface =>
            ApiResponder::success($this->response, $supplier->show($id)));
    }

    public function create(): ResponseInterface
    {
        return $this->run(fn (SupplierService $supplier): ResponseInterface =>
            ApiResponder::success($this->response, $supplier->create(JsonRequest::readObject($this->request)), 201));
    }

    public function update(string $id): ResponseInterface
    {
        return $this->run(fn (SupplierService $supplier): ResponseInterface =>
            ApiResponder::success($this->response, $supplier->update($id, JsonRequest::readObject($this->request))));
    }

    public function companyUsages(string $id): ResponseInterface
    {
        return $this->run(fn (SupplierService $supplier): ResponseInterface =>
            ApiResponder::success($this->response, $supplier->companyUsages($id)));
    }

    public function replaceCompanyUsages(string $id): ResponseInterface
    {
        return $this->run(fn (SupplierService $supplier): ResponseInterface =>
            ApiResponder::success($this->response,
                $supplier->replaceCompanyUsage($id, JsonRequest::readObject($this->request))));
    }

    public function setState(string $id): ResponseInterface
    {
        // SupplierSetState owns the authoritative CAS/Workflow transaction.
        // Generic /workflow/execute is NOT used and must not be a shortcut.
        return $this->run(fn (SupplierService $supplier): ResponseInterface =>
            ApiResponder::success($this->response,
                $supplier->setState($id, JsonRequest::readObject($this->request))));
    }
}
