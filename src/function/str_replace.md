## PHP的函数 `str_replace`
* 查看源码，位于 `/php7.2.7/ext/standard/string.c` ，如下：
```php
/* {{{ php_str_replace_common
 */
static void php_str_replace_common(INTERNAL_FUNCTION_PARAMETERS, int case_sensitivity)
{
	zval *subject, *search, *replace, *subject_entry, *zcount = NULL;
	zval result;
	zend_string *string_key;
	zend_ulong num_key;
	zend_long count = 0;
	int argc = ZEND_NUM_ARGS();

	ZEND_PARSE_PARAMETERS_START(3, 4)
		Z_PARAM_ZVAL(search)
		Z_PARAM_ZVAL(replace)
		Z_PARAM_ZVAL(subject)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL_DEREF(zcount)
	ZEND_PARSE_PARAMETERS_END();

	/* Make sure we're dealing with strings and do the replacement. */
	if (Z_TYPE_P(search) != IS_ARRAY) {
		convert_to_string_ex(search);
		if (Z_TYPE_P(replace) != IS_STRING) {
			convert_to_string_ex(replace);
		}
	} else if (Z_TYPE_P(replace) != IS_ARRAY) {
		convert_to_string_ex(replace);
	}

	/* if subject is an array */
	if (Z_TYPE_P(subject) == IS_ARRAY) {
		array_init(return_value);

		/* For each subject entry, convert it to string, then perform replacement
		   and add the result to the return_value array. */
		ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(subject), num_key, string_key, subject_entry) {
			ZVAL_DEREF(subject_entry);
			if (Z_TYPE_P(subject_entry) != IS_ARRAY && Z_TYPE_P(subject_entry) != IS_OBJECT) {
				count += php_str_replace_in_subject(search, replace, subject_entry, &result, case_sensitivity);
			} else {
				ZVAL_COPY(&result, subject_entry);
			}
			/* Add to return array */
			if (string_key) {
				zend_hash_add_new(Z_ARRVAL_P(return_value), string_key, &result);
			} else {
				zend_hash_index_add_new(Z_ARRVAL_P(return_value), num_key, &result);
			}
		} ZEND_HASH_FOREACH_END();
	} else {	/* if subject is not an array */
		count = php_str_replace_in_subject(search, replace, subject, return_value, case_sensitivity);
	}
	if (argc > 3) {
		zval_ptr_dtor(zcount);
		ZVAL_LONG(zcount, count);
	}
}
```

* 在函数内部，它判断了search参数是否是数组：`Z_TYPE_P(search) != IS_ARRAY`
* 不是数组时，使用了一个宏 `convert_to_string_ex`
* 这个宏，再调用宏：`convert_to_ex_master`，它的源码如下：

```html
#define convert_to_ex_master(pzv, lower_type, upper_type)	\
	if (Z_TYPE_P(pzv)!=upper_type) {					\
		convert_to_##lower_type(pzv);						\
	}
```

