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


function eval_expr($code, &$pos, &$vars) {
    return eval_t2($code, $pos, $vars);
};

function eval_t2($code, &$pos, &$vars){
    $l = eval_t1($code, $pos, $vars);

    while ($c = take($code, $pos)) {
        if ($c == '+') {
            $r = eval_t1($code, $pos, $vars);
             $l += $r;
        } else if ($c == '-') {
            $r = eval_t1($code, $pos, $vars);
             $l -= $r;
        } else {
            $pos--;
            return $l;
        }
    }
    return $l;
};

function eval_t1($code, &$pos, $vars){
    $l = eval_atom($code, $pos, $vars);

    while ($c = take($code, $pos)) {
        if ($c == '*') {
            $r = eval_atom($code, $pos, $vars);
            $l *= $r;
        } else if ($c == '/'){
            $r = eval_atom($code, $pos, $vars);
            $l /= $r;
        } else if ($c == '%'){
            $r = eval_atom($code, $pos, $vars);
            $l %= $r;
        } else {
            $pos--;
            return $l;
        }
    }
    return $l;
};

function eval_ary($code, &$pos, &$vars) {
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
            $key=eval_expr($keyRaw, $ePos, $vars);
            $ePos = 0;
            $value=eval_expr($valueRaw, $ePos, $vars);
            $result[$key] = $value;
        } else {
            $ePos=0;
            $result[]=eval_expr($elm, $ePos, $vars);
        }
    }

    return $result;
}

function eval_atom($code, &$pos, &$vars) {
    $c = $code[$pos];

    if ($c == '\'' || $c == '"') {
        $s = takeRegex('(\'.*\'|".*")', $code, $pos);
        $pos++;
        return substr($s,1,-1);
    }

    if ($c == '(') {
        $expr = takeSurrounded('(', $code, $pos);
        $exprPos = 0;
        $result = eval_expr($expr, $exprPos, $vars);
        return $result;
    }

    if ($c=='[') {
        return eval_ary($code, $pos, $vars);
    }

    if ($c=='$') {
        $var = takeRegex('\$(\w+)', $code, $pos);
        $pos++;
        return $vars[$var];
    }

    return takeRegex('(\d+)', $code, $pos);
}

function eval_assign($code, &$pos, &$vars) {
    // assign array
    if($ary = takeRegex('(.+?)\[.*?\]=', $code, $pos)) {
        $key = takeRegex('\[(.*?)\]=', $code, $pos);
        $valExpr = takeRegex('=(.+?;)', $code, $pos);
        $exprPos = 0;
        if (isset($vars[$ary])) {
            $vars[$ary][$key] = eval_expr($valExpr, $exprPos, $vars);
        } else {
            $vars[$ary] = [];
            $vars[$ary][$key] = eval_expr($valExpr, $exprPos, $vars);
        }
        return;
    };
    $name = takeRegex('(.+?)=', $code, $pos);
    $valExpr = takeRegex('=(.+?;)', $code, $pos);
    $exprPos = 0;
    $vars[$name] = eval_expr($valExpr, $exprPos, $vars);
}

function eval_def($code, &$pos, &$vars) {
}

function eval_call($code, &$pos, &$vars) {
}

function evaluate($code, &$pos, &$vars) {
    while($c = take($code, $pos)) {
        // assign
        if ($c == '$') {
            $stmt = takeRegex('^(.+?;)', $code, $pos);
            $tPos = 0;
            eval_assign($stmt, $tPos, $vars);
            continue;
         }
        // fun
        if ($c == 'f' && str_starts_with($code,'unction')) {
            $pos+=7&&eval_def($code, $pos, $vars);
        }
        $pos++;
    }
}
