<?php
class ThirdTypeA implements Countable
{
    public $p1 = "第三方";
    
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
dd_count_object($ins);
