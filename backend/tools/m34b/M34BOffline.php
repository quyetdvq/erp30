<?php

declare(strict_types=1);

require_once __DIR__ . '/M34BPlan.php';

final class M34BOffline
{
    /** @return array{total:int,passed:int,failed:list<array{test:string,exit:int,tail:string}>} */
    public static function run(string $root): array
    {
        $pure = glob($root.'/backend/tests/*_pure.php');
        $fixtures = glob($root.'/backend/tests/*_fixture.php');
        $tests = ($pure === false || $fixtures === false) ? [] : [...$pure, ...$fixtures];
        if (count($tests) < 35) {
            throw new M34BFailure('Missing prior M1-M3.4A.1 regression suites.');
        }
        sort($tests, SORT_STRING);
        $passed = 0; $failed = [];
        foreach ($tests as $file) {
            $name = basename($file);
            $process = proc_open([PHP_BINARY,$file], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
                $pipes, $root);
            if (! is_resource($process)) {
                $failed[] = ['test'=>$name,'exit'=>-1,'tail'=>'Could not start test'];
                continue;
            }
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process);
            if ($exit === 0 && str_contains($stdout, 'PASS')) {
                $passed++;
            } else {
                $failed[] = ['test'=>$name,'exit'=>$exit,
                    'tail'=>substr(trim($stdout . "\n" . $stderr), -900)];
            }
        }
        return ['total'=>count($tests),'passed'=>$passed,'failed'=>$failed];
    }
}