* 这里使用了[宏拼接符](https://www.cnblogs.com/muzinian/archive/2012/11/25/2787929.html) `convert_to_##lower_type`
* 最终调用的是：`convert_to_string(pzv)`
* 其定义如下：`#define convert_to_string(op) if (Z_TYPE_P(op) != IS_STRING) { _convert_to_string((op) ZEND_FILE_LINE_CC); }`
* 到了这里，只是做一件事，想办法将变量转换为字符串：

```
ZEND_API void ZEND_FASTCALL _convert_to_string(zval *op ZEND_FILE_LINE_DC) /* {{{ */
{
try_again:
	switch (Z_TYPE_P(op)) {
		case IS_UNDEF:
		case IS_NULL:
		case IS_FALSE: {
			ZVAL_EMPTY_STRING(op);
			break;
		}
		case IS_TRUE:
			ZVAL_INTERNED_STR(op, ZSTR_CHAR('1'));
			break;
		case IS_STRING:
			break;
		case IS_RESOURCE: {
			char buf[sizeof("Resource id #") + MAX_LENGTH_OF_LONG];
			int len = snprintf(buf, sizeof(buf), "Resource id #" ZEND_LONG_FMT, (zend_long)Z_RES_HANDLE_P(op));
			zval_ptr_dtor(op);
			ZVAL_NEW_STR(op, zend_string_init(buf, len, 0));
			break;
		}
		case IS_LONG: {
			ZVAL_STR(op, zend_long_to_str(Z_LVAL_P(op)));
			break;
		}
		case IS_DOUBLE: {
			zend_string *str;
			double dval = Z_DVAL_P(op);

			str = zend_strpprintf(0, "%.*G", (int) EG(precision), dval);
			/* %G already handles removing trailing zeros from the fractional part, yay */
			ZVAL_NEW_STR(op, str);
			break;
		}
		case IS_ARRAY:
			zend_error(E_NOTICE, "Array to string conversion");
			zval_ptr_dtor(op);
			ZVAL_NEW_STR(op, zend_string_init("Array", sizeof("Array")-1, 0));
			break;
		case IS_OBJECT: {
			zval dst;

			convert_object_to_type(op, &dst, IS_STRING, convert_to_string);
			zval_dtor(op);

			if (Z_TYPE(dst) == IS_STRING) {
				ZVAL_COPY_VALUE(op, &dst);
			} else {
				ZVAL_NEW_STR(op, zend_string_init("Object", sizeof("Object")-1, 0));
			}
			break;
		}
		case IS_REFERENCE:
			zend_unwrap_reference(op);
			goto try_again;
		EMPTY_SWITCH_DEFAULT_CASE()
	}
}
```

* 在这里，就其中变量为数组的情况为例 ：

```html
case IS_ARRAY:
			zend_error(E_NOTICE, "Array to string conversion");
			zval_ptr_dtor(op);
			ZVAL_NEW_STR(op, zend_string_init("Array", sizeof("Array")-1, 0));
			break;
```

* 可以看到，源码的做法是直接进行抛出异常信息："Array to string conversion"
* 其中，有一个关键的函数是 `php_str_replace_in_subject`，当replace参数和search参数都是数组时，会直接调用这个函数，其源码如下：

```c
/* {{{ php_str_replace_in_subject
 */
static zend_long php_str_replace_in_subject(zval *search, zval *replace, zval *subject, zval *result, int case_sensitivity)
{
	zval		*search_entry,
				*replace_entry = NULL;
	zend_string	*tmp_result,
				*replace_entry_str = NULL;
	char		*replace_value = NULL;
	size_t		 replace_len = 0;
	zend_long	 replace_count = 0;
	zend_string	*subject_str;
	zend_string *lc_subject_str = NULL;
	uint32_t     replace_idx;

	/* Make sure we're dealing with strings. */
	subject_str = zval_get_string(subject);
	if (ZSTR_LEN(subject_str) == 0) {
		zend_string_release(subject_str);
		ZVAL_EMPTY_STRING(result);
		return 0;
	}
    //如果search是数组，则走这个分支
	/* If search is an array */
	if (Z_TYPE_P(search) == IS_ARRAY) {
		/* Duplicate subject string for repeated replacement */
		ZVAL_STR_COPY(result, subject_str);

		if (Z_TYPE_P(replace) == IS_ARRAY) {
			replace_idx = 0;
		} else {
			/* Set replacement value to the passed one */
			replace_value = Z_STRVAL_P(replace);
			replace_len = Z_STRLEN_P(replace);
		}
        //遍历search数组中需要被替换的字符串
		/* For each entry in the search array, get the entry */
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(search), search_entry) {
			/* Make sure we're dealing with strings. */
			zend_string *search_str = zval_get_string(search_entry);
			if (ZSTR_LEN(search_str) == 0) {
				if (Z_TYPE_P(replace) == IS_ARRAY) {
					replace_idx++;
				}
				zend_string_release(search_str);
				continue;
			}

			/* If replace is an array. */
			if (Z_TYPE_P(replace) == IS_ARRAY) {
				/* Get current entry */
				while (replace_idx < Z_ARRVAL_P(replace)->nNumUsed) {
					replace_entry = &Z_ARRVAL_P(replace)->arData[replace_idx].val;
					if (Z_TYPE_P(replace_entry) != IS_UNDEF) {
						break;
					}
					replace_idx++;
				}
				if (replace_idx < Z_ARRVAL_P(replace)->nNumUsed) {
					/* Make sure we're dealing with strings. */
					replace_entry_str = zval_get_string(replace_entry);

					/* Set replacement value to the one we got from array */
					replace_value = ZSTR_VAL(replace_entry_str);
					replace_len = ZSTR_LEN(replace_entry_str);

					replace_idx++;
				} else {
					/* We've run out of replacement strings, so use an empty one. */
					replace_value = "";
					replace_len = 0;
				}
			}

			if (ZSTR_LEN(search_str) == 1) {
				zend_long old_replace_count = replace_count;

				tmp_result = php_char_to_str_ex(Z_STR_P(result),
								ZSTR_VAL(search_str)[0],
								replace_value,
								replace_len,
								case_sensitivity,
								&replace_count);
				if (lc_subject_str && replace_count != old_replace_count) {
					zend_string_release(lc_subject_str);
					lc_subject_str = NULL;
				}
			} else if (ZSTR_LEN(search_str) > 1) {
				if (case_sensitivity) {
					tmp_result = php_str_to_str_ex(Z_STR_P(result),
							ZSTR_VAL(search_str), ZSTR_LEN(search_str),
							replace_value, replace_len, &replace_count);
				} else {
					zend_long old_replace_count = replace_count;

					if (!lc_subject_str) {
						lc_subject_str = php_string_tolower(Z_STR_P(result));
					}
					tmp_result = php_str_to_str_i_ex(Z_STR_P(result), ZSTR_VAL(lc_subject_str),
							search_str, replace_value, replace_len, &replace_count);
					if (replace_count != old_replace_count) {
						zend_string_release(lc_subject_str);
						lc_subject_str = NULL;
					}
				}
			}

			zend_string_release(search_str);

			if (replace_entry_str) {
				zend_string_release(replace_entry_str);
				replace_entry_str = NULL;
			}
			zend_string_release(Z_STR_P(result));
			ZVAL_STR(result, tmp_result);

			if (Z_STRLEN_P(result) == 0) {
				if (lc_subject_str) {
					zend_string_release(lc_subject_str);
				}
				zend_string_release(subject_str);
				return replace_count;
			}
		} ZEND_HASH_FOREACH_END();
		if (lc_subject_str) {
			zend_string_release(lc_subject_str);
		}
	} else {
		ZEND_ASSERT(Z_TYPE_P(search) == IS_STRING);
		if (Z_STRLEN_P(search) == 1) {
			ZVAL_STR(result,
				php_char_to_str_ex(subject_str,
							Z_STRVAL_P(search)[0],
							Z_STRVAL_P(replace),
							Z_STRLEN_P(replace),
							case_sensitivity,
							&replace_count));
		} else if (Z_STRLEN_P(search) > 1) {
			if (case_sensitivity) {
				ZVAL_STR(result, php_str_to_str_ex(subject_str,
						Z_STRVAL_P(search), Z_STRLEN_P(search),
						Z_STRVAL_P(replace), Z_STRLEN_P(replace), &replace_count));
			} else {
				lc_subject_str = php_string_tolower(subject_str);
				ZVAL_STR(result, php_str_to_str_i_ex(subject_str, ZSTR_VAL(lc_subject_str),
						Z_STR_P(search),
						Z_STRVAL_P(replace), Z_STRLEN_P(replace), &replace_count));
				zend_string_release(lc_subject_str);
			}
		} else {
			ZVAL_STR_COPY(result, subject_str);
		}
	}
	zend_string_release(subject_str);
	return replace_count;
}
``` 

* 判断search是数组，`Z_TYPE_P(search) == IS_ARRAY`，接着调用了一个宏`ZVAL_STR_COPY`，它的定义如下：

```html
#define ZVAL_STR_COPY(z, s) do {						\
		zval *__z = (z);								\
		zend_string *__s = (s);							\
		Z_STR_P(__z) = __s;								\
		/* interned strings support */					\
		if (ZSTR_IS_INTERNED(__s)) {							\
			Z_TYPE_INFO_P(__z) = IS_INTERNED_STRING_EX;	\
		} else {										\
			GC_REFCOUNT(__s)++;							\
			Z_TYPE_INFO_P(__z) = IS_STRING_EX;			\
		}												\
	} while (0)
```

* 它的作用，在一个ebook上看到的
>ZVAL_STR_COPY(z, s): 将s拷贝到z的value,s类型为zend_string*,同ZVAL_STR(z, s),这里会增加s的refcount <br>
链接地址是 https://www.kancloud.cn/huqinlou/php_internals_extended_development/428883

* 通过上方的 `php_str_replace_in_subject` 的源代码 `ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(search), search_entry)`，当search和replace参数都是数组时，会遍历其中的每个单元，逐个进行替换。
* 在这个循环体内，针对每个searchEntry，将其处理成字符串（zend_string指针类型），此时再处理replace参数，根据search参数的索引search_idx，<br> 
取出replace对应索引search_idx上的值，如果 replace 对应索引 search_idx 上的值不存在，则将此时的 replaceEntry 视为空字符串
* 那么此时，searchEntry 和 replaceEntry 都已经准备就绪了，判断一下 searchEntry 的长度
* 如果长度为1，调用如下函数：
    
```html
tmp_result = php_char_to_str_ex(Z_STR_P(result),
            ZSTR_VAL(search_str)[0],
            replace_value,
            replace_len,
            case_sensitivity,
            &replace_count);
```

* 其中的参数 `Z_STR_P(result)` ，是待替换的字符串，`replace_value` 是替换的目标字符串
* 现在，我们锁定了 `php_char_to_str_ex` 函数。它的实际实现如下：

```html
/* {{{ php_char_to_str_ex
 */
static zend_string* php_char_to_str_ex(zend_string *str, char from, char *to, size_t to_len, int case_sensitivity, zend_long *replace_count)
{
	zend_string *result;
	size_t char_count = 0;
	char lc_from = 0;
	char *source, *target, *source_end= ZSTR_VAL(str) + ZSTR_LEN(str);

	if (case_sensitivity) {
		char *p = ZSTR_VAL(str), *e = p + ZSTR_LEN(str);
		//计算需要替换几次。
		//memchr的作用是：从 p 所指内存区域的前 (e - p) 个字节查找字符 from。当第一次遇到字符from时停止查找，
		//如果成功，返回指向字符from的指针；否则返回NULL
		while ((p = memchr(p, from, (e - p)))) {
			char_count++;
			p++;
		}
	} else {
		lc_from = tolower(from);
		for (source = ZSTR_VAL(str); source < source_end; source++) {
			if (tolower(*source) == lc_from) {
				char_count++;
			}
		}
	}

	if (char_count == 0) {
		return zend_string_copy(str);
	}

	if (to_len > 0) {
		result = zend_string_safe_alloc(char_count, to_len - 1, ZSTR_LEN(str), 0);
	} else {
		result = zend_string_alloc(ZSTR_LEN(str) - char_count, 0);
	}
	target = ZSTR_VAL(result);

	if (case_sensitivity) {
		char *p = ZSTR_VAL(str), *e = p + ZSTR_LEN(str), *s = ZSTR_VAL(str);
		while ((p = memchr(p, from, (e - p)))) {
			memcpy(target, s, (p - s));
			target += p - s;
			memcpy(target, to, to_len);
			target += to_len;
			p++;
			s = p;
			if (replace_count) {
				*replace_count += 1;
			}
		}
		if (s < e) {
			memcpy(target, s, (e - s));
			target += e - s;
		}
	} else {
		for (source = ZSTR_VAL(str); source < source_end; source++) {
			if (tolower(*source) == lc_from) {
				if (replace_count) {
					*replace_count += 1;
				}
				memcpy(target, to, to_len);
				target += to_len;
			} else {
				*target = *source;
				target++;
			}
		}
	}
	*target = 0;
	return result;
}
/* }}} */
```

* c语言的一些函数可以[]查看这儿](http://www.kuqin.com/clib/)

