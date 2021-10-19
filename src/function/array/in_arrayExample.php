<?php
$exist = in_array(1, [1, 2]);
var_dump($exist);

$exist = in_array('1', [1, 2], true);
var_dump($exist);
echo "同一个类的实例化的判等：\n";
class A1
{
    public $name = "testName";
}
$c1 = new A1();
$c2 = new A1();
var_dump(in_array($c1, [$c2,], 1));// false
echo "\n数组的判等：\n";
$arr = [1,2];
$arrGroup = [
    [2,1],
    [1,2],
];
var_dump(in_array($arr, $arrGroup, 1));// true


