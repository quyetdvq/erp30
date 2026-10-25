<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Exceptions\DomainException;
use App\Modules\Import\Core\ImportJobService;
use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Four frozen API-060 routes. Template downloads are versioned static assets. */
final class ProductImportController extends Controller
{
    public function startProductImport():ResponseInterface
    {
        $actor=$this->actor();
        if ($actor===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        if (strtolower((string)env('DINKERP_M33B_WRITE_MODE','off'))!=='enabled') {
            return ApiResponder::error($this->response,'ACCESS_MUTATION_NOT_ENABLED',403);
        }
        $file=$this->request->getFile('file');
        if ($file===null || !$file->isValid()) {
            throw new DomainException('IMPORT_FILE_REQUIRED');
        }
        $mode=$this->request->getPost('mode');
        if (!is_string($mode)) throw new DomainException('IMPORT_INVALID_MODE');
        $service=$this->service($actor);
        return ApiResponder::success($this->response,
            $service->start($this->adapter($actor),
                $file->getTempName(),$file->getClientName(),strtoupper(trim($mode))),201);
    }

    public function listImportJobs():ResponseInterface
    {
        $actor=$this->actor();
        if ($actor===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        $result=$this->service($actor)->listJobs($this->query(),$this->adapter($actor));
        return ApiResponder::success($this->response,$result['data'],200,
            ['page'=>$result['page'],'page_size'=>$result['page_size'],'total'=>$result['total']]);
    }

    public function getImportJob(string $id):ResponseInterface
    {
        $actor=$this->actor();
        if ($actor===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        return ApiResponder::success($this->response,$this->service($actor)->findVisible($id,$this->adapter($actor)));
    }

    public function listImportJobRows(string $id):ResponseInterface
    {
        $actor=$this->actor();
        if ($actor===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        $result=$this->service($actor)->rows($id,$this->query(),$this->adapter($actor));
        return ApiResponder::success($this->response,$result['data'],200,
            ['page'=>$result['page'],'page_size'=>$result['page_size'],'total'=>$result['total']]);
    }

    private function actor():?User
    {
        $actor=auth()->user();
        return $actor instanceof User?$actor:null;
    }

    private function service(User $actor):ImportJobService
    { return new ImportJobService($actor,db_connect()); }

    private function adapter(User $actor):ProductImportAdapter
    { return new ProductImportAdapter($actor,db_connect()); }

    /** @return array<string,mixed> */
    private function query():array
    {
        $params=$this->request->getGet();
        return is_array($params)?$params:[];
    }
}
