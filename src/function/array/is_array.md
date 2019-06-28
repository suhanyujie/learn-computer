# is_array 函数源码分析
>*本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/array/is_array.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/array/is_array.md* <br>
基于PHP 7.3.3

## php 中的 is_array
* php 中的 [is_array](https://php.net/is_array)，它的签名是 `is_array ( mixed $var ) : bool`

## 实现的源码
* 在 `\ext\standard\type.c` 中可以找到 `PHP_FUNCTION(is_array)` 所处的位置，大概位于 273 行。可以[点击链接](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/standard/type.c#L273)便于查看。
* 在 PHP 中，这个系列的函数，是由很多个，除了它本身之外，还有 is_bool 、 is_countable 、 is_callback 、 is_int 、 is_object 、 is_string 等等
* 在它们之中，大部分的源代码也都是和 is_array 的类似：

```c
PHP_FUNCTION(is_array)
{
	php_is_type(INTERNAL_FUNCTION_PARAM_PASSTHRU, IS_ARRAY);
}
```

* 它的定义很简洁，直接调用了 `php_is_type` ，宏 `INTERNAL_FUNCTION_PARAM_PASSTHRU` 的作用是，将调用 is_array 时的参数，原样传递给 php_is_type 。它的定义如下：

```c
#define INTERNAL_FUNCTION_PARAM_PASSTHRU execute_data, return_value
```

* 函数 php_is_type 的定义如下：

```c
static inline void php_is_type(INTERNAL_FUNCTION_PARAMETERS, int type)
{
	zval *arg;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(arg)
	ZEND_PARSE_PARAMETERS_END_EX(RETURN_FALSE);

	if (Z_TYPE_P(arg) == type) {
		if (type == IS_RESOURCE) {
			const char *type_name = zend_rsrc_list_get_rsrc_type(Z_RES_P(arg));
			if (!type_name) {
				RETURN_FALSE;
			}
		}
		RETURN_TRUE;
	} else {
		RETURN_FALSE;
	}
}
```

* 前面几行是参数解析部分

```c
ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
ZEND_PARSE_PARAMETERS_END_EX(RETURN_FALSE);
```

* 随后通过 `Z_TYPE_P(arg)` 获取变量的类型，再让其结果和 `IS_ARRAY` 判等。如果为真，则表示变量是数组，否则不是。
* Z_TYPE_P 的作用很明显，就是获取变量的类型，这个宏展开后如下：

```c
static zend_always_inline zend_uchar zval_get_type(const zval* pz) {
	return pz->u1.v.type;
}
```

* 其中的 pz ，就是 zval 指针， zval 就是 经常提到的 `_zval_struct`:

```c
struct _zval_struct {
	zend_value        value;			/* 值 */
	union {
		struct {
			ZEND_ENDIAN_LOHI_3(
				zend_uchar    type,			/* 类型 */
				zend_uchar    type_flags,
				union {
					uint16_t  call_info;    /* call info for EX(This) */
					uint16_t  extra;        /* not further specified */
				} u)
		} v;
		uint32_t type_info;
	} u1;
	union {
		uint32_t     next;                 /* hash 碰撞时用到的链表 */
		uint32_t     cache_slot;           /* cache slot (for RECV_INIT) */
		uint32_t     opline_num;           /* opline number (for FAST_CALL) */
		uint32_t     lineno;               /* 行号 (ast 节点中) */
		uint32_t     num_args;             /* 参数数量 for EX(This) */
		uint32_t     fe_pos;               /* foreach 时的所在位置 */
		uint32_t     fe_iter_idx;          /* foreach iterator index */
		uint32_t     access_flags;         /* 类时的访问权限标志位 */
		uint32_t     property_guard;       /* single property guard */
		uint32_t     constant_flags;       /* constant flags */
		uint32_t     extra;                /* 保留字段 */
	} u2;
};
```

* 不做深入介绍了。接续看 `php_is_type`，php_is_type 这个函数会被多种函数调用，例如：is_string、is_resource、is_object... 因此，它需要兼容各种情况。
* 在判断类型时，有个地方比较蹊跷： `if (type == IS_RESOURCE) {`
* 为何这里要判断是否是资源类型？

### 延伸资源类型
* 这里延伸一下，如果用 php_is_type 判断的是资源类型
* 这里会调用 `const char *type_name = zend_rsrc_list_get_rsrc_type(Z_RES_P(arg));`
* 其中有 zend_rsrc_list_get_rsrc_type 的调用，其实现如下：

```c
const char *zend_rsrc_list_get_rsrc_type(zend_resource *res)
{
	zend_rsrc_list_dtors_entry *lde;

	lde = zend_hash_index_find_ptr(&list_destructors, res->type);
	if (lde) {
		return lde->type_name;
	} else {
		return NULL;
	}
}
```

* 有一个叫做 `list_destructors` 的静态变量，它的作用如下
>list_destructors 是一个全局静态 HashTable，资源类型注册时，将一个 zval 结构体变量 zv 存放入 list_destructors 的 arData 中，而 zv 的 value.ptr 却指向了 zend_rsrc_list_dtors_entry *lde ，lde中包含的该种资源释放函数指针、持久资源的释放函数指针，资源类型名称，该资源在 hashtable 中的索引依据 （resource_id）等。   --来源于“[PHP7 使用资源包裹第三方扩展原理分析](https://segmentfault.com/a/1190000010185347)”

* 也就是说，创建了一个资源类型R1时，就会向 `list_destructors` 中存入一份 zend_rsrc_list_dtors_entry ，其中包含了该资源R1的一些信息
* 这里的 `zend_hash_index_find_ptr` 就是找到资源对应的 zend_rsrc_list_dtors_entry ，从而取其中的 `lde->type_name`
* 如果 type 成员是存在的，则说明是资源类型。也就是说，当你用 is_resource 函数判断资源时，会如此执行，type 成员存在，才回返回 true。

## 总结
* PHP 中使用 `is_*` 系列判断类型的函数，大部分都是通过变量底层 zval 中的 `u1.v.type` 来判断类型值
* 如果是资源类型，需要通过 list_destructors 查询对应的资源类型是否存在，如果存在，说明资源句柄是可以正常使用的。

## 参考资料
* https://www.jianshu.com/p/5956b4cfca17
* PHP7扩展开发之依赖其他扩展
    * https://www.bo56.com/php7%E6%89%A9%E5%B1%95%E5%BC%80%E5%8F%91%E4%B9%8B%E4%BE%9D%E8%B5%96%E5%85%B6%E4%BB%96%E6%89%A9%E5%B1%95/
* PHP7 使用资源包裹第三方扩展原理分析
    * https://segmentfault.com/a/1190000010185347
