<?php

function takeRegex($regex, $code, &$pos) {
    $s = substr($code, $pos);
    $m = [];
    if (preg_match("/$regex/", $s, $m)) {
        $pos += strlen($m[1]);
        return $m[1];
    };
    return false;
}

function take($code, &$pos, $n=1) {
    $s=substr($code, $pos, $n);
    $pos += $n;
    return $s;
}

function takeToken($code, &$pos) {
    // lexer in one regex
    while($pos < strlen($code)) {
        if ($t=takeRegex(
            '^(([{}\[\]()<>"\'+\-*\/\%=$.,;])|(for|function|return|if|else)|(\d+)|(\w+))',
            $code,
            $pos
        )) return $t;
        $pos++;
    }
}
function seekToken($code, $pos) {
    return takeToken($code, $pos);
}


function takeSurrounded($open, $code, &$pos) {
    $close = match ($open) {
        '[' => ']',
        '(' => ')',
        '{' => '}',
    };
    $nest = 0;
    $result = '';
    $pos++;
    while(($c = take($code, $pos)) != $close || $nest != 0) {
        if ($c == $open) $nest++;
        if ($c == $close) $nest--;
        $result .= $c;
    }
    return $result;
}


function eval_expr($code, &$pos, &$vars, &$funcs) {
    return eval_t2($code, $pos, $vars, $funcs);
};

function eval_t2($code, &$pos, &$vars, &$funcs){
    $l = eval_t1($code, $pos, $vars, $funcs);

    while ($c = takeToken($code, $pos)) {
        if ($c == '+') {
            $r = eval_t1($code, $pos, $vars, $funcs);
             $l += $r;
        } else if ($c == '-') {
            $r = eval_t1($code, $pos, $vars, $funcs);
             $l -= $r;
        } else {
            $pos--;
            return $l;
        }
    }
    return $l;
};

function eval_t1($code, &$pos, $vars, &$funcs){
    $l = eval_atom($code, $pos, $vars, $funcs);

    while ($c = takeToken($code, $pos)) {
        if ($c == '*') {
            $r = eval_atom($code, $pos, $vars, $funcs);
            $l *= $r;
        } else if ($c == '/'){
            $r = eval_atom($code, $pos, $vars, $funcs);
            $l /= $r;
        } else if ($c == '%'){
            $r = eval_atom($code, $pos, $vars, $funcs);
            $l %= $r;
        } else {
            $pos--;
            return $l;
        }
    }
    return $l;
};

function eval_ary($code, &$pos, &$vars, &$funcs) {
    if (str_starts_with($code,"[]")) {
        $pos += 2;
        return [];
    }

    $result = [];

    $inside = takeSurrounded('[', $code, $pos);
    $pos += 1;


    foreach(explode(',', $inside) as $elm) {
        if ($elm === '') continue;

        if (str_contains($elm, "=>")) {
            [$keyRaw, $valueRaw]=explode('=>', $elm);

            $ePos = 0;
            $key=eval_expr($keyRaw, $ePos, $vars, $funcs);
            $ePos = 0;
            $value=eval_expr($valueRaw, $ePos, $vars, $funcs);
            $result[$key] = $value;
        } else {
            $ePos=0;
            $result[]=eval_expr($elm, $ePos, $vars, $funcs);
        }
    }

    return $result;
}

function eval_call($f, $code, &$pos, &$vars, &$funcs) {
    var_dump('eval_call', substr($code, $pos), 'l'.__LINE__);
    $argsRaw = explode(',', takeSurrounded('(', $code, $pos));
    var_dump('eval_call', $argsRaw, 'l'.__LINE__);
    $localVars = [];
    foreach($argsRaw as $i => $a) {;
        $aPos = 0;
        $localVar[$f[0][$i]] = eval_expr($a, $aPos, $vars, $funcs);
    }
    $localPos = 0;
    var_dump($f[1], $localVars, __LINE__);
    return evaluate($f[1], $localPos, $localVars, $funcs);
}

function eval_atom($code, &$pos, &$vars, &$funcs) {
    $c = $code[$pos];

    if ($c == '\'' || $c == '"') {
        $s = takeRegex('(\'.*\'|".*")', $code, $pos);
        $pos++;
        return substr($s,1,-1);
    }

    if ($c == '(') {
        $expr = takeSurrounded('(', $code, $pos);
        $exprPos = 0;
        $result = eval_expr($expr, $exprPos, $vars, $funcs);
        return $result;
    }

    if ($c=='[') {
        return eval_ary($code, $pos, $vars, $funcs);
    }

    if ($c=='$') {
        $var = takeRegex('\$(\w+)', $code, $pos);
        $pos++;
        return $vars[$var];
    }

    $t = takeToken($code, $pos);

    if (array_key_exists($t, $funcs)) {
        return eval_call($funcs[$t], $code, $pos, $vars, $funcs);
    } else {
        return $t;
    }
}

function eval_assign($code, &$pos, &$vars, &$funcs) {
    $name = takeToken($code, $pos);
    $t = seekToken($code, $pos);

    // assign array
    if($t == '[') {
        $key = takeSurrounded('[', $code, $pos);
        $valExpr = takeRegex('=(.+?;)', $code, $pos);
        $exprPos = 0;
        if (isset($vars[$name])) {
            $vars[$name][$key] = eval_expr($valExpr, $exprPos, $vars, $funcs);
        } else {
            $vars[$name] = [];
            $vars[$name][$key] = eval_expr($valExpr, $exprPos, $vars, $funcs);
        }
        return;
    };
    $valExpr = takeRegex('=(.+?;)', $code, $pos);
    $exprPos = 0;
    $vars[$name] = eval_expr($valExpr, $exprPos, $vars, $funcs);
}

function eval_def($code, &$pos, &$vars, &$funcs) {
    $name = takeRegex('(.+?)\(', $code, $pos);
    $argsRaw = takeSurrounded('(', $code, $pos);
    $args = $argsRaw ? explode(',', $argsRaw) : [];
    $body = takeSurrounded('{', $code, $pos);

    $funcs[$name] = [$args, $body];

    var_dump($name, $funcs, 'l'.__LINE__);
}

function evaluate($code, &$pos, &$vars, &$funcs) {
    while($t = takeToken($code, $pos)) {
        // assign
        if ($t == '$') {
            eval_assign($code, $pos, $vars, $funcs);
            continue;
         }
        // fun
        if ($t == 'function') {
            eval_def($code, $pos, $vars, $funcs);
            continue;
        }

        if (array_key_exists($t, $funcs)) {
            eval_call($funcs[$t], $code, $pos, $vars, $funcs);
        } else if ($t == 'return') {
            $exp = takeRegex('(.+?;)', $code, $pos);
            var_dump("return!!$exp", 'l'.__LINE__);
            $expPos  = 0;
            return eval_expr($exp, $expPos, $vars, $funcs);
        } else if ($t == 'if') {
            var_dump('if');
        } else if ($t == 'for') {
            var_dump('for');
        }
        // ignore unknown keyword
    }
}
