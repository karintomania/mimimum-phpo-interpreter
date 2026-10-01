<?php

declare(strict_types=1);

require __DIR__ . '/interpreter.php';

class SkipTestException extends Exception {};

function skip() { throw new SkipTestException(); }

function assertSame ($wants, $got) {
    if ($wants !== $got) throw new Exception("Expected $wants, got $got");
}
function assertTrue ($got) {
    if (!$got) throw new Exception("Expected true");
}

function describe(string $desc, Closure $tests): void {
    print("-----$desc -----\n");
    try {
        $tests();
    } catch (SkipTestException){
        printf("Skip test: %s\n", $desc);
    }
}

function test(string $name, Closure $closure, array $args = []) {
    if (empty($args)) {
        try {
            $closure();
            print("✅: $name\n");
        } catch (SkipTestException $e){
            printf("Skip test: %s\n", $name);
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
        } catch (SkipTestException $e){
            printf("Skip test: %s, %s\n", $key, $name);
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

test('takeToken', function ($code, $want) {
        skip();
        $pos = 0;
        $i = 0;
        while($t = takeToken($code, $pos)) {
            assertSame($want[$i++], $t);
        }
        assertSame(count($want), $i);
    },
    [
        'single char tokens' => [
            '{}[]()<>"\'+-*/%=$,.;',
            explode('|', '{|}|[|]|(|)|<|>|"|\'|+|-|*|/|%|=|$|,|.|;'),
        ],
        'keywords' => [
            'forfunctionreturnifelse',
            explode(',', 'for,function,return,if,else'),
        ],
        'numbers' => [
            '123;',
            ['123', ';'],
        ],
        // var or function name
        'words' => [
            'test;funcName;snake_case',
            ['test', ';', 'funcName', ';', 'snake_case'],
        ],
    ]
);

describe("eval_expr", function () {
    skip();
    test ("evaluate arithmatic operations",
        function ($code, $answer) {
            $pos = 0;
            $vars = [];
            $funcs = [];
            $result = eval_expr($code, $pos, $vars, $funcs);

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
            $funcs = [];
            $result = eval_expr($code, $pos, $vars, $funcs);

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
            $funcs = [];
            evaluate($code, $pos, $vars, $funcs);

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
            $funcs = [];
            evaluate($code, $pos, $vars, $funcs);

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

describe("eval_def", function () {
    // skip();
    test ("eval_def",
        function ($code, $val) {
            $pos = 0;
            $vars = [];
            $funcs = [];
            $got = evaluate($code, $pos, $vars, $funcs);
            var_dump("got=$got");

            assertSame((int)$val, (int)$got);
        },
        [
            // can't include space
            ['functiontest(){return1+1;};returntest();', 2],
        ]
    );
});
