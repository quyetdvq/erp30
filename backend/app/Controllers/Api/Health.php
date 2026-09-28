<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

final class Health extends BaseController
{
    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'service' => 'mini-erp-backend',
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    public function get_user_allowed_permissions(): array //Profile Permission = Role Permission + User Override
    {
        //resource_type => [action];

        $profile = [
            'product' => ['view', 'create', 'edit', 'delete'],
            'order' => ['view', 'create', 'edit', 'delete'],
            'organization' => ['view', 'create', 'edit', 'delete'],
        ];

        return $profile;
    }


    public function get_user_organization_scope(): array //After Employee Assignment (Inheritance is included).
    {
        $organization_id = [115, 23];

        return $organization_id;
    }


    public function get_user_denied_context(): array //After Policy Evaluation, Effective Policy Restriction Result
    {
        //resource_type => [organization => [state => [action]]];

        $user_denied = [
            'product' => [
                '115' => [
                    'DRAFT' => ['view', 'delete'],
                    'RELEASED' => ['delete', 'edit'],
                    'APPROVED' => ['delete', 'edit'],
                    'REJECTED' => ['delete', 'edit'],
                ],

            ],
        ];

        return $user_denied;
    }

    public function can(
        string $resource,
        string $action,
        int $organizationId,
        string $state
    ): bool {
        // 1. Profile Permission

        $permissions =
            $this->get_user_allowed_permissions();


        if (
            !in_array(
                $action,
                $permissions[$resource] ?? []
            )
        ) {
            return false;
        }


        // 2. Organization Scope

        $organizations =
            $this->get_user_organization_scope();


        if (
            !in_array(
                $organizationId,
                $organizations
            )
        ) {
            return false;
        }


        // 3. Policy Denied Context

        $denied =
            $this->get_user_denied_context();


        if (
            isset(
                $denied[$resource][$organizationId][$state]
            )
            &&
            in_array(
                $action,
                $denied[$resource][$organizationId][$state]
            )
        ) {
            return false;
        }


        return true;
    }

    public function applyProductScope($builder)
    {
        $organizations =
            $this->get_user_organization_scope();


        $builder->whereIn(
            'organization_id',
            $organizations
        );


        $denied =
            $this->get_user_denied_context();


        foreach (
            $denied['product'] ?? []
            as $orgId => $states
        ) {

            foreach ($states as $state => $actions) {

                if (
                    in_array('view', $actions)
                ) {

                    $builder->where(
                        "NOT (
                        organization_id={$orgId}
                        AND state='{$state}'
                    )"
                    );
                }
            }
        }


        return $builder;
    }
}
