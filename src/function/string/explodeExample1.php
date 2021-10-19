<?php
$str = "who are you ...";
$case1 = explode('', $str);
var_dump($case1);
$case1 = explode(' ', $str);
var_dump($case1);
$case1 = explode('o', $str);
var_dump($case1);
$case1 = explode('o', $str, 2);
var_dump($case1);

