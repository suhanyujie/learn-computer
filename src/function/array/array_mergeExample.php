<?php
$class1 = [
    '张天痕',
    '狗胜',
    '张天虎',
];
$class2 = [
    '胡桃',
    '水尚',
];
$class3 = [
    '魏司',
];

$arr = array_merge($class1, $class2, $class3);
var_dump($arr);

$value1 = 1;
$arr = [1,2,3,];
$arr = array_merge($value1, $arr);

