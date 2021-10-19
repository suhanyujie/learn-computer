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
* 在大多数场景中，我们不会传递 limit ，因此它的默认值是 `ZEND_LONG_MAX` ，它的值通过 gdb 调试，可以看出是 `9223372036854775807`
* 到这里的执行会走向“大于1”的分支，也就是：

```c
php_explode(delim, str, return_value, limit);
```

* 至此，我们可以将主要精力放到 php_explode 上，来看看它的实现：

```c
PHPAPI void php_explode(const zend_string *delim, zend_string *str, zval *return_value, zend_long limit)
{
	const char *p1 = ZSTR_VAL(str);
	const char *endp = ZSTR_VAL(str) + ZSTR_LEN(str);
	const char *p2 = php_memnstr(ZSTR_VAL(str), ZSTR_VAL(delim), ZSTR_LEN(delim), endp);
	zval  tmp;

	if (p2 == NULL) {
		ZVAL_STR_COPY(&tmp, str);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), &tmp);
	} else {
		do {
			size_t l = p2 - p1;

			if (l == 0) {
				ZVAL_EMPTY_STRING(&tmp);
			} else if (l == 1) {
				ZVAL_INTERNED_STR(&tmp, ZSTR_CHAR((zend_uchar)(*p1)));
			} else {
				ZVAL_STRINGL(&tmp, p1, p2 - p1);
			}
			zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), &tmp);
			p1 = p2 + ZSTR_LEN(delim);
			p2 = php_memnstr(p1, ZSTR_VAL(delim), ZSTR_LEN(delim), endp);
		} while (p2 != NULL && --limit > 1);

		if (p1 <= endp) {
			ZVAL_STRINGL(&tmp, p1, endp - p1);
			zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), &tmp);
		}
	}
}
```

* 大致看了一下，这个函数中，最关键的要属 php_memnstr 的调用，它是一个宏，展开后的内容如下：

```c
static zend_always_inline const char *
zend_memnstr(const char *haystack, const char *needle, size_t needle_len, const char *end)
{
  // 原字符串
	const char *p = haystack;
  // 分隔符字符串最后一个字符
	const char ne = needle[needle_len-1];
	ptrdiff_t off_p;
	size_t off_s;
  // 参数 end 表示原字符串的尾指针
  // (end-p) 的意义是原字符串的长度
  // 如果分隔符字符串的长度为 1 ，则跳过分隔符本身，
	if (needle_len == 1) {
		return (const char *)memchr(p, *needle, (end-p));
	}

  // off_p 的值是待查询字符串的长度
	off_p = end - haystack;
	off_s = (off_p > 0) ? (size_t)off_p : 0;

	if (needle_len > off_s) {
		return NULL;
	}

	if (EXPECTED(off_s < 1024 || needle_len < 9)) {	/* glibc memchr is faster when needle is too short */
		end -= needle_len;

		while (p <= end) {
			if ((p = (const char *)memchr(p, *needle, (end-p+1))) && ne == p[needle_len-1]) {
				if (!memcmp(needle+1, p+1, needle_len-2)) {
					return p;
				}
			}

			if (p == NULL) {
				return NULL;
			}

			p++;
		}

		return NULL;
	} else {
		return zend_memnstr_ex(haystack, needle, needle_len, end);
	}
}
```

* zend_memnstr 的作用是查找分隔符 needle 的位置
* 要看懂这个函数，需要先了解一下 c 函数 memchr 的作用。[点此查看](https://www.tutorialspoint.com/c_standard_library/c_function_memchr.htm)
* 它的描述翻译大意如下：
>函数 `void *memchr(const void *str, int c, size_t n)` 搜索参数 str 指向的字符串的前 n 个字节中字符 c （类型是 unsigned char ）第一次出现的位置

* 只有当 needle 长度为 1 是正好可以使用这个函数。那么如果 needle 的长度大于 1 ，怎么办呢？
* 这部分的逻辑可以在这段代码中提现出来：

```c
if ((p = (const char *)memchr(p, *needle, (end-p+1))) && ne == p[needle_len-1]) {
  // 比较位于第一位和最后一位之间的字符是否一致
	if (!memcmp(needle+1, p+1, needle_len-2)) {
		return p;
	}
}
```

* 用文字描述就是：多字符分隔符时，先查找第一位的分隔符，如果查到，则比较位于分隔符最后一位的字符是否和字符串中对应位置相同，如果还是相同，则比较位于第一位和最后一位之间的字符是否一致。如果一致，则返回这一段字符的首指针。

## 其他

```c
ZVAL_STR_COPY(&tmp, str);// 这里将 str 的内容拷贝到 tmp 中
zend_hash_index_add_new(Z_ARRVAL_P(return_value), 0, &tmp);
```

## 参考资料
* https://blog.csdn.net/nituizi2012/article/details/7406746
* PHP 相关代码搜索 https://lxr.room11.org/
