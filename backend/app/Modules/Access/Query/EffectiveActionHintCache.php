<?php

declare(strict_types=1);

namespace App\Modules\Access\Query;

use App\Modules\Access\UnifiedAccessService;
use UnexpectedValueException;

/** Request-local only. Only explicitly reviewed record-independent MASTER rules may share decisions by State. */
final class EffectiveActionHintCache
{
    /** @var array<string,array{edit:bool,setstate:bool}> */
    private array $master = [];

    /** @param array<string,mixed> $persisted @return array{edit:bool,setstate:bool} */
    public function forProduct(UnifiedAccessService $access, array $persisted): array
    {
        return $this->forProductWithDecider($persisted,
            static fn (string $action, array $row): bool =>
                $access->decideMutation('product', $action, $row)['allowed']);
    }

    /**
     * Pure, request-scoped grouping unit: the caller supplies the CANONICAL
     * Product mutation decision. Never use this method for ORGANIZATION data.
     * @param array<string,mixed> $persisted
     * @param callable(string,array<string,mixed>):bool $decide
     * @return array{edit:bool,setstate:bool}
     */
    public function forProductWithDecider(array $persisted, callable $decide): array
    {
        $status = $persisted['status'] ?? null;
        if (!is_string($status) || trim($status) === '') {
            throw new UnexpectedValueException('Trusted Product State required for action hint.');
        }
        // Product is a reviewed MASTER domain. Its mutation grant depends on
        // actor, State and GROUP ancestry, not id or organization_id. Do NOT
        // reuse this key for price_list or any multi-company resource.
        $state = strtoupper(trim($status));
        if (!isset($this->master[$state])) {
            $this->master[$state] = [
                'edit'=>$decide('edit', $persisted),
                'setstate'=>$decide('setstate', $persisted),
            ];
        }
        return $this->master[$state];
    }
}
