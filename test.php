<?php

declare(strict_types=1);

require __DIR__ . '/interpreter.php';

function assertSame ($wants, $got) {
    if ($wants !== $got) throw new Exception("Expected $wants, got $got");
}
function assertTrue ($got) {
    if (!$got) throw new Exception("Expected true");
}

function describe(string $desc, Closure $tests): void {
    print("-----$desc -----\n");
    $tests();
}

function test(string $name, Closure $closure, array $args = []) {
    if (empty($args)) {
        try {
            $closure();
            print("✅: $name\n");
        } catch (Exception $e){
            printf(
                "❌: %s: %s\n%s",
                $name,
                $e->getMessage(),
                $e->getTraceAsString(),
            );
        }
        return;
    }

    foreach($args as $key => $arg) {
        try {
            $closure(...$arg);
            print("✅: $name [$key]\n");
        } catch (Exception $e){
            printf(
                "❌: %s [%s]: %s\n%s",
                $name,
                $key,
                $e->getMessage(),
                $e->getTraceAsString(),
            );
        }
    }
};

describe("eval_expr", function () {
    test ("evaluate arithmatic operations",
        function ($code, $answer) {
            $pos = 0;
            $vars = [];
            $result = eval_expr($code, $pos, $vars);

            assertSame((float)$answer, (float)$result);
        },
        [
            ["1+1;", 2],
            ["1*2*3;", 6],
            ["1+2*3;", 7],
            ["1+2+3+4;", 10],
            ["1+2+3/2;", 4.5],
            ["1*2+3/2;", 3.5],
            ["3*(2+3)*4;", 60],
            ["3*5%2;", 1],
            ["3*(1+2*(3-4));", -3],
        ]
    );

    test ("evaluate string",
        function ($code, $answer) {
            $pos = 0;
            $vars = [];
            $result = eval_expr($code, $pos, $vars);

            assertSame((float)$answer, (float)$result);
        },
        [
            ["'single quote'", 'single quote'],
            ['"double quote"', 'double quote'],
        ],
    );
});

describe("eval_assign", function () {
    test ("assign var",
        function ($code, $name, $val) {
            $pos = 0;
            $vars = [];
            evaluate($code, $pos, $vars);

            assertSame((float)$val, (float)$vars[$name]);
        },
        [
            ['$a=1+1;', 'a', 2],
            ['$a=1;$a=$a+$a;', 'a', 2],
            ['$result=7+3*2;', 'result', 13],
            ['$ary=[];', 'ary', []],
        ]
    );
    test ("assign array",
        function ($code, $ary, $key, $val) {
            $pos = 0;
            $vars = [];
            evaluate($code, $pos, $vars);

            assertSame((float)$val, (float)$vars[$ary][$key]);
        },
        [
            ['$a[1]=12;', 'a', '1', 12],
            ['$a=[1,2,3];', 'a', '1', 2],
            ['$a=[\'test\'=>1,\'test2\'=>\'value\'];', 'a', 'test2', 'value'],
            ['$a=[1,2,3];$a[1]=0;', 'a', '1', 0],
        ]
    );
});
