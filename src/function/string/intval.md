# PHP 源码 — intval 函数源码分析（算法：字符串转换为整形）
>* 文章来源： https://github.com/suhanyujie/learn-computer/
>* 作者：[suhanyujie](https://github.com/suhanyujie)
>* 基于PHP 7.3.3

## PHP 中的 intval
* [intval 函数](https://php.net/intval)的签名从官方文档可见：

```
intval ( mixed $var [, int $base = 10 ] ) : int
```

* 它的作用是将变量转换为整数值。其第二个参数 `$base` 用的不是很多。它代表转化所使用的进制。默认是 10 进制
* 可以通过如下简单示例，了解如何使用它：

```php
$var1 = '123';
$var2 = '-123';
$var3 = [1, 2, ];
$var4 = [-1, 2, ];
var_dump(
    intval($var1),
    intval($var2),
    intval($var3),
    intval($var4)
);
// 输出如下：
// int(-123)
// int(1)
// int(1)
```

* 这个函数不是从 100 个函数中选出来的，而是偶然的在 [LeetCode 刷题](https://leetcode-cn.com/problems/string-to-integer-atoi/)，碰到将字符串转换为数字的算法题中得到的想法，PHP 有 intval，其底层是如何实现的呢？

## intval 实现源码
* 函数 intval 在位于 `php-7.3.3/ext/standard/type.c` 中，可以[点击查看](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/standard/type.c#L88)
* 函数源码不多，直接贴出：

```c
PHP_FUNCTION(intval)
{
	zval *num;
	zend_long base = 10;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ZVAL(num)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(base)
	ZEND_PARSE_PARAMETERS_END();

	if (Z_TYPE_P(num) != IS_STRING || base == 10) {
		RETVAL_LONG(zval_get_long(num));
		return;
	}


	if (base == 0 || base == 2) {
		char *strval = Z_STRVAL_P(num);
		size_t strlen = Z_STRLEN_P(num);

		while (isspace(*strval) && strlen) {
			strval++;
			strlen--;
		}

		/* Length of 3+ covers "0b#" and "-0b" (which results in 0) */
		if (strlen > 2) {
			int offset = 0;
			if (strval[0] == '-' || strval[0] == '+') {
				offset = 1;
			}

			if (strval[offset] == '0' && (strval[offset + 1] == 'b' || strval[offset + 1] == 'B')) {
				char *tmpval;
				strlen -= 2; /* Removing "0b" */
				tmpval = emalloc(strlen + 1);

				/* Place the unary symbol at pos 0 if there was one */
				if (offset) {
					tmpval[0] = strval[0];
				}

				/* Copy the data from after "0b" to the end of the buffer */
				memcpy(tmpval + offset, strval + offset + 2, strlen - offset);
				tmpval[strlen] = 0;

				RETVAL_LONG(ZEND_STRTOL(tmpval, NULL, 2));
				efree(tmpval);
				return;
			}
		}
	}

	RETVAL_LONG(ZEND_STRTOL(Z_STRVAL_P(num), NULL, base));
}
```

* 从PHP 用户态的角度看，intval 函数原型中，输入参数 `$var` 变量类型是 `mixed`，这也就意味着，输入参数可以是 PHP 中的任意一种类型，包括整形、字符串、数组、对象等。因此，在源码中直接使用 zval 接收输入参数 `zval *num;`

### 十进制的情况
* 源码中，大部分的内容是针对非 10 进制的处理。我们先着重看一下 10 进制的情况。对数据转化为 10 进制的整数时，源码所做处理如下：

```c
if (Z_TYPE_P(num) != IS_STRING || base == 10) {
    RETVAL_LONG(zval_get_long(num));
    return;
}

static zend_always_inline zend_long zval_get_long(zval *op) {
	return EXPECTED(Z_TYPE_P(op) == IS_LONG) ? Z_LVAL_P(op) : zval_get_long_func(op);
}

ZEND_API zend_long ZEND_FASTCALL zval_get_long_func(zval *op)
{
	return _zval_get_long_func_ex(op, 1);
}
```

* 只要传入的数据不是整数情况，那么源码中最终会调用 `_zval_get_long_func_ex(op, 1);`。在这个函数中，处理了各种 PHP 用户态参数类型的情况：

```c
switch (Z_TYPE_P(op)) {
	case IS_UNDEF:
	case IS_NULL:
	case IS_FALSE:
		return 0;
	case IS_TRUE:
		return 1;
	case IS_RESOURCE:
		return Z_RES_HANDLE_P(op);
	case IS_LONG:
		return Z_LVAL_P(op);
	case IS_DOUBLE:
		return zend_dval_to_lval(Z_DVAL_P(op));
	case IS_STRING:
		// 略 ……
	case IS_ARRAY:
		return zend_hash_num_elements(Z_ARRVAL_P(op)) ? 1 : 0;
	case IS_OBJECT:
		// 略 ……
	case IS_REFERENCE:
		op = Z_REFVAL_P(op);
		goto try_again;
	EMPTY_SWITCH_DEFAULT_CASE()
}
```

* 通过 switch 语句的不同分支对不同类型做了各种不同的处理：
    - 如果传入的类型是“空”类型，则 intval 函数直接返回 0；
    - 如果是 true，返回 1
    - 如果是数组，空数组时返回 0；非空数组，则返回 1
    - 如果是字符串，则进一步处理
    - ……

* 按照本文的初衷，就是要了解一下如何将字符串转化为整形数据，因此我们着重看字符串的情况：

```
{
	zend_uchar type;
	zend_long lval;
	double dval;
	if (0 == (type = is_numeric_string(Z_STRVAL_P(op), Z_STRLEN_P(op), &lval, &dval, silent ? 1 : -1))) {
		if (!silent) {
			zend_error(E_WARNING, "A non-numeric value encountered");
		}
		return 0;
	} else if (EXPECTED(type == IS_LONG)) {
		return lval;
	} else {
		/* Previously we used strtol here, not is_numeric_string,
		 * and strtol gives you LONG_MAX/_MIN on overflow.
		 * We use use saturating conversion to emulate strtol()'s
		 * behaviour.
		 */
		 return zend_dval_to_lval_cap(dval);
	}
}
```

```c
static zend_always_inline zend_uchar is_numeric_string(const char *str, size_t length, zend_long *lval, double *dval, int allow_errors) {
    return is_numeric_string_ex(str, length, lval, dval, allow_errors, NULL);
}

static zend_always_inline zend_uchar is_numeric_string_ex(const char *str, size_t length, zend_long *lval, double *dval, int allow_errors, int *oflow_info)
{
	if (*str > '9') {
		return 0;
	}
	return _is_numeric_string_ex(str, length, lval, dval, allow_errors, oflow_info);
}

ZEND_API zend_uchar ZEND_FASTCALL _is_numeric_string_ex(const char *str, size_t length, zend_long *lval, double *dval, int allow_errors, int *oflow_info) { // ... }
```

* 而在这段逻辑里，最能体现字符串转整形算法的还是隐藏在 `is_numeric_string(Z_STRVAL_P(op), Z_STRLEN_P(op), &lval, &dval, silent ? 1 : -1)` 背后的函数调用，也就是函数 `_is_numeric_string_ex`
* 对于一段字符串，将其转为整形，我们的规则一般如下：
    - 去除前面的空格字符，包括空格、换行、制表符等
    - 妥善处理字符串前面的 `+/-` 符号
    - 处理靠前的 `'0'` 字符，比如字符串 `'001a'`，转换为整形后，就是 `1`，去除了前面的 `'0'` 字符
    - 处理余下的字符串中前几位是数字字符串的值，并抛弃非数字字符。所谓数字字符，就是 `'0'-'9'` 的字符

#### 空白符号处理
* 源码中的处理如下：

```c
while (*str == ' ' || *str == '\t' || *str == '\n' || *str == '\r' || *str == '\v' || *str == '\f') {
	str++;
	length--;
}
```

* `\n`、`\t`、`\r` 这几个用的多一些。`\v` 是指竖向跳格；`\f` 是换页符。针对这种空白符，不做处理，选择跳过。然后使用指针运算 `str++` 指向下一个字符

#### 正、负号的处理
* 由于正、负号在数值中是有意义的，因此需要保留，但是数值中 `+` 号是可以省略的：

```c
if (*ptr == '-') {
	neg = 1;
	ptr++;
} else if (*ptr == '+') {
	ptr++;
}
```

#### 跳过任意个字符 0
* 因为十进制数值前的 0 值是没有意义的，因此需要跳过：

```c
while (*ptr == '0') {
	ptr++;
}
```

* 处理完以上的 3 种情况后，就会对接下里的字符逐个转换为整数。由于最先遍历到的字符数字是处于高位的，所以在计算下一个字符前，需要对之前的数值 `*10` 操作。举例说明：
	* 对于字符串 `231aa`，遍历到第一个字符 `'2'` 时，将其作为临时值存储到变量 tmp 中
	* 第二次遍历到 `'3'`，需要 `*10`，也就是 `tmp * 10 + 3`，此时 tmp 值为 23
	* 第三次遍历到 `'1'`，需要 `tmp * 10 + 1`，此时 tmp 值为 231。

* 因此，源码中判断字符是否是数字字符：`ZEND_IS_DIGIT(*ptr)`，是的话则按照上述方式计算
>* ZEND_IS_DIGIT 宏的实现是 `((c) >= '0' && (c) <= '9')`，位于 `'0'`和 `'9'` 之间的字符就是我们需要找的数字字符。

#### 小数的情况
* `_is_numeric_string_ex` 函数在底层会被多种 PHP 函数调用，包括 `floatval`。如果在遍历字符串的字符时，遇到小数点该如何处理呢？个人观点看，由于我们要实现的是 `intval` 函数，所以我觉得遇到小数点时，可以将其当作非数字字符来处理。例如 `"3.14abc"` 字符串，intval 之后就直接是 3。然而实际上，`_is_numeric_string_ex` 的实现不是这样的，因为它是一个通用函数。在遇到小数点时，有一些特殊处理：
* 在遇到小数点的情况下，c 会进行 goto 跳转，跳转到 `process_double`：

```c
process_double:
    type = IS_DOUBLE;

    /* If there's a dval, do the conversion; else continue checking
     * the digits if we need to check for a full match */
    if (dval) {
        local_dval = zend_strtod(str, &ptr);
    } else if (allow_errors != 1 && dp_or_e != -1) {
        dp_or_e = (*ptr++ == '.') ? 1 : 2;
        goto check_digits;
    }
```

* `_is_numeric_string_ex` 函数最后会将得到的浮点数返回：

```c
if (dval) {
    *dval = local_dval;
}

return IS_DOUBLE;
```

* 浮点数的值被赋给 `dval` 指针。并将数据标识 `IS_DOUBLE` 返回。
* 随后执行栈跳转回函数 `_zval_get_long_func_ex` 继续执行，也就是 `return zend_dval_to_lval_cap(dval);`。该函数定义如下：

```
static zend_always_inline zend_long zend_dval_to_lval_cap(double d)
{
	if (UNEXPECTED(!zend_finite(d)) || UNEXPECTED(zend_isnan(d))) {
		return 0;
	} else if (!ZEND_DOUBLE_FITS_LONG(d)) {
		return (d > 0 ? ZEND_LONG_MAX : ZEND_LONG_MIN);
	}
	return (zend_long)d;
}
```

* 也就是说，从浮点数到整数，是底层进行了类型强制转换的结果：`(zend_long)d`。

## 结语
* PHP 底层将很多小段逻辑进行了封装，很大程度的提高了代码复用性。但也给源码的维护和学习带来了一些额外的成本。一个类型转换的函数就进行了 10 余种函数调用。
* 下一篇，将进行 intval 底层相关的扩展实践。敬请期待。
* 如果你有更好的想法，欢迎给我提意见和建议。
