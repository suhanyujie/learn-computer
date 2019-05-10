# count 函数源码分析
* 本文首发于 
* 基于PHP 7.3.3
* 由于不了解PHP的源码，用工具搜索了半天 `count` ，这个关键字的结果太多，挨个看了一遍都没看到 count 实现位置。
* 去百度了一下，通过其中实现体中的 php_count_recursive 关键字，才找到 count 的实现。
* 位于文件 `ext/standard/array.c` 中 776 行，搜索关键字 `PHP_FUNCTION(count)` 即可搜索到。
* 实现源码如下：

```c
PHP_FUNCTION(count)
{
	zval *array;
	zend_long mode = COUNT_NORMAL;
	zend_long cnt;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ZVAL(array)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(mode)
	ZEND_PARSE_PARAMETERS_END();

	switch (Z_TYPE_P(array)) {
		case IS_NULL:
			php_error_docref(NULL, E_WARNING, "Parameter must be an array or an object that implements Countable");
			RETURN_LONG(0);
			break;
		case IS_ARRAY:
			if (mode != COUNT_RECURSIVE) {
				cnt = zend_array_count(Z_ARRVAL_P(array));
			} else {
				cnt = php_count_recursive(Z_ARRVAL_P(array));
			}
			RETURN_LONG(cnt);
			break;
		case IS_OBJECT: {
			zval retval;
			/* first, we check if the handler is defined */
			if (Z_OBJ_HT_P(array)->count_elements) {
				RETVAL_LONG(1);
				if (SUCCESS == Z_OBJ_HT(*array)->count_elements(array, &Z_LVAL_P(return_value))) {
					return;
				}
			}
			/* if not and the object implements Countable we call its count() method */
			if (instanceof_function(Z_OBJCE_P(array), zend_ce_countable)) {
				zend_call_method_with_0_params(array, NULL, NULL, "count", &retval);
				if (Z_TYPE(retval) != IS_UNDEF) {
					RETVAL_LONG(zval_get_long(&retval));
					zval_ptr_dtor(&retval);
				}
				return;
			}

			/* If There's no handler and it doesn't implement Countable then add a warning */
			php_error_docref(NULL, E_WARNING, "Parameter must be an array or an object that implements Countable");
			RETURN_LONG(1);
			break;
		}
		default:
			php_error_docref(NULL, E_WARNING, "Parameter must be an array or an object that implements Countable");
			RETURN_LONG(1);
			break;
	}
}
```

## part 1 参数处理
* 先看第一部分：

```c
ZEND_PARSE_PARAMETERS_START(1, 2)
    Z_PARAM_ZVAL(array)
    Z_PARAM_OPTIONAL
    Z_PARAM_LONG(mode)
ZEND_PARSE_PARAMETERS_END();
```

* 在旧版的PHP中，获取参数的写法是 `(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z|l", &array, &mode) == FAILURE)` ,但在 7.3 的写法中，使用的是FAST ZPP方式，也就是 `ZEND_PARSE_PARAMETERS_*` 相关的宏
* 参数部分 `(1, 2)` ，第1个参数表示最少参数时的参数个数，这里的 `1` 表示调用 count 时，最少要有1个参数。第2个参数表示，参数最多时的参数个数，这里的 `2` 表示最多有2个参数。

## part 2 类型匹配
* 使用 `switch` 匹配传入的参数的类型
* 可以看出，只有当参数1是数组或者对象类型时，才回执行正常的逻辑

### 参数是数组时

```c
if (mode != COUNT_RECURSIVE) {
    cnt = zend_array_count(Z_ARRVAL_P(array));
} else {
    cnt = php_count_recursive(Z_ARRVAL_P(array));
}
RETURN_LONG(cnt);
```

* 在不进行递归计算元素数量的情况下，最后调用的是 `(ht)->nNumOfElements` ，也就是返回数组变量对应的结构体成员 `nNumOfElements`
* 在进行递归统计的情况下，底层会递归调用 `php_count_recursive` 函数，进行统计单元数量。
* 在 `zend_array_count` 中

```c
uint32_t num;
if (UNEXPECTED(HT_FLAGS(ht) & HASH_FLAG_HAS_EMPTY_IND)) {
    num = zend_array_recalc_elements(ht);
    if (UNEXPECTED(ht->nNumOfElements == num)) {
        HT_FLAGS(ht) &= ~HASH_FLAG_HAS_EMPTY_IND;
    }
}...
...
```

* 其中的这一段逻辑是处理特殊情况下的元素数量统计，针对其中的 `HASH_FLAG_HAS_EMPTY_IND` ，它定义是 `#define HASH_FLAG_HAS_EMPTY_IND (1<<5)`
* google 查看了一下内核相关文档，有一下介绍
>This flag is set when a HashTable needs its element count to be recalculated. One hash table where this always needs to be performed is the executor globals symbol table (for the $GLOBALS PHP array). This is because this hash table holds elements of type IS_INDIRECT, which means the values they point to could be unset (see IS_UNDEF). The only way to get the true element count of such a hash table is to iterate through all of its elements and check specifically for this condition.

