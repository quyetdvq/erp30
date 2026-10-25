<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exactly seven frozen API-050 Price List routes; writes default OFF. */
final class PriceListController extends Controller
{
    private function service():?PriceListService
    {
        $actor=auth()->user();
        return $actor instanceof User ? new PriceListService($actor) : null;
    }

    public function listPriceLists():ResponseInterface
    {
        $service=$this->service();
        if ($service===null) return $this->unauthenticated();
        $raw=$this->request->getGet();
        $page=$service->list(is_array($raw)?$raw:[]);
        return ApiResponder::success($this->response,$page['data'],200,
            ['page'=>$page['page'],'page_size'=>$page['page_size'],'total'=>$page['total']]);
    }

    public function createPriceList():ResponseInterface
    {
        $service=$this->service();
        if ($service===null) return $this->unauthenticated();
        if (!$this->canMutate()) return $this->disabled();
        return ApiResponder::success($this->response,$service->create($this->objectBody()),201);
    }

    public function getPriceList(string $id):ResponseInterface
    {
        $service=$this->service();
        if ($service===null) return $this->unauthenticated();
        return ApiResponder::success($this->response,$service->show($id));
    }

    public function updatePriceList(string $id):ResponseInterface
    {
        $service=$this->service();
        if ($service===null) return $this->unauthenticated();
        if (!$this->canMutate()) return $this->disabled();
        return ApiResponder::success($this->response,$service->update($id,$this->objectBody()));
    }

    public function setPriceListState(string $id):ResponseInterface
    {
        $service=$this->service();
        if ($service===null) return $this->unauthenticated();
        if (!$this->canMutate()) return $this->disabled();
        return ApiResponder::success($this->response,$service->setState($id,$this->objectBody()));
    }

    public function listPriceListItems(string $id):ResponseInterface
    {
        $service=$this->service();
        if ($service===null) return $this->unauthenticated();
        return ApiResponder::success($this->response,$service->listItems($id));
    }

    public function replacePriceListItems(string $id):ResponseInterface
    {
        $service=$this->service();
        if ($service===null) return $this->unauthenticated();
        if (!$this->canMutate()) return $this->disabled();
        return ApiResponder::success($this->response,$service->replaceItems($id,$this->itemsBody()));
    }

    /** @return array<string,mixed> */
    private function objectBody():array
    {
        $raw=ltrim((string)$this->request->getBody());
        if ($raw==='' || $raw[0]!=='{') {
            throw new BadRequestException('Expected a JSON object.');
        }
        $body=JsonRequest::readObject($this->request);
        if ($body!==[] && array_is_list($body)) {
            throw new BadRequestException('Expected a JSON object.');
        }
        return $body;
    }

    /** Distinguish explicit [] from missing/null/{} to prevent accidental DELETE ALL.
     *  @return array<mixed>
     */
    private function itemsBody():array
    {
        $raw=ltrim((string)$this->request->getBody());
        if ($raw==='' || !in_array($raw[0],['{','['],true)) {
            throw new BadRequestException('Explicit JSON array or items object required.');
        }
        $body=JsonRequest::readObject($this->request);
        if ($raw[0]==='{' && !array_key_exists('items',$body)) {
            throw new BadRequestException('Replacement object requires items.');
        }
        if ($raw[0]==='[' && !array_is_list($body)) {
            throw new BadRequestException('Replacement array must be a list.');
        }
        return $body;
    }

    private function canMutate():bool
    {
        return strtolower((string)env('DINKERP_M33B_WRITE_MODE','off'))==='enabled';
    }
    private function unauthenticated():ResponseInterface { return ApiResponder::error($this->response,'UNAUTHENTICATED',401); }
    private function disabled():ResponseInterface { return ApiResponder::error($this->response,'ACCESS_MUTATION_NOT_ENABLED',403); }
}
