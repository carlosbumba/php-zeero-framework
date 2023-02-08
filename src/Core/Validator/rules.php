<?php

use Zeero\Core\Utils\RegexPatterns;
use Zeero\Core\Validator\Rule;
use Zeero\Core\Validator\Validator;
use Zeero\Database\DataBase;

$rules = [];


/**
 * 
 * DEFAULT VALIDATOR RULES
 * 
 */

$rules[] = Rule::Builder('min', function ($var, $value) {
    if (
        is_string($value)
        && !preg_match('/^\d+/', $value)
    ) $value = strlen($value);

    return $value >= $var;
});


$rules[] = Rule::Builder('bigger', function ($var, $value) {
    return $value > $var;
});

$rules[] = Rule::Builder('lower', function ($var, $value) {
    return $value < $var;
});


// max
$rules[] = Rule::Builder('max', function ($var, $value) {
    if (
        is_string($value)
        && !preg_match('/^\d+/', $value)
    ) $value = strlen($value);

    return $value <= $var;
});

/** range rule
 * -> return true if the parameter $value is in range of $value[0] and $value[1]
 */
$rules[] = Rule::Builder("in_range",  function (array $var, $value) {
    gettype($value) == "string" ? $len = strlen($value) : $len = $value;
    $r = (range((int) $var[0], (int) $var[1]));
    return in_array($len, $r);
});


/** out range rule
 * -> return true if the parameter $value is out range of $value[0] and $value[1]
 */

$rules[] = Rule::Builder("out_range",  function (array $var, $value) {
    gettype($value) == "string" ? $len = strlen($value) : $len = $value;
    $r = (range($var[0], $var[1]));
    return in_array($len, $r) === false;
});



/** range rule
 * -> return true if the parameter $value is a value of array 
 */
$rules[] = Rule::Builder("in_list", function (array $var, $value) {
    $value = strval($value);
    return in_array($value, $var);
});

//

$rules[] = Rule::Builder("pattern",  function ($var, $value) {

    $var = is_string($var) ? $var : $var[0];

    if (RegexPatterns::get($var ?? '')) {
        return RegexPatterns::test($var, $value) == 1;
    } else
        $var = "/^" . $var . "$/";

    return preg_match($var, $value) == 1;
});


$rules[] = Rule::Builder("same",  function ($data1, $data) {
    return $data1 === $data;
});

$rules[] = Rule::Builder("dif",  function ($var, $value) {
    return $var !== $value;
});


/**
 * Unique Rule
 * 
 * test if a data not exists in a table record
 */
$rules[] = Rule::Builder("unique", function ($table, $field, $value) {

    if ($value == "") return true;

    $sql = "SELECT COUNT(*) FROM `$table` WHERE $field = :vv  LIMIT 1";
    $smt = DataBase::PreparedStatment($sql, [':vv' => $value]);

    if ($smt) {
        if (is_array($result = $smt->fetch()))
            return $result["COUNT(*)"] == 0;
    }
});


/**
 * Unique Rule
 * 
 * test if a data exists in a table record
 */
$rules[] = Rule::Builder("exists", function ($table, $field, $value) {
    $sql = "SELECT COUNT(*) FROM `$table` WHERE $field = :vv LIMIT 1";

    $smt = DataBase::PreparedStatment($sql, [':vv' => $value]);

    if ($smt and is_array($result = $smt->fetch()))
        return $result["COUNT(*)"] > 0;
});

// register the rules
foreach ($rules as $obj) {
    Validator::addRule($obj);
}