* 大意是：当哈希表需要重新计算其元素时设置这个标志位。全局的符号表（PHP中的 `$GLOBALS` 数组）就是一个经常要执行这个操作的哈希表。这是因为这个哈希表包含 `IS_INDIRECT` 类型的元素，这意味着它们指向的值会被 `unset` （查阅 IS_UNDEF）。获取这类哈希表的真正元素计数的方法是遍历它的所有元素并专门检查这个这个标志位。
* 当你 unset 一个数组单元之后，并且 gc 尚未对其进行回收，导致单元从某种意义上还是存在，只是其标志位对其标识 unset ，此时进行 count 操作，需要去除这些数组单元。

### 参数是对象时
* 先判断检查对象是否定义了 handler 。 `Z_OBJ_HT_P(array)->count_elements`
* `Z_OBJ_HT_P(array)` 的作用是返回对象中的 value 的 `handler table`
* `count_elements` 是对象相关结构体 `_zend_object_handlers` 中的一个成员
* handler table 的定义中，它被定义为底层的行为。
* 根据 [php 官方文档](https://wiki.php.net/internals/engine/objects#the_handler_table)，在引入 zend 标准对象之后，它们默认有以下这些项：

```c
typedef struct _zend_object_handlers {
    /* general object functions */
    zend_object_add_ref_t              add_ref;
    zend_object_del_ref_t              del_ref;
    zend_object_clone_obj_t            clone_obj;
    /* individual object functions */
    zend_object_read_property_t        read_property;
    zend_object_write_property_t       write_property;
    zend_object_read_dimension_t       read_dimension;
    zend_object_write_dimension_t      write_dimension;
    zend_object_get_property_ptr_ptr_t get_property_ptr_ptr;
    zend_object_get_t                  get;
    zend_object_set_t                  set;
    zend_object_has_property_t         has_property;
    zend_object_unset_property_t       unset_property;
    zend_object_has_dimension_t        has_dimension;
    zend_object_unset_dimension_t      unset_dimension;
    zend_object_get_properties_t       get_properties;
    zend_object_get_method_t           get_method;
    zend_object_call_method_t          call_method;
    zend_object_get_constructor_t      get_constructor;
    zend_object_get_class_entry_t      get_class_entry;
    zend_object_get_class_name_t       get_class_name;
    zend_object_compare_t              compare_objects;
    zend_object_cast_t                 cast_object;
    zend_object_count_elements_t       count_elements;
    zend_object_get_debug_info_t       get_debug_info;
    zend_object_get_closure_t          get_closure;
} zend_object_handlers;
```

* 除非特别指定，否则其中的参数被认为是非空指针。
* 不脱离主题，我们回到 `count_elements` 上来，它的函数签名是： `int (*count_elements)(zval *object, long *count TSRMLS_DC)`
* 对它的描述大概如下：
    * 调用此函数可以确定某个可计数对象的计数。计数是非负数。
    * 对象有类似数组的访问元素的功能，并在未来可能会实现，这样他们的行为就更像是数组了。
    * 这个 handler 不常被 zend 引擎使用，而是由 [count](https://www.php.net/count) 和其他扩展使用。
    * 这个程序在向 `*count` 写入一个非负数，并且如果传递的对象是可计数的，返回 SUCCESS，否则返回失败。
    * 如果对象是不是可计数的，则 `count_elements` 可能为空，即使实现了 `count_elements` ，也会总是返回失败。

* 如果对象是可计数的，但没有定义 `count_elements` 。随后，会判断改对象是否实现 [`Countable`](https://www.php.net/manual/class.countable.php)
* 如果实现，则进行调用对象中实现的 `count()` 方法
* 如果既没有定义 `count_elements` ，也没有实现 `Countable` ，则会报错处理。

## 实例
* 对对象进行 count 操作倒是用的少，不妨试试看：

```php
<?php
class ThirdTypeA 
{
    public $data = [
        'merchantId'=>1,
        'key'=>'testxxkey32Xsdadxaqqwey',
    ];

    public function count()
    {
        return count($this->data);
    }
}

$ins = new ThirdTypeA;
$res = count($ins);
var_dump($res);
```

* 此时返回 `1`，并且PHP提示了一个 Warning：

```other
PHP Warning:  count(): Parameter must be an array or an object that implements Countable in /xxxxx/countExample.php on line 16
int(1)
```

* 这个 1 并不是计数的结果，而是异常时的 code ，是符合源码中的逻辑：

```c
php_error_docref(NULL, E_WARNING, "Parameter must be an array or an object that implements Countable");
RETURN_LONG(1);
```

* 改进一下，同样的代码，只是在声明类的时候，显示的实现 Countable 接口： `class ThirdTypeA implements Countable`
* Countable 接口类中很简单，只有1个 count 方法：

```php
interface Countable {

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count();
}
```

* 因而在 `implements Countable` 时，需要实现方法 count 

```php
public function count()
{
    return count($this->data);
}
```

## 参考资料
* 参数的解析
    * https://segmentfault.com/a/1190000007575322
    * https://www.jianshu.com/p/05616d23c0dc
    * https://wiki.php.net/rfc/fast_zpp#proposal
* PHP内核文档 
    * https://phpinternals.net/docs/hash_flag_has_empty_ind
* handler table
    * https://wiki.php.net/internals/engine/objects#the_handler_table
