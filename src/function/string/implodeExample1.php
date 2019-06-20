<?php
$pieces = [
    123,
    ',是一个',
    'number!',
];
$str1 = implode($pieces);
$str2 = implode('', $pieces);

var_dump($str1, $str2);
/*
string(20) "123,是一个number!"
string(20) "123,是一个number!"
*/
