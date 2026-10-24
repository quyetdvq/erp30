<?php

declare(strict_types=1);

namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}

namespace {
    // The production CI4 runtime requires mbstring. This isolated PHP
    // pure test uses ASCII fixtures when that extension is not installed.
    if (! function_exists('mb_strtolower')) {
        function mb_strtolower(string $value): string { return strtolower($value); }
    }
    require __DIR__ . '/../app/Modules/Access/PolicyReadLanes.php';
    require __DIR__ . '/../app/Shared/Http/AuthorizedListPage.php';

    use App\Modules\Access\PolicyReadLanes;
    use App\Shared\Http\AuthorizedListPage;
    use CodeIgniter\HTTP\Exceptions\BadRequestException;

    $n = 0;
    function ok(string $description, bool $result): void {
        global $n;
        if (! $result) {
            fwrite(STDERR, "FAIL: {$description}\n");
            exit(1);
        }
        $n++;
        echo "PASS: {$description}\n";
    }
    function p(string $type, string $id, string $effect): array {
        return ['participant_type' => $type, 'participant_ref_id' => $id, 'effect' => $effect];
    }
    function decide(array $rows, array $assignments = [], array $groups = ['9'], array $orgs = ['1','2']): bool {
        return PolicyReadLanes::allows($rows, $orgs, '15', $groups, $assignments);
    }
    ok('PBAC no matching policy is neutral after base permission', decide([]));
    ok('PBAC actor DENY without assignment lane denied', ! decide([p('USER','15','DENY')]));
    ok('PBAC actor DENY with neutral assignment lane allowed', decide([p('USER','15','DENY')], [['department_id'=>'3']]));
    ok('PBAC common DENY propagates to every lane', ! decide([p('ORGANIZATION','1','DENY')], [['department_id'=>'3']]));
    ok('PBAC common ALLOW overrides common DENY in lane', decide([p('ORGANIZATION','1','DENY'),p('ORGANIZATION','1','ALLOW')]));
    ok('PBAC absolute deny beats actor ALLOW', ! decide([p('USER','15','ALLOW'),p('POSITION','4','ABSOLUTE_DENY')], [['position_id'=>'4']]));
    ok('PBAC absolute deny on org vetoes', ! decide([p('ORGANIZATION','2','ABSOLUTE_DENY')]));
    ok('PBAC non-ancestor org policy does not match', decide([p('ORGANIZATION','55','DENY')]));
    ok('PBAC GROUP actor matches active group ID', ! decide([p('GROUP','9','DENY')]));
    ok('PBAC unmatched GROUP does not match', decide([p('GROUP','10','DENY')]));
    ok('PBAC assignment department participant matches', ! decide([p('DEPARTMENT','3','DENY'),p('USER','15','DENY')], [['department_id'=>'3']]));
    ok('PBAC actor neutral can allow denied assignment', decide([p('DEPARTMENT','3','DENY')], [['department_id'=>'3']]));
    ok('PBAC actor DENY and assignment ALLOW', decide([p('USER','15','DENY'),p('DEPARTMENT','3','ALLOW')], [['department_id'=>'3']]));
    ok('PBAC actor ALLOW and assignment DENY', decide([p('USER','15','ALLOW'),p('DEPARTMENT','3','DENY')], [['department_id'=>'3']]));
    ok('PBAC ordinary DENY loses to ALLOW within actor lane', decide([p('GROUP','9','DENY'),p('USER','15','ALLOW')]));
    ok('PBAC position assignment matches', ! decide([p('USER','15','DENY'),p('POSITION','4','DENY')], [['position_id'=>'4']]));
    ok('PBAC out of-scope unknown org not counted', decide([p('ORGANIZATION','3','DENY')]));
    ok('PBAC unknown participant kind does not broaden', decide([p('ROLE','15','DENY')]));
    try {
        decide([p('USER','15','UNKNOWN')]);
        ok('PBAC invalid effect fails closed', false);
    } catch (\UnexpectedValueException) {
        ok('PBAC invalid effect fails closed', true);
    }

    $visible = [
        ['id' => '11', 'code' => 'B', 'name' => 'Beta', 'status' => 'ACTIVE'],
        ['id' => '12', 'code' => 'A', 'name' => 'Anpha', 'status' => 'INACTIVE'],
        ['id' => '13', 'code' => 'A', 'name' => 'Alpha', 'status' => 'ACTIVE'],
    ];
    $page = AuthorizedListPage::paginate('province', $visible, ['page'=>'1','page_size'=>'2', 'sort'=>'code']);
    ok('Page meta counts only authorized rows', $page['total'] === 3);
    ok('Page size enforced', count($page['data']) === 2);
    ok('Sort uses id stable tie-break', array_column($page['data'], 'id') === ['12','13']);
    ok('Page field 1', $page['page'] === 1 && $page['page_size'] === 2);
    $page2 = AuthorizedListPage::paginate('province', $visible, ['page'=>'2','page_size'=>'2','sort'=>'code']);
    ok('Second page contains remainder', array_column($page2['data'], 'id') === ['11']);
    $search = AuthorizedListPage::paginate('province', $visible, ['q'=>'ALPHA']);
    ok('Unicode-safe case-insensitive name search', array_column($search['data'], 'id') === ['13']);
    $filtered = AuthorizedListPage::paginate('province', $visible, ['filter'=>['status'=>['ACTIVE']]]);
    ok('Filter reflects authorized subset', $filtered['total'] === 2);
    ok('Filter produces correct rows', array_column($filtered['data'], 'id') === ['11','13']);
    $none = AuthorizedListPage::paginate('ward', [], []);
    ok('Empty authorized list gives empty data+zero total', $none['total'] === 0 && $none['data'] === []);
    foreach ([
        ['sort'=>'-invalid'],
        ['filter'=>['not_real'=>'x']],
        ['page'=>'0'],
        ['page_size'=>'201'],
        ['filter'=>[]],
        ['unexpected'=>'yes'],
        ['sort'=>'name,name'],
        ['q'=>['bad']],
    ] as $q) {
        try {
            AuthorizedListPage::paginate('province', $visible, $q);
            ok('Malformed list input rejected', false);
        } catch (BadRequestException) {
            ok('Malformed list input rejected: ' . json_encode($q), true);
        }
    }
    echo "TOTAL: {$n} M2.1 assertions passed.\n";
}
