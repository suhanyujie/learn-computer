<?php
class ThirdTypeA implements Countable
{
    public $data = [
        'merchantId'=>1,
        'key'=>'testxxkey32Xsdadxaqqwey',
    ];

    public function count()
    {
        return count($this->data);
    }
}

$ins = new ThirdTypeA;
$res = count($ins);
var_dump($res);
