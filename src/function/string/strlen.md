# PHP 源码 — implode 函数源码分析
>* 本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/strlen.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/strlen.md* 
>* 作者：[suhanyujie](https://github.com/suhanyujie)
>* 基于PHP 7.3.3

## PHP 中的 strlen
* strlen 函数的签名从官方文档可见：

```php
strlen ( string $string ) : int
```

* 其作用是：获取字符串长度。通过简单的 PHP 脚本可以看出：

```php
$str = "hello world";
$len =strlen($str);
var_dump($len);
// 输出 int(11)
```

* 本文将通过探究其背后的源码，看看 strlen 在 c 语言层面是如何实现的。

## strlen 源码
### 源码位置
* 函数 strlen 在源码中，不像 explode、implode、count 等函数位于 ext/standard 目录的源代码中，它位于 php-7.3.3/Zend/zend_builtin_functions.c 中，可以[点击查看](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/Zend/zend_builtin_functions.c#L577)
* 其定义也很简洁：

```c
ZEND_FUNCTION(strlen)
{
	zend_string *s;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(s)
	ZEND_PARSE_PARAMETERS_END();

	RETVAL_LONG(ZSTR_LEN(s));
}
```

* 当贴出上方这段源码的时候，我发现可以本文可以结束了。是的，获取一个字符串的长度，只需要通过宏来获取 `ZSTR_LEN(s)`
* 为了能让本文篇幅更长一些，我不得不拿出一些手段了：
    * 分析一下宏 ZSTR_LEN
    * 分析一下跟 strlen 类似的 mb_strlen
    * 通过编写扩展函数来学习。该函数传入一个一维数组，返回一个数组，对应原数组所在的单元字符长度。

### 宏 ZSTR_LEN
* 这个宏定义如下：`#define ZSTR_LEN(zstr)  (zstr)->len`，可见字符串长度值直接就是 zstr 变量的 len 成员。
* zstr 是一个 zend_string 结构体指针变量。zend_string 的结构如下：

```c
struct _zend_string {
	zend_refcounted_h gc;
	zend_ulong        h;                /* hash value */
	size_t            len;
	char              val[1];
};
```

* 在这里返回指针对应的结构体成员使用：`zstr->len`，它等价于：`(*zstr).len`。`.` 运算符的优先级高于 `*`

### 类似的 mb_strlen
* mb_strlen 函数的实现位于 php-7.3.3/ext/mbstring/mbstring.c 中的 [2287 行](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/mbstring/mbstring.c#L2287)
* 在 PHP 中，它的函数签名是：`mb_strlen ( string $str [, string $encoding = mb_internal_encoding() ] ) : mixed`
* 可以看到，最多传 2 个参数。第二个可选参数 encoding 为字符编码。mb_strlen 源码如下：

```c
PHP_FUNCTION(mb_strlen)
{
	size_t n;
	mbfl_string string;
	char *str, *enc_name = NULL;
	size_t str_len, enc_name_len;

	mbfl_string_init(&string);

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_STRING(str, str_len)
		Z_PARAM_OPTIONAL
		Z_PARAM_STRING(enc_name, enc_name_len)
	ZEND_PARSE_PARAMETERS_END();

	string.val = (unsigned char *) str;
	string.len = str_len;
	string.no_language = MBSTRG(language);
	string.encoding = php_mb_get_encoding(enc_name);
	if (!string.encoding) {
		RETURN_FALSE;
	}

	n = mbfl_strlen(&string);
	if (!mbfl_is_error(n)) {
		RETVAL_LONG(n);
	} else {
		RETVAL_FALSE;
	}
}
```

* encoding 参数传入后，被转化为 enc_name，随后通过函数进行处理：`string.encoding = php_mb_get_encoding(enc_name);`，主要作用就是通过 enc_name 映射为对应的 encoding。我们可以通过了解 php_mb_get_encoding 函数看看 PHP 底层支持哪些编码。而在这个函数内部，通过这样的调用获取 encoding：`encoding = mbfl_name2encoding(encoding_name);`，这个函数中，通过在一个固定的列表中查找，是否有匹配的，如果没有匹配，则使用系统默认的编码。这个固定的列表的变量名是：`mbfl_encoding_ptr_list`：

```c
static const mbfl_encoding *mbfl_encoding_ptr_list[] = {
	&mbfl_encoding_pass,
	&mbfl_encoding_wchar,
	&mbfl_encoding_byte2be,
	// ...
	&mbfl_encoding_html_ent,
	&mbfl_encoding_qprint,
	&mbfl_encoding_7bit,
	&mbfl_encoding_8bit,
	&mbfl_encoding_ucs4,
	&mbfl_encoding_ucs4be,
	// ...
	NULL
};
```

* 列表中的每个元素是 mbfl_encoding 类型：

```c
typedef struct _mbfl_encoding {
	enum mbfl_no_encoding no_encoding;
	const char *name;
	const char *mime_name;
	const char *(*aliases)[];
	const unsigned char *mblen_table;
	unsigned int flag;
	const struct mbfl_convert_vtbl *input_filter;
	const struct mbfl_convert_vtbl *output_filter;
} mbfl_encoding;
```

* 我们通过一段 PHP 脚本，看看 mb_strlen 是如何定位编码的：

```php
$str = "who-are-you...一";
$res = mb_strlen($str, 'GBK');
var_dump($res);
// 输出 int(16)
```

* 前面提到，底层会通过:`encoding = mbfl_name2encoding(encoding_name);` 定位编码。该函数通过 3 段循环逐个寻找对应的的编码方式：

```c
const mbfl_encoding *
mbfl_name2encoding(const char *name)
{
	// ...
 	i = 0;
 	while ((encoding = mbfl_encoding_ptr_list[i++]) != NULL){
		if (strcasecmp(encoding->name, name) == 0) {
			return encoding;
		}
	}

 	/* serch MIME charset name */
 	i = 0;
 	while ((encoding = mbfl_encoding_ptr_list[i++]) != NULL) {
		if (encoding->mime_name != NULL) {
			if (strcasecmp(encoding->mime_name, name) == 0) {
				return encoding;
			}
		}
	}

 	/* serch aliases */
 	i = 0;
 	while ((encoding = mbfl_encoding_ptr_list[i++]) != NULL) {
		if (encoding->aliases != NULL) {
 			j = 0;
 			while ((*encoding->aliases)[j] != NULL) {
				if (strcasecmp((*encoding->aliases)[j], name) == 0) {
					return encoding;
				}
				j++;
			}
		}
	}

	return NULL;
}
```

* 针对定义好的编码方式的 encoding->name，encoding->mime_name，encoding->aliases 一一作对比，直至找到匹配的为止，若未找到，则会返回 NULL
* 定义的编码方式有很多种，位于目录 php-7.3.3/ext/mbstring/libmbfl/filters 下，有 63 个大类。这里的 3 个 while 循环，感觉效率低下。后面会将将其优化的实践作为练习。
* 上面的 PHP 脚本中，在底层会寻找 GBK 对应的编码方式，其最终的结局是找到了对应的类型，它位于 `php-7.3.3/ext/mbstring/libmbfl/filters/mbfilter_cp936.c` 文件中，存在于 mbfl_encoding 类型的 aliases 数组中，其值的定义为：`static const char *mbfl_encoding_cp936_aliases[] = {"CP-936", "GBK", NULL};`
* 寻找到对应的 encoding 之后，就是计算字符串的长度了：`n = mbfl_strlen(&string);`
* 该函数中，通过预定义好的 mblen_table 来获取对应的字符长度，从而推算出字符串的总长度：

```c
else if (encoding->mblen_table != NULL) {
    const unsigned char *mbtab = encoding->mblen_table;
    n = 0;
    p = string->val;
    k = string->len;
    /* count */
    if (p != NULL) {
        while (n < k) {
            unsigned m = mbtab[*p];
            n += m;
            p += m;
            len++;
        }
    }
}
```

* 至此，字符串长度才算计算完成。

## 实践
* 前面提到过，使用 mb_strlen 的效率可能会因为寻找对应的字符编码方式而下降，那么这次的实践，就通过写扩展函数来优化 mb_strlen，并且我们优化的点就是前面提到的三段 while 循环。
* 在这个扩展函数中，我们将通过一个 HashTable 来缓存编码名称到对应编码的映射。

### 依赖其他扩展
* 如果开发扩展函数时，你需要依赖其他的扩展，可以参考一下信海龙老师的[ php7 扩展开发之依赖其他扩展](https://www.bo56.com/php7%e6%89%a9%e5%b1%95%e5%bc%80%e5%8f%91%e4%b9%8b%e4%be%9d%e8%b5%96%e5%85%b6%e4%bb%96%e6%89%a9%e5%b1%95/)
* zend_module_entry 的调整：

```c
// 调整前
zend_module_entry s2_module_entry = {
	STANDARD_MODULE_HEADER, 
	"s2",					/* Extension name */
	s2_functions,			/* zend_function_entry */
	// ...
};
// 调整后
static const  zend_module_dep s2_deps[] = {
    ZEND_MOD_REQUIRED("mbstring")
    ZEND_MOD_END
};
zend_module_entry s2_module_entry = {
	STANDARD_MODULE_HEADER_EX, NULL,
	s2_deps,
	"s2",					/* Extension name */
	s2_functions,			/* zend_function_entry */
	// ...
};
```


## 总结
* 通过寻找 mbstring 扩展中的 GBK 编码方式，了解到 GBK 的别名是 CP936，因为分配给 GBK 编码集的页是第 936 页。


## 参考资料
* PHP7扩展开发之依赖其他扩展 https://blog.csdn.net/u013474436/article/details/79029538
