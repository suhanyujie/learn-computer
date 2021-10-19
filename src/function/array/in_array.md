# in_array 函数源码分析
>*本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/array/in_array.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/array/in_array.md* <br>
基于PHP 7.3.3

## php 中的 in_array
* php 中的 [in_array](https://php.net/in_array)，它的签名是 `in_array ( mixed $needle , array $haystack [, bool $strict = FALSE ] ) : bool`
* 按照惯例，先讨论一下 in_array 在PHP中的使用。一般情况下，都是传递2个参数，例如： `$exist = in_array(1, [1, 2]);` ，返回结果如下：

```
bool(true)
```

* 第三个参数 strict 的值为 TRUE 则 in_array() 函数还会检查 needle 的类型是否和 haystack 中的相同。

```php
$exist = in_array(1, [1, 2]);
//--------输出--------------
bool(false)
```

## 源码
* 源码位于 `\ext\standard\array.c` ，[`1636` 行](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/standard/array.c#L1636)，可以搜索 `PHP_FUNCTION(in_array)`
* 定义 in_array 的函数体很简单：

```c
PHP_FUNCTION(in_array)
{
	php_search_array(INTERNAL_FUNCTION_PARAM_PASSTHRU, 0);
}
```

* 在前面的[ is_array 的解析](../is_array.md)中有提到过，`INTERNAL_FUNCTION_PARAM_PASSTHRU` 宏的作用是将调用函数的参数原样传递给此处的 `php_search_array` 函数
* 逻辑的实现，都在 php_search_array 函数中，我们深入其中
* 先跳过 中前面几行的变量声明，看到函数中对参数的处理：

```c
ZEND_PARSE_PARAMETERS_START(2, 3)
    Z_PARAM_ZVAL(value)
    Z_PARAM_ARRAY(array)
    Z_PARAM_OPTIONAL
    Z_PARAM_BOOL(strict)
ZEND_PARSE_PARAMETERS_END();
```

* 按照前面几篇分析的经验，很容易就知道，2个必传参数，最多能传3个参数，`Z_PARAM_BOOL(strict)` 对应于 in_array 函数原型的第三个 `$strict` 可选参数
* 如果 $strict 值为真，会进入循环的分支处理

```c
ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(array), num_idx, str_idx, entry) {
    ZVAL_DEREF(entry);
    if (fast_is_identical_function(value, entry)) {
        if (behavior == 0) {
            RETURN_TRUE;
        } else {
            if (str_idx) {
                RETVAL_STR_COPY(str_idx);
            } else {
                RETVAL_LONG(num_idx);
            }
            return;
        }
    }
} ZEND_HASH_FOREACH_END();
```

* 参数 entry 是指循环过程中当前所指的数组单元
* 循环一开始，先对 entry 进行 ZVAL_DEREF 处理，展开后的语句块如下：

```c
do {								\
    if (UNEXPECTED(Z_ISREF_P(entry))) {					\
        (entry) = Z_REFVAL_P(entry);						\
    }												\
} while (0)
```

* 它的作用是解引用。因为在 PHP 的代码中，foreach 代码中，可以使用形如 `$k => &$v`。因此，这里 ZVAL_DEREF 宏的作用是解引用。
* 接着，判断 `fast_is_identical_function(value, entry)` ，我看看看它的作用是什么

```c
static zend_always_inline int fast_is_identical_function(zval *op1, zval *op2)
{
	if (Z_TYPE_P(op1) != Z_TYPE_P(op2)) {
		return 0;
	} else if (Z_TYPE_P(op1) <= IS_TRUE) {
		return 1;
	}
	return zend_is_identical(op1, op2);
}
```

* 因为 zval 结构体中已经存放了变量对应的类型，并且它是 `unsigned char` 类型
* 因此考虑到性能问题，源码中先对 value 和 entry 进行类型的对比，如果类型相同，则调用 zend_is_identical ，如果不相同，则直接返回 0
* 我们知道， `zend_is_identical` 函数的作用是比较 两个变量的值是否相等的，其源码如下：

```c
ZEND_API int ZEND_FASTCALL zend_is_identical(zval *op1, zval *op2) /* {{{ */
{
	if (Z_TYPE_P(op1) != Z_TYPE_P(op2)) {
		return 0;
	}
	switch (Z_TYPE_P(op1)) {
		case IS_NULL:
		case IS_FALSE:
		case IS_TRUE:
			return 1;
		case IS_LONG:
			return (Z_LVAL_P(op1) == Z_LVAL_P(op2));
		case IS_RESOURCE:
			return (Z_RES_P(op1) == Z_RES_P(op2));
		case IS_DOUBLE:
			return (Z_DVAL_P(op1) == Z_DVAL_P(op2));
		case IS_STRING:
			return zend_string_equals(Z_STR_P(op1), Z_STR_P(op2));
		case IS_ARRAY:
			return (Z_ARRVAL_P(op1) == Z_ARRVAL_P(op2) ||
				zend_hash_compare(Z_ARRVAL_P(op1), Z_ARRVAL_P(op2), (compare_func_t) hash_zval_identical_function, 1) == 0);
		case IS_OBJECT:
			return (Z_OBJ_P(op1) == Z_OBJ_P(op2));
		default:
			return 0;
	}
}
```

* 布尔值，整形值，浮点数，字符串等我们知道可以对内容进行比较，可是对象、数组，资源等类型在这里也是进行了比较操作

### 比较基本类型
* 基本类型的比较，如 IS_LONG，IS_DOUBLE 等，是使用形如 `Z_LVAL_P` 的宏，取出变量中的 `val` 值直接进行比较，因为这些基本类型在 c 语言中是支持比较运算符的

### 复合类型的比较
* 在这里，支持的复合类型有：IS_RESOURCE ， IS_OBJECT ，数组我们放到后面讨论
* 代码中，他们都是使用形如 `Z_OBJ_P/Z_RES_P` 的宏，取出他们的变量地址，进行对比，判断其地址是否一致
* 这里可以得出结论，复合类型的对比，只能针对变量和其引用进行对比操作。比如一个对象，即使他们是同一个类的实例化，但它们地址不同，也就认为它们不相等。

### 数组的比较
* 数组的比较，先进行判断存储数据的地址是否是一致的，如果不是一致的，则进一步使用 `zend_hash_compare` 进行比较，而 zend_hash_compare 中调用了 zend_hash_compare_impl 。

```c
result = zend_hash_compare_impl(ht1, ht2, compar, ordered);
```

* 这个函数实现了对比数组的真正逻辑

### 字符串的比较
* 如果查找的变量 $needle 是字符串，则会调用 `zend_string_equals(Z_STR_P(op1), Z_STR_P(op2));`
* `zend_string_equals` 的定义如下

```c
static zend_always_inline zend_bool zend_string_equals(zend_string *s1, zend_string *s2)
{
	return s1 == s2 || zend_string_equal_content(s1, s2);
}
```

* 先判断是否是同一个地址，如果是，则返回 true ，表示相等
* 如果指针不相等，则调用 zend_string_equal_content 判断字符串是否相等，而这个函数的底层逻辑是调用 

```c
!memcmp(ZSTR_VAL(s1), ZSTR_VAL(s2), ZSTR_LEN(s1));
```

* 众所周知， [memcmp](http://www.runoob.com/cprogramming/c-function-memcmp.html) 是 c 语言标准库中提供的函数，它的作用是
>C 库函数 int memcmp(const void *str1, const void *str2, size_t n)) 把存储区 str1 和存储区 str2 的前 n 个字节进行比较。


## 参考资料
* PHP底层内核源码与扩展开发
    * https://www.kancloud.cn/huqinlou/php_internals_extended_development/428884
