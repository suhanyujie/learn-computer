# PHP 源码 — array_merge 函数源码分析
>* 本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/array/array_merge.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/array/array_merge.md* 
>* 作者：[suhanyujie](https://github.com/suhanyujie)
>* 基于PHP 7.3.3

## PHP 中的 array_merge
* [array_merge 函数](https://php.net/array_merge)的签名从官方文档可见：

```
array_merge ( array $array1 [, array $... ] ) : array
```

* 它的作用是合并一个或者多个数组。通过下面的简单示例可以看出。

```php
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
```

* 函数签名明确地支持，参数必须都是数组类型，返回值也是数组。因而，array_merge 不适合将一个值合并到一个数组中。
* 通过运行 `php src\function\array\array_mergeExample.php`，输出如下：

```shell
array(6) {
  [0]=>
  string(9) "张天痕"
  [1]=>
  string(6) "狗胜"
  [2]=>
  string(9) "张天虎"
  [3]=>
  string(6) "胡桃"
  [4]=>
  string(6) "水尚"
  [5]=>
  string(6) "魏司"
}
```

## array_merge 实现源码
### 源码位置
* 函数 array_merge 在位于 `php-7.3.3/ext/standard/array.c` 中，可以[点击查看](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/standard/array.c#L3840)

### 源码解析
* array_merge 的源码定义很简洁：

```c
PHP_FUNCTION(array_merge)
{
	php_array_merge_or_replace_wrapper(INTERNAL_FUNCTION_PARAM_PASSTHRU, 0, 0);
}
```

* 像这样调用 php_array_merge_or_replace_wrapper 的 PHP 函数还有 array_merge_recursive、array_replace、array_replace_recursive。这个文章主要以 array_merge 作为示例。且去看看 php_array_merge_or_replace_wrapper 的实现

#### 函数 php_array_merge_or_replace_wrapper
* 该函数原型为：

```c
static inline void php_array_merge_or_replace_wrapper(INTERNAL_FUNCTION_PARAMETERS, int recursive, int replace);
```

* 其实现大概有 100 行代码。先看看开始的参数解析部分：

```c
ZEND_PARSE_PARAMETERS_START(1, -1)
    Z_PARAM_VARIADIC('+', args, argc)
ZEND_PARSE_PARAMETERS_END();
```

* 和常用的诸如 Z_PARAM_STR、Z_PARAM_LONG 等参数解析宏不同，这里用的是 `Z_PARAM_VARIADIC`。它的作用主要用于传入的参数是多个的时候使用，而 array_merge 函数的参数恰好适合这样的场景。关于它的详细资料可以[点此查看](https://phpinternals.net/docs/z_param_variadic)
* 当使用了 `Z_PARAM_VARIADIC('+', args, argc)` 后，PHP 用户态传入的所有参数都在 `*arg` 数组中。因此源码中使用了 for 循环判断其中的每个参数类型是否是 IS_ARRAY：

```c
for (i = 0; i < argc; i++) {
    zval *arg = args + i;

    if (Z_TYPE_P(arg) != IS_ARRAY) {
        php_error_docref(NULL, E_WARNING, "Expected parameter %d to be an array, %s given", i + 1, zend_zval_type_name(arg));
        RETURN_NULL();
    }
    count += zend_hash_num_elements(Z_ARRVAL_P(arg));
}
```

* 在这个 for 循环中，通统计了所有数组的值的个数，计算后数值为 count。后续将这个值用于指定返回值的大小 `array_init_size(return_value, count);`。
* 通过对 args 判断做不同的处理 `if (HT_FLAGS(src) & HASH_FLAG_PACKED) {`。其中 HASH_FLAG_PACKED 的[描述](https://phpinternals.net/docs/hash_flag_packed)如下：
> 这个标志位表示哈希表是 packed（PHP 7.0 开始引入的一种优化概念）的。意味着该数组更接近 c 数组的特性，它有比普通数组更低的内存消耗和更快的查找速度。为了能用这种特性，数组键必须是自然数，并且是递增的顺序排列（键之间要么间隔小，要么没有间隔）

* 看起来描述的似乎是特殊的索引数组。先不管这个，我们继续看代码。在数组 args 为连续的索引数组情况下对存放结果值进行填充：

```c
ZEND_HASH_FILL_PACKED(dest) {
    ZEND_HASH_FOREACH_VAL(src, src_entry) {
        if (UNEXPECTED(Z_ISREF_P(src_entry) &&
            Z_REFCOUNT_P(src_entry) == 1)) {
            src_entry = Z_REFVAL_P(src_entry);
        }
        Z_TRY_ADDREF_P(src_entry);
        ZEND_HASH_FILL_ADD(src_entry);
    } ZEND_HASH_FOREACH_END();
} ZEND_HASH_FILL_END();
```

### ZEND_HASH_FILL_PACKED 宏
* 先了解一下 ZEND_HASH_FILL_PACKED 宏。定义的内容如下：

```c
#define ZEND_HASH_FILL_PACKED(ht) do { \
		HashTable *__fill_ht = (ht); \
		Bucket *__fill_bkt = __fill_ht->arData + __fill_ht->nNumUsed; \
		uint32_t __fill_idx = __fill_ht->nNumUsed; \
		ZEND_ASSERT(HT_FLAGS(__fill_ht) & HASH_FLAG_PACKED);
```

* 源码注释中对它的解释是：
> 用于向某个数组中插入一个 packed 类型数组中的新值，可以代替 `zend_hash_next_index_insert_new()`

* 通过 ZEND_HASH_FOREACH_VAL 遍历数组 args，将其中的每个单元都填充到 return_value 中（`ZEND_HASH_FILL_ADD(src_entry);`）。填充前，将每个单元的引用 +1（`Z_TRY_ADDREF_P(src_entry);`）


## 参考资料
* Z_PARAM_VARIADIC 介绍 https://phpinternals.net/docs/z_param_variadic
