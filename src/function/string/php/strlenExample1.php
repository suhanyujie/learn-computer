<?php
$str = "hello world";
$len =strlen($str);
var_dump($len);

$str = "hello";
$str2 = "特朗普";
$len = mb_strlen($str);
$len2 = mb_strlen($str2, 'UTF-8');
$len2_1 = mb_strlen($str2, 'GBK');
echo "{$str}:{$len}\n";
echo "{$str2}-UTF-8:{$len2}\n";
echo "{$str2}-GBK:{$len2_1}\n";
