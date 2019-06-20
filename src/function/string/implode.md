# implode 函数源码分析
>*本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/implode.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/implode.md* <br>
基于PHP 7.3.3

## PHP 中的 implode
* 在 PHP 中，implode 的作用是：将一个一维数组的值转化为字符串。记住一维数组，如果是多维的，会发生什么呢？在本篇分析中，会有所探讨。
* 事实上，通过官方的文档可以知道，implode 有两种用法，通过函数签名可以看得出来：

```php
// 方法1
implode ( string $glue , array $pieces ) : string
// 方法2
implode ( array $pieces ) : string
```

* 因为，在不传 glue 的时候，内部实现会默认空字符串。
* 通过一个简单的示例可以看出：

```php
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
```

## implode 源码实现
* 通过搜索关键字 `PHP_FUNCTION(explode)` 可以找到，该函数定义于 `\ext\standard\string.c` 文件中的 [1288 行](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/standard/string.c#L1288)
* 一开始的几行是参数声明相关的信息。其中 *arg2 是用于接收 pieces 参数的指针。
* 在下方对 arg2 的判断中，如果 arg2 为空，则表示没有传 pieces 对应的值

```c
if (arg2 == NULL) {
    if (Z_TYPE_P(arg1) != IS_ARRAY) {
        php_error_docref(NULL, E_WARNING, "Argument must be an array");
        return;
    }

    glue = ZSTR_EMPTY_ALLOC();
    tmp_glue = NULL;
    pieces = arg1;
} else {
    if (Z_TYPE_P(arg1) == IS_ARRAY) {
        glue = zval_get_tmp_string(arg2, &tmp_glue);
        pieces = arg1;
    } else if (Z_TYPE_P(arg2) == IS_ARRAY) {
        glue = zval_get_tmp_string(arg1, &tmp_glue);
        pieces = arg2;
    } else {
        php_error_docref(NULL, E_WARNING, "Invalid arguments passed");
        return;
    }
}
```

### 不传递 pieces 参数
* 




