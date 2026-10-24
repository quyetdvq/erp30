<?php

declare(strict_types=1);

require_once __DIR__ . '/../m34/M34Core.php';

/** Exactly 29 explicit, dependency-ordered positive HTTP cases. */
final class M34BPlan
{
    /** @return list<string> */
    public static function order(): array
    {
        return [
            'createWorkflowState','updateWorkflowState','setWorkflowStateState',
            'createLifecycle','updateLifecycle','configureLifecycleStates',
            'configureLifecycleTransitions','setLifecycleState',
            'createResource','updateResource','createResourceAction','updateResourceAction',
            'deleteResourceAction','setResourceState','deleteResource',
            'createAuthorizationGroup','updateAuthorizationGroup','replaceAuthorizationGroupMembers',
            'replaceAuthorizationGroupPermissions','setAuthorizationGroupState',
            'createUser','updateUser','replaceUserPermissions','resetUserPassword','setUserState',
            'createPolicy','updatePolicy','setPolicyState','deletePolicy',
        ];
    }

    /** @param array<string,array{method:string,path:string,status:int}> $ops */
    public static function assertCoverage(array $ops): void
    {
        $list = self::order();
        if (count($list) !== 29 || count(array_unique($list)) !== 29
            || array_diff($list, array_keys($ops)) !== []
            || array_diff(array_keys($ops), $list) !== []) {
            throw new M34BFailure('Positive test plan is incomplete or differs from pinned 29-operation OpenAPI.');
        }
    }

