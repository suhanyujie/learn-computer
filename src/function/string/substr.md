# substr 函数源码分析
>*本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/substr.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/substr.md* <br>
基于PHP 7.3.3

## php 中的 substr
* php 中的 [substr](https://php.net/substr)，它的签名是 `substr ( string $string , int $start [, int $length ] ) : string`
* 参数列表虽不多，但提供的功能却挺多，看源码前，可以先梳理一下其功能
* `1.1` 如果 start 是非负数，返回的字符串将从 string 的 start 位置开始，从 0 开始计算。例如，在字符串 “abcdef” 中，在位置 0 的字符是 “a”，位置 2 的字符串是 “c” 等等。
* `1.2`如果 start 是负数，返回的字符串将从 string 结尾处向前数第 start 个字符开始。
* `1.3` 如果 string 的长度小于 start，将返回 FALSE。
* `1.4` 如果提供了正数的 length，返回的字符串将从 start 处开始最多包括 length 个字符（取决于 string 的长度）。
* `1.5` 如果提供了负数的 length，那么 string 末尾处的 length 个字符将会被省略（若 start 是负数则从字符串尾部算起）。如果 start 不在这段文本中，那么将返回 FALSE。
* `1.6` 如果提供了值为 0，FALSE 或 NULL 的 length，那么将返回一个空字符串。

## 源码
* 源码位于 `\ext\standard\string.c` ，[`2405` 行](https://github.com/php/php-src/blob/PHP-7.3.3/ext/standard/string.c#L2405)，可以搜索 `PHP_FUNCTION(substr)`
* 函数体第3行，获取参数个数 `int argc = ZEND_NUM_ARGS();`
* 紧接着是参数解析部分：

```c
ZEND_PARSE_PARAMETERS_START(2, 3)
    Z_PARAM_STR(str)
    Z_PARAM_LONG(f)
    Z_PARAM_OPTIONAL
    Z_PARAM_LONG(l)
ZEND_PARSE_PARAMETERS_END();
```

* `2` 表示最少要传递2个参数，`3`表示最多3个参数
* 针对参数的个数的判断和处理：

```c
if (argc > 2) {
    if ((l < 0 && (size_t)(-l) > ZSTR_LEN(str))) {
        RETURN_FALSE;
    } else if (l > (zend_long)ZSTR_LEN(str)) {
        l = ZSTR_LEN(str);
    }
} else {
    l = ZSTR_LEN(str);
}
```

* 这里可以看出，当 length 参数值超过传入字符串的长度时，底层直接将 length 赋值为字符串的长度值
* 如果只传入 2 个参数，则 length 值也赋值为字符串的长度值。
* 有个地方有点疑惑，针对 start （c语言中的参数名是 `f` ），当其小于 0 时，转换为正数是可以这样写 `(size_t)-f`
* 如果 start 的值大于字符串的长度，则直接 `RETURN_FALSE;`
* 针对 start 和 length 的处理后，再针对字符串进行返回。
* 返回值的情况分为4类：
    * `length == 0`
    * `length == 1`
    * `length == strLen`
    * `length > 1 && length < strLen`
* strLen 是字符串长度。在这里，只讨论一下普遍的情况—— `length > 1 && length < strLen` 

```c
// z 是返回值指针，s 是字符串指针， l 代表 length，
do {				\
    do {					\
        zval *__z = (z);						\
        zend_string *__s = (zend_string_init(s, l, 0));					\
        Z_STR_P(__z) = __s;						\
        Z_TYPE_INFO_P(__z) = IS_STRING_EX;		\
    } while (0)
} while (0)
```

* 综上， start 为负数是，源码中会将其转换为大于等于 0 的数，如果 length 小于 0 ，则源码将其转换为 大于等于 0  的数，最后，才回根据计算后的 start 和 length 返回最终的字符数组（字符串）。也就是 `RETURN_STRINGL(ZSTR_VAL(str) + f, l);` 的结果。
