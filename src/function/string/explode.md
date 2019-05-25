# explode 函数源码分析
>*本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/explode.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/explode.md* <br>
基于PHP 7.3.3

## PHP 中的 explode
* 先温习一下 PHP 中 explode 的使用
* 它的函数签名如下：`explode ( string $delimiter , string $string [, int $limit ] ) : array`
* 在大部分的使用中，可能是传递2个参数，limit 参数用的不多。

### explode 参数
* delimiter 是分隔符，类型是字符串，必传
* string 是输入的字符串，类型是字符串，必传
* limit 的作用：
> 如果设置了 limit 参数并且是正数，则返回的数组包含最多 limit 个元素，而最后那个元素将包含 string 的剩余部分。<br>
> 如果 limit 参数是负数，则返回除了最后的 -limit 个元素外的所有元素。<br>
> 如果 limit 是 0，则会被当做 1。<br>

* 针对参数不同，有以下几种调用方式：

```php
<?php
$str = "who are you ...";
$case1 = explode('', $str);// 第一个参数必传，如果为空，则报 Warning
var_dump($case1);
$case1 = explode(' ', $str);
var_dump($case1);
$case1 = explode('o', $str);
var_dump($case1);
$case1 = explode('o', $str, 2);
var_dump($case1);
```

* 对应的输出结果如下：

```
PHP Warning:  explode(): Empty delimiter in /xxxxxx/explodeExample1.php on line 3
bool(false)
array(4) {
  [0]=>
  string(3) "who"
  [1]=>
  string(3) "are"
  [2]=>
  string(3) "you"
  [3]=>
  string(3) "..."
}
array(3) {
  [0]=>
  string(2) "wh"
  [1]=>
  string(6) " are y"
  [2]=>
  string(5) "u ..."
}
array(2) {
  [0]=>
  string(2) "wh"
  [1]=>
  string(12) " are you ..."
}
```

* 这样，对 explode 的使用有了大概的印象，接下来，我们深入其中的 c 源代码了解一下 explode 的实现

## explode 源码实现
* 通过搜索关键字 `PHP_FUNCTION(explode)` 可以找到，该函数定义于 `\ext\standard\string.c` 文件中的 [1155 行](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/standard/string.c#L1155)
* 首先，函数体在开头声明了部分变量用于接收传递进来的参数。然后，对输入的参数解析，放入开始的时候声明的变量。

```c
zend_string *str, *delim;
zend_long limit = ZEND_LONG_MAX; /* No limit */
zval tmp;

ZEND_PARSE_PARAMETERS_START(2, 3)
    Z_PARAM_STR(delim)
    Z_PARAM_STR(str)
    Z_PARAM_OPTIONAL
    Z_PARAM_LONG(limit)
ZEND_PARSE_PARAMETERS_END();
```

* 接着，有一个 if 判断，判断分隔符的长度是否是 0 ，如果为 0 ，则返回 warning 信息，这也就是我们在前面提到的，当参数为空字符串时，会报 Empty delimiter：

```c
if (ZSTR_LEN(delim) == 0) {
    php_error_docref(NULL, E_WARNING, "Empty delimiter");
    RETURN_FALSE;
}
```

* 初始化一个空数组，用于存放返回的结果：`array_init(return_value);`
* 然后判断输入的 string 字符串是否是空字符串，如果为空字符串，则执行：`zend_hash_index_add_new(Z_ARRVAL_P(return_value), 0, &tmp);`
* 这个函数的作用先不管，我们向后看。
* 后面一段的逻辑分别对 limit 参数大于1，小于0以及其他情况分别做处理。
* 在大多数场景中，我们不会传递 limit ，因此它的默认值是 1 ，到这里的执行会走向“其他情况”的分支，也就是：

```c
ZVAL_STR_COPY(&tmp, str);// 这里讲 str 的内容拷贝到 tmp 中
zend_hash_index_add_new(Z_ARRVAL_P(return_value), 0, &tmp);
```

* 