    /** @param array<string,mixed> $context @return array{operation:string,path:string,body:array<string,mixed>} */
    public static function case(string $name, array $context): array
    {
        $id = static fn ($label): string => (string) ($context[$label] ?? throw new M34BFailure('Missing positive dependency: ' . $label));
        $token = (string) ($context['token'] ?? '');
        $root = $id('root');
        $resource = $id('resource_resource');
        $action = $id('action_resource_view');
        $group = $id('admin_group');
        $email = 'm34b-user-' . substr($token, 0, 10) . '@example.test';
        $userName = 'm34b_u_' . substr($token, 0, 10);
        $descriptions = [
            'createWorkflowState' => static fn () => ['/api/v1/workflow-states', ['code'=>'M34B_EXTRA_' . strtoupper(substr($token,0,10)), 'name'=>'M34B extra','status'=>'ACTIVE']],
            'updateWorkflowState' => static fn () => ['/api/v1/workflow-states/'.$id('new_workflow_state'), ['name'=>'M34B extra updated']],
            'setWorkflowStateState' => static fn () => ['/api/v1/workflow-states/'.$id('new_workflow_state').'/actions/set-state', ['status'=>'INACTIVE']],
            'createLifecycle' => static fn () => ['/api/v1/lifecycles', ['code'=>'M34B_LC_' . strtoupper(substr($token,0,10)), 'name'=>'M34B lifecycle','status'=>'INACTIVE']],
            'updateLifecycle' => static fn () => ['/api/v1/lifecycles/'.$id('new_lifecycle'), ['name'=>'M34B lifecycle updated']],
            'configureLifecycleStates' => static fn () => ['/api/v1/lifecycles/'.$id('new_lifecycle').'/states', ['states'=>[
                ['state_id'=>$id('state_active'), 'sequence'=>1, 'is_initial'=>true, 'is_final'=>false],
                ['state_id'=>$id('state_inactive'), 'sequence'=>2, 'is_initial'=>false, 'is_final'=>true],
            ]]],
            'configureLifecycleTransitions' => static fn () => ['/api/v1/lifecycles/'.$id('new_lifecycle').'/transitions', ['transitions'=>[
                ['from_state_id'=>$id('state_active'), 'to_state_id'=>$id('state_inactive'), 'action_code'=>'finish', 'status'=>'ACTIVE'],
            ]]],
            'setLifecycleState' => static fn () => ['/api/v1/lifecycles/'.$id('new_lifecycle').'/actions/set-state', ['status'=>'ACTIVE']],
            'createResource' => static fn () => ['/api/v1/resources', ['type'=>'m34b_asset_'.substr($token,0,10),'name'=>'M34B Asset','domain'=>'MASTER', 'lifecycle_id'=>$id('new_lifecycle')]],
            'updateResource' => static fn () => ['/api/v1/resources/'.$id('new_resource'), ['name'=>'m34b asset updated']],
            'createResourceAction' => static fn () => ['/api/v1/resources/'.$id('new_resource').'/actions', ['code'=>'m34b_review']],
            'updateResourceAction' => static fn () => ['/api/v1/resources/'.$id('new_resource').'/actions/'.$id('new_action'), ['description'=>'M34B checked']],
            'deleteResourceAction' => static fn () => ['/api/v1/resources/'.$id('new_resource').'/actions/'.$id('new_action'), []],
            'setResourceState' => static fn () => ['/api/v1/resources/'.$id('new_resource').'/actions/set-state', ['status'=>'INACTIVE']],
            'deleteResource' => static fn () => ['/api/v1/resources/'.$id('new_resource'), []],
            'createAuthorizationGroup' => static fn () => ['/api/v1/authorization-groups', ['code'=>'M34B_TEST_'.strtoupper(substr($token,0,10)), 'name'=>'M34B Group']],
            'updateAuthorizationGroup' => static fn () => ['/api/v1/authorization-groups/'.$id('new_group'), ['name'=>'M34B Group Updated']],
            'replaceAuthorizationGroupMembers' => static fn () => ['/api/v1/authorization-groups/'.$id('new_group').'/members', ['user_ids'=>[$id('admin_user')]]],
            'replaceAuthorizationGroupPermissions' => static fn () => ['/api/v1/authorization-groups/'.$id('new_group').'/permissions', ['resource_action_ids'=>[$action]]],
            'setAuthorizationGroupState' => static fn () => ['/api/v1/authorization-groups/'.$id('new_group').'/actions/set-state', ['status'=>'INACTIVE']],
            'createUser' => static fn () => ['/api/v1/users', ['username'=>$userName,'email'=>$email,'password'=>$id('new_user_password'),'state'=>'ACTIVE','name'=>'M34B Synthetic']],
            'updateUser' => static fn () => ['/api/v1/users/'.$id('new_user'), ['name'=>'M34B Synthetic Updated']],
            'replaceUserPermissions' => static fn () => ['/api/v1/users/'.$id('new_user').'/permissions', ['permissions'=>['resource.view']]],
            'resetUserPassword' => static fn () => ['/api/v1/users/'.$id('new_user').'/actions/reset-password', ['new_password'=>$id('reset_password')]],
            'setUserState' => static fn () => ['/api/v1/users/'.$id('new_user').'/actions/set-state', ['state'=>'INACTIVE']],
            'createPolicy' => static fn () => ['/api/v1/policies', [
                'organization_id'=>$root, 'resource_id'=>$resource, 'state_id'=>null,
                'participant_type'=>'GROUP','participant_ref_id'=>$group,
                'actions'=>[['resource_action_id'=>$action, 'effect'=>'ALLOW']],
            ]],
            'updatePolicy' => static fn () => ['/api/v1/policies/'.$id('new_policy'), ['actions'=>[['resource_action_id'=>$action, 'effect'=>'ALLOW']]]],
            'setPolicyState' => static fn () => ['/api/v1/policies/'.$id('new_policy').'/actions/set-state', ['status'=>'INACTIVE']],
            'deletePolicy' => static fn () => ['/api/v1/policies/'.$id('new_policy'), []],
        ];
        if (! isset($descriptions[$name])) { throw new M34BFailure('Unknown M34B test operation'); }
        [$path, $body] = $descriptions[$name]();
        return ['operation'=>$name,'path'=>$path,'body'=>$body];
    }

    /** @return string|null */
    public static function createdKey(string $operation): ?string
    {
        return [
            'createWorkflowState'=>'new_workflow_state', 'createLifecycle'=>'new_lifecycle',
            'createResource'=>'new_resource','createResourceAction'=>'new_action',
            'createAuthorizationGroup'=>'new_group','createUser'=>'new_user',
            'createPolicy'=>'new_policy',
        ][$operation] ?? null;
    }
}
