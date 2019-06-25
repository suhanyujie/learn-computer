# PHP 源码 — implode 函数源码分析
>* 本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/implode.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/implode.md* 
>* 作者：[suhanyujie](https://github.com/suhanyujie)
>* 基于PHP 7.3.3

## PHP 中的 implode
* 在 PHP 中，implode 的作用是：将一个一维数组的值转化为字符串。记住一维数组，如果是多维的，会发生什么呢？在本篇分析中，会有所探讨。
* 事实上，通过官方的文档可以知道，implode 有两种用法，通过函数签名可以看得出来：

```php
// 方法1
implode ( string $glue , array $pieces ) : string
// 方法2
implode ( array $pieces ) : string
```

* 因为，在不传 glue 的时候，内部实现会默认空字符串。
* 通过一个简单的示例可以看出：

```php
$pieces = [
    123,
    ',是一个',
    'number!',
];
$str1 = implode($pieces);
$str2 = implode('', $pieces);

var_dump($str1, $str2);
/*
string(20) "123,是一个number!"
string(20) "123,是一个number!"
*/
```

## implode 源码实现
* 通过搜索关键字 `PHP_FUNCTION(explode)` 可以找到，该函数定义于 `\ext\standard\string.c` 文件中的 [1288 行](https://github.com/php/php-src/blob/9ebd7f36b1bcbb2b425ab8e903846f3339d6d566/ext/standard/string.c#L1288)
* 一开始的几行是参数声明相关的信息。其中 *arg2 是用于接收 pieces 参数的指针。
* 在下方对 arg2 的判断中，如果 arg2 为空，则表示没有传 pieces 对应的值

```c
if (arg2 == NULL) {
    if (Z_TYPE_P(arg1) != IS_ARRAY) {
        php_error_docref(NULL, E_WARNING, "Argument must be an array");
        return;
    }

    glue = ZSTR_EMPTY_ALLOC();
    tmp_glue = NULL;
    pieces = arg1;
} else {
    if (Z_TYPE_P(arg1) == IS_ARRAY) {
        glue = zval_get_tmp_string(arg2, &tmp_glue);
        pieces = arg1;
    } else if (Z_TYPE_P(arg2) == IS_ARRAY) {
        glue = zval_get_tmp_string(arg1, &tmp_glue);
        pieces = arg2;
    } else {
        php_error_docref(NULL, E_WARNING, "Invalid arguments passed");
        return;
    }
}
```

### 不传递 pieces 参数
* 在不传递 pieces 参数的判断中，即 `arg2 == NULL`，主要是对参数的一些处理
* 将 glue 初始化为空字符串，并将传进来的唯一的参数，赋值给 pieces 变量，接着就调用 `php_implode(glue, pieces, return_value);`

### 十分关键的 php_implode
* 无论有没有传递 pieces 参数，在处理好参数后，最终都会调用 PHPAPI 的相关函数 php_implode，可见，关键逻辑都是在这个函数中实现的，那么我们深入其中看一看它
* 在调用 php_implode 时，出现了一个看起来没有被声明的变量 return_value。没错，它似乎就是凭空出现的
* 通过谷歌搜索 `PHP源码中 return_value`，找到了[答案](http://demon.tw/programming/php-function-return_value.html)。
* 原来，这个变量是伴随着宏 PHP_FUNCTION 而出现的，而此处 implode 的实现就是通过 `PHP_FUNCTION(implode)` 来声明的。而 PHP_FUNCTION 的定义是:

```c
#define PHP_FUNCTION			ZEND_FUNCTION
// 对应的 ZEND_FUNCTION 定义如下
#define ZEND_FUNCTION(name)				ZEND_NAMED_FUNCTION(ZEND_FN(name))
// 对应的 ZEND_NAMED_FUNCTION 定义如下
#define ZEND_NAMED_FUNCTION(name)		void ZEND_FASTCALL name(INTERNAL_FUNCTION_PARAMETERS)
// 对应的 ZEND_FN 定义如下
#define ZEND_FN(name) zif_##name
// 对应的 ZEND_FASTCALL 定义如下
# define ZEND_FASTCALL __attribute__((fastcall))
```

* （关于双井号，它起连接符的作用，可以[参考这里](http://www.php-internals.com/book/?p=chapt01/01-03-comm-code-in-php-src)了解）
* 在被预处理后，它的样子类似于下方所示：

```c
void zif_implode(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used TSRMLS_DC)
```

* 也就是说 return_value 是作为整个 implode 扩展函数定义的一个形参
* 在 php_implode 的定义中，一开始，先定义了一些即将用到的变量，随后使用 `ALLOCA_FLAG(use_heap)` 进行标识，如果申请内存，则申请的是堆内存
* 通过 `numelems = zend_hash_num_elements(Z_ARRVAL_P(pieces));` 获取 pieces 参数的单元数量，如果是空数组，则直接返回空字符串
* 此处还有判断，如果数组单元数为 1，则直接将唯一的单元作为字符串返回。
* 最后是处理多数组单元的情况，因为前面标识过，若申请内存则申请的是堆内存，堆内存相对于栈来讲，效率比较低，所以只在非用不可的情形下，才会申请堆内存，那此处的情形就是多单元数组的情况。
* 随后，针对 pieces 循环，获取其值进行拼接，在源码中的 foreach 循环是固定结构，如下：

```c
ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(zend_array), tmp) {
    // ...
} ZEND_HASH_FOREACH_END();
```

* 这种常用写法我觉得，在编写 PHP 扩展中是必不可少的吧。虽然我还没有编写过任何一个可用于生产环境的 PHP 扩展。但我正努力朝那个方向走呢！
* 在循环内，对数组单元分为三类：
    * 字符串
    * 整形数据
    * 其它
* 事实上，在循环开始之前，源码中，先申请了一块内存，用于存放下面的结构体，并且个数恰好是 pieces 数组单元的个数。

```c
struct {
    zend_string *str;
    zend_long    lval;
} *strings, *ptr;
```

* 可以看到，结构体成员包含 zend 字符串以及 zend 整形数据。这个结构体的出现，恰好是为了存放数组单元中的 zend 字符串/zend 整形数据。

#### 字符串
* 先假设，pieces 数组单元中，都是字符串类型，此时循环中执行的逻辑就是：

```c
// tmp 是循环中的单元值
ptr->str = Z_STR_P(tmp);
len += ZSTR_LEN(ptr->str);
ptr->lval = 0;
ptr++;
```

* 其中，tmp 是循环中的单元值。每经历一次循环，会将单元值放入结构体中，随后进行指针 +1 运算，指针就指向存储下一个结构体数据的地址：
* ![](./implodePic1.png)
* 并且，在这期间，统计出了字符串的总长度 `len += ZSTR_LEN(ptr->str);`

#### 整数类型
* 以上，讨论了数组单元中是字符串的情况。接下来看看，如果数组单元的类型是数值类型时会发生什么？
* 判断一个变量是否是数值类型（其实是 zend_long），通用方法是：`Z_TYPE_P(tmp) == IS_LONG`。一旦知道当前的数据类型是 zend_long，则将其赋值给 ptr 的 lval 结构体成员。然后 ptr 指针后移一个单位长度。
* 但是，我们知道我们不能像获取 zend_string 的长度一样去获取 zend_long 的字符长度。如果是 zend_string，则可以通过 `len += ZSTR_LEN(val);` 的方式获取其字符长度。对于 zend_long，有什么好的方法呢？
* 在源码中是通过对 10 做除法运算，得出结果的一部分，再慢慢的累加其长度：

```c
while (val) {
    val /= 10;
    len++;
}
```

* 如果是负数呢？没有什么特别的办法，直接判断处理：

```c
if (val <= 0) {
    len++;
}
```

### 字符串的处理和拷贝
* 循环结束后，ptr 就是指向这段内存的尾部的指针。
* 然后，申请了一段内存：`str = zend_string_safe_alloc(numelems - 1, ZSTR_LEN(glue), len, 0);`，用于存放单元字符串总长度加上连接字符的总长度，即 `(n-1)glue + len`。因为 n 个数组单元，只需要 n-1 个 glue 字符串。然后，将这段内存的尾地址，赋值给 cptr，为什么要指向尾部呢？看下一部分，你就会明白了。
* 接下来，需要循环取出存放在 ptr 中的字符。我们知道，ptr 此时是所处内存区域的尾部，为了能有序展示连接的字符串，源码中，是从后向前循环处理。这也就是为什么需要把 cptr 指向所在内存区域的尾部的原因。
* 进入循环，先进行 `ptr--;`，然后针对 ptr->str 的判断 `if (EXPECTED(ptr->str))`，看了一下此处的 EXPECTED 的作用，可以[参考这里](https://blog.csdn.net/GrubLinux/article/details/37543489)。可以简单的将其理解一种汇编层面的优化，当实际执行的情况更偏向于当前条件下的分支而非 else 的分支时，就用 EXPECTED 宏将其包装起来：`EXPECTED(ptr->str)`。我敢说，当你调用 implode 传递的数组中都是数字而非字符串，那么这里的 EXPECTED 作用就会失效。
* 接下来的两行是比较核心的：

```c
cptr -= ZSTR_LEN(ptr->str);
memcpy(cptr, ZSTR_VAL(ptr->str), ZSTR_LEN(ptr->str));
```

* cptr 的指针前移一个数组单元字符的长度，然后将 `ptr->str` （某数组单元的值）通过 c 标准库函数 memcpy 拷贝到 cptr 内存空间中。
* 当 `ptr == strings` 满足时，意味着 ptr 不再有可被复制的字符串/数字。因为 strings 是 ptr 所在区域的首地址。
* 通过上面，已经成功将一个数组单元的字符串拷贝到 cptr 对应的内存区域中，接下来如何处理 glue 呢？
* 只需要像处理 `ptr->str` 一样处理 glue 即可。至少源码中是这么做的。
* 代码中有一段是：`*cptr = 0`，它的作用相当于赋值空字符串。
* cptr 继续前移 glue 的长度，然后，将 glue 字符串拷贝到 cptr 对应的内存区域中。没错，还是用 memcpy 函数。
* 到这里，第一次循环结束了。我应该不需要像实际循环中那样描述这里的循环吧？相信优秀的你,是完全可以参考上方的描述脑补出来的 ^^
* 当然，处理返回的两句还是要提一下：

```c
free_alloca(strings, use_heap);
RETURN_NEW_STR(str);
```

* strings 的那一片内存空间只是存储临时值的，因此函数结束了，就必须跟 strings 说再见。我们知道 c 语言是手动管理内存的，没有 GC，你要显示的释放内存，即 `free_alloca(strings, use_heap);`。
* 在上面的描述中，我们只讲到了 cptr，但这里的返回值却是 str。
* 不用怀疑，这里是对的，我们所讲的 cptr 那一片内存区域的首地址就是 str。并通过宏 `RETURN_NEW_STR` 会将最终的返回值写入 return_value 中

## 实践
* 为了可能更加清晰 implode 源码中代码运行时的情况，接下来，我们通过 PHP 扩展的方式对其进行 debug。在这个过程中的代码，我都放在 [GitHub](https://github.com/suhanyujie/su_dd/tree/debug/implode) 的仓库中，分支名是 `debug/implode`，可自行下载运行，看看效果。
* 新建 PHP 扩展模板的操作，可以[参考这里](https://github.com/suhanyujie/su_dd/blob/debug/implode/docs/prepare.md)。请确保操作完里面描述的步骤。
* 接下来，主要针对 su_dd.c 文件修改代码。为了能通过修改代码来看效果，将 php_implode 函数复制到扩展文件中，并将其命名为 su_php_implode：

```c
static void su_php_implode(const zend_string *glue, zval *pieces, zval *return_value)
{
	// 源码内容省略
}
```

* 在扩展中新增一个扩展函数 su_test：

```c
PHP_FUNCTION(su_test)
{
	zval tmp;
	zend_string *str, *glue, *tmp_glue;
	zval *arg1, *arg2 = NULL, *pieces;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ZVAL(arg1)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL(arg2)
	ZEND_PARSE_PARAMETERS_END();
	glue = zval_get_tmp_string(arg1, &tmp_glue);
	pieces = arg2;
	su_php_implode(glue, pieces, return_value);
}
```

* 因为扩展的编译以及引入,前面的已经[提及](https://github.com/suhanyujie/su_dd/blob/debug/implode/docs/prepare.md)。因此，此时只需编写 PHP 代码进行调用：

```php
// t1.php
$res = su_test('-', [
	2019, '01', '01',
]);
var_dump($res);
```

* PHP 运行该脚本，输出：`string(10) "2019-01-01"`，这意味着，你已经成功编写了一个扩展函数。别急，这只是迈出了第一步，别忘记我们的目标：通过调试来学习 implode 源码。
* 接下来，我们通过 gdb 工具，调试以上 PHP 代码在源码层面的运行。为了防止初学者不会用 gdb，这里就繁琐的写出这个过程。如果没有安装 gdb，请自行谷歌。
* 先进入 PHP 脚本所在路径。命令行下:

```bash
gdb php
b zval_get_tmp_string
r t1.php
```

* `b` 即 break，表示打一个断点
* `r` 即 run，表示运行脚本
* `s` 即 step，表示一步一步调试，遇到方法调用，会进入方法内部单步调试
* `n` 即 next，表示一行一行调试。遇到方法，则调试直接略过直接执行返回，调试不会进入其内部。 
* `p` 即 print，表示打印当前作用域中的一个变量

* 当运行完 `r t1.php`，则会定位到第一个断点对应的行，显示如下：

```bash
Breakpoint 1, zif_su_test (execute_data=0x7ffff1a1d0c0, 
    return_value=0x7ffff1a1d090)
    at /home/www/clang/php-7.3.3/ext/su_dd/su_dd.c:179
179		glue = zval_get_tmp_string(arg1, &tmp_glue);
```

* 此时，按下 `n`，显示如下：

```bash
184		su_php_implode(glue, pieces, return_value);
```

* 此时，当前的作用域中存在变量：`glue`，`pieces`，`return_value`
* 我们可以通过 gdb 调试，查看 `pieces` 的值。先使用命令：`p pieces`，此时在终端会显示类似于如下内容：

```bash
$1 = (zval *) 0x7ffff1a1d120
```

* 表明 `pieces` 是一个 zval 类型的指针，`0x7ffff1a1d120` 是其地址，当然，你运行的时候对应的也是一个地址，只不过跟我的这个会不太一样。
* 我们继续使用 `p` 去打印存储于改地址的变量内容：`p *$1`,`$1` 可以认为是一个临时变量名，`*` 是取值运算符。运行完后，此时显示如下：

```bash
(gdb) p *$1
$2 = {value = {lval = 140737247576960, dval = 6.9533439118030153e-310, 
    counted = 0x7ffff1a60380, str = 0x7ffff1a60380, arr = 0x7ffff1a60380, 
    obj = 0x7ffff1a60380, res = 0x7ffff1a60380, ref = 0x7ffff1a60380, 
    ast = 0x7ffff1a60380, zv = 0x7ffff1a60380, ptr = 0x7ffff1a60380, 
    ce = 0x7ffff1a60380, func = 0x7ffff1a60380, ww = {w1 = 4054188928, 
      w2 = 32767}}, u1 = {v = {type = 7 '\a', type_flags = 1 '\001', u = {
        call_info = 0, extra = 0}}, type_info = 263}, u2 = {next = 0, 
    cache_slot = 0, opline_num = 0, lineno = 0, num_args = 0, fe_pos = 0, 
    fe_iter_idx = 0, access_flags = 0, property_guard = 0, constant_flags = 0, 
    extra = 0}}
```

* 打印的内容，看起来是一堆乱糟糟的字符，这实际上是 zval 的结构体，其中的字段刚好是和 zval 的成员一一对应的，为了便于读者阅读，这里直接贴出 zval 的结构体信息：

```c
struct _zval_struct {
	zend_value        value;			/* value */
	union {
		struct {
			ZEND_ENDIAN_LOHI_3(
				zend_uchar    type,			/* active type */
				zend_uchar    type_flags,
				union {
					uint16_t  call_info;    /* call info for EX(This) */
					uint16_t  extra;        /* not further specified */
				} u)
		} v;
		uint32_t type_info;
	} u1;
	union {
		uint32_t     next;                 /* hash collision chain */
		uint32_t     cache_slot;           /* cache slot (for RECV_INIT) */
		uint32_t     opline_num;           /* opline number (for FAST_CALL) */
		uint32_t     lineno;               /* line number (for ast nodes) */
		uint32_t     num_args;             /* arguments number for EX(This) */
		uint32_t     fe_pos;               /* foreach position */
		uint32_t     fe_iter_idx;          /* foreach iterator index */
		uint32_t     access_flags;         /* class constant access flags */
		uint32_t     property_guard;       /* single property guard */
		uint32_t     constant_flags;       /* constant flags */
		uint32_t     extra;                /* not further specified */
	} u2;
};
```

* 我们直指要害 —— `value`，打印一下其中的内容。打印结构体成员可以使用 `.` 运算符，例如：`p $2.value`，运行这个命令，显示如下：

```bash
(gdb) p $2.value
$3 = {lval = 140737247576960, dval = 6.9533439118030153e-310, 
  counted = 0x7ffff1a60380, str = 0x7ffff1a60380, arr = 0x7ffff1a60380, 
  obj = 0x7ffff1a60380, res = 0x7ffff1a60380, ref = 0x7ffff1a60380, 
  ast = 0x7ffff1a60380, zv = 0x7ffff1a60380, ptr = 0x7ffff1a60380, 
  ce = 0x7ffff1a60380, func = 0x7ffff1a60380, ww = {w1 = 4054188928, 
    w2 = 32767}}
```

* 通过 zval 结构体，我们知道 value 成员的类型是 zend_value，很不幸，这也是一个结构体：

```c
typedef union _zend_value {
	zend_long         lval;				/* long value */
	double            dval;				/* double value */
	zend_refcounted  *counted;
	zend_string      *str;
	zend_array       *arr;
	zend_object      *obj;
	zend_resource    *res;
	zend_reference   *ref;
	zend_ast_ref     *ast;
	zval             *zv;
	void             *ptr;
	zend_class_entry *ce;
	zend_function    *func;
	struct {
		uint32_t w1;
		uint32_t w2;
	} ww;
} zend_value;
```

* 我们要打印的变量是 pieces，我们知道它是一个数组，因而此时我们直接取 zend_value 结构体的 `*arr` 成员，它外表看起来就是一个指针，因此打印其内容，需要使用 `*` 运算符

```bash
(gdb) p *$3.arr
$4 = {gc = {refcount = 2, u = {type_info = 23}}, u = {v = {flags = 28 '\034', 
      _unused = 0 '\000', nIteratorsCount = 0 '\000', _unused2 = 0 '\000'}, 
    flags = 28}, nTableMask = 4294967294, arData = 0x7ffff1a67648, 
  nNumUsed = 3, nNumOfElements = 3, nTableSize = 8, nInternalPointer = 0, 
  nNextFreeElement = 3, pDestructor = 0x555555b6e200 <zval_ptr_dtor>}
```

* 真棒！到目前为止，貌似一切都按照预定的路线进行。通过 zend_value 结构体，可以知道 `*arr` 的类型是 zend_array：

```c
struct _zend_array {
	zend_refcounted_h gc;
	union {
		struct {
			ZEND_ENDIAN_LOHI_4(
				zend_uchar    flags,
				zend_uchar    _unused,
				zend_uchar    nIteratorsCount,
				zend_uchar    _unused2)
		} v;
		uint32_t flags;
	} u;
	uint32_t          nTableMask;
	Bucket           *arData;
	uint32_t          nNumUsed;
	uint32_t          nNumOfElements;
	uint32_t          nTableSize;
	uint32_t          nInternalPointer;
	zend_long         nNextFreeElement;
	dtor_func_t       pDestructor;
};
```

* 了解 PHP 数组的同学一定知道它底层是一个 HashTable，感兴趣的同学，可以去自行了解一下 HashTable。这里，我们打印 `*arData`，使用：`p *$4.arDaa`:

```bash
(gdb) p *$4.arData
$5 = {val = {value = {lval = 2019, dval = 9.9751853895347677e-321, 
      counted = 0x7e3, str = 0x7e3, arr = 0x7e3, obj = 0x7e3, res = 0x7e3, 
      ref = 0x7e3, ast = 0x7e3, zv = 0x7e3, ptr = 0x7e3, ce = 0x7e3, 
      func = 0x7e3, ww = {w1 = 2019, w2 = 0}}, u1 = {v = {type = 4 '\004', 
        type_flags = 0 '\000', u = {call_info = 0, extra = 0}}, type_info = 4}, 
    u2 = {next = 0, cache_slot = 0, opline_num = 0, lineno = 0, num_args = 0, 
      fe_pos = 0, fe_iter_idx = 0, access_flags = 0, property_guard = 0, 
      constant_flags = 0, extra = 0}}, h = 0, key = 0x0}
```

* 到这里，我们已经可以看到 pieces 数组第一个单元的值 —— 2019，就是那段 `lval = 2019`。
* 好了，关于 gdb 的简单使用就先介绍到这里。文章开篇，我们提到，如果数组是多维数组，会发生什么？我们实践的主要目标就是简单实现二维数组的 implode
* 在 PHP 的 implode 函数中，如果是多维数组，则会直接把里层的数组显示为 Array 字符串。

```php
$res = implode('-', [
	2019, '01', '01', [1,2]
]);
var_dump($res);
```

* 运行这段脚本，会输出如下：

```bash
PHP Notice:  Array to string conversion in /path/to/t2.php on line 3
PHP Notice:  Array to string conversion in /path/to/t2.php on line 3
string(16) "2019-01-01-Array"
```

* 为了能够支持连接数组，我们需要改写 php_implode，因此，先拷贝一下 php_implode 到写扩展代码的文件中：

```c
PHPAPI void php_implode(const zend_string *glue, zval *pieces, zval *return_value)
{
	zval         *tmp;
	int           numelems;
	zend_string  *str;
	char         *cptr;
	size_t        len = 0;
	struct {
		zend_string *str;
		zend_long    lval;
	} *strings, *ptr;
	ALLOCA_FLAG(use_heap)

	numelems = zend_hash_num_elements(Z_ARRVAL_P(pieces));

	if (numelems == 0) {
		RETURN_EMPTY_STRING();
	} else if (numelems == 1) {
		/* loop to search the first not undefined element... */
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(pieces), tmp) {
			RETURN_STR(zval_get_string(tmp));
		} ZEND_HASH_FOREACH_END();
	}

	ptr = strings = do_alloca((sizeof(*strings)) * numelems, use_heap);

	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(pieces), tmp) {
		if (EXPECTED(Z_TYPE_P(tmp) == IS_STRING)) {
			ptr->str = Z_STR_P(tmp);
			len += ZSTR_LEN(ptr->str);
			ptr->lval = 0;
			ptr++;
		} else if (UNEXPECTED(Z_TYPE_P(tmp) == IS_LONG)) {
			zend_long val = Z_LVAL_P(tmp);

			ptr->str = NULL;
			ptr->lval = val;
			ptr++;
			if (val <= 0) {
				len++;
			}
			while (val) {
				val /= 10;
				len++;
			}
		} else {
			ptr->str = zval_get_string_func(tmp);
			len += ZSTR_LEN(ptr->str);
			ptr->lval = 1;
			ptr++;
		}
	} ZEND_HASH_FOREACH_END();

	/* numelems can not be 0, we checked above */
	str = zend_string_safe_alloc(numelems - 1, ZSTR_LEN(glue), len, 0);
	cptr = ZSTR_VAL(str) + ZSTR_LEN(str);
	*cptr = 0;

	while (1) {
		ptr--;
		if (EXPECTED(ptr->str)) {
			cptr -= ZSTR_LEN(ptr->str);
			memcpy(cptr, ZSTR_VAL(ptr->str), ZSTR_LEN(ptr->str));
			if (ptr->lval) {
				zend_string_release_ex(ptr->str, 0);
			}
		} else {
			char *oldPtr = cptr;
			char oldVal = *cptr;
			cptr = zend_print_long_to_buf(cptr, ptr->lval);
			*oldPtr = oldVal;
		}

		if (ptr == strings) {
			break;
		}

		cptr -= ZSTR_LEN(glue);
		memcpy(cptr, ZSTR_VAL(glue), ZSTR_LEN(glue));
	}

	free_alloca(strings, use_heap);
	RETURN_NEW_STR(str);
}
```

* 先将函数签名稍微调整成 `static void su_php_implode(const zend_string *glue, zval *pieces, zval *return_value)`
* 我们可以看到其中有一段循环 pieces 的处理：

```c
ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(pieces), tmp) {
		if (EXPECTED(Z_TYPE_P(tmp) == IS_STRING)) {
			// ...
		} else if (UNEXPECTED(Z_TYPE_P(tmp) == IS_LONG)) {
			// ...
		} else {
			// ...
		}
	} ZEND_HASH_FOREACH_END();
```

* 我们只需将其中的 if 分支新增一个分支：`else if (UNEXPECTED(Z_TYPE_P(tmp) == IS_ARRAY))`，其具体内容如下：

```c
ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(pieces), tmp) {
    if (EXPECTED(Z_TYPE_P(tmp) == IS_STRING)) {
        // ...
    } else if (UNEXPECTED(Z_TYPE_P(tmp) == IS_LONG)) {
        // ...
    } else if (UNEXPECTED(Z_TYPE_P(tmp) == IS_ARRAY)) {
        // 如果值是数组，则调用 php_implode，将其使用 glue 连接成字符串
        cptr = ZSTR_VAL(ptr->str);
        zend_string* str2 = origin_php_implode(glue, tmp, tmp_val);
        ptr->str = str2;
        // 此时，要拿到 tmp_str 存储的字符串长度
        len += ZSTR_LEN(str2);
        ptr++;
    } else {
        // ...
    }
} ZEND_HASH_FOREACH_END();
```

* 正如注释中写的，当遇到数组的单元是数组类型时，我们会调用原先的 php_implode，只不过，这个“php_implode”会真的返回一个 zend_string 指针，在此我将其改名为 `origin_php_implode`：

```c
static zend_string* origin_php_implode(const zend_string *glue, zval *pieces, zval *return_value)
{
	zval         *tmp;
	int           numelems;
	zend_string  *str;
	char         *cptr;
	size_t        len = 0;
	struct {
		zend_string *str;
		zend_long    lval;
	} *strings, *ptr;
	ALLOCA_FLAG(use_heap)

	numelems = zend_hash_num_elements(Z_ARRVAL_P(pieces));

	if (numelems == 0) {
		RETURN_EMPTY_STRING();
	} else if (numelems == 1) {
		/* loop to search the first not undefined element... */
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(pieces), tmp) {
			RETURN_STR(zval_get_string(tmp));
		} ZEND_HASH_FOREACH_END();
	}

	ptr = strings = do_alloca((sizeof(*strings)) * numelems, use_heap);

	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(pieces), tmp) {
		if (EXPECTED(Z_TYPE_P(tmp) == IS_STRING)) {
			ptr->str = Z_STR_P(tmp);
			len += ZSTR_LEN(ptr->str);
			ptr->lval = 0;
			ptr++;
		} else if (UNEXPECTED(Z_TYPE_P(tmp) == IS_LONG)) {
			zend_long val = Z_LVAL_P(tmp);

			ptr->str = NULL;
			ptr->lval = val;
			ptr++;
			if (val <= 0) {
				len++;
			}
			while (val) {
				val /= 10;
				len++;
			}
		} else {
			ptr->str = zval_get_string_func(tmp);
			len += ZSTR_LEN(ptr->str);
			ptr->lval = 1;
			ptr++;
		}
	} ZEND_HASH_FOREACH_END();

	/* numelems can not be 0, we checked above */
	str = zend_string_safe_alloc(numelems - 1, ZSTR_LEN(glue), len, 0);
	cptr = ZSTR_VAL(str) + ZSTR_LEN(str);
	*cptr = 0;

	while (1) {
		ptr--;
		if (EXPECTED(ptr->str)) {
			cptr -= ZSTR_LEN(ptr->str);
			memcpy(cptr, ZSTR_VAL(ptr->str), ZSTR_LEN(ptr->str));
			if (ptr->lval) {
				zend_string_release_ex(ptr->str, 0);
			}
		} else {
			char *oldPtr = cptr;
			char oldVal = *cptr;
			cptr = zend_print_long_to_buf(cptr, ptr->lval);
			*oldPtr = oldVal;
		}

		if (ptr == strings) {
			break;
		}

		cptr -= ZSTR_LEN(glue);
		memcpy(cptr, ZSTR_VAL(glue), ZSTR_LEN(glue));
	}

	free_alloca(strings, use_heap);
	// RETURN_NEW_STR(str);
	return str;
}
```

* 内容大体不变，只有函数签名以及返回值的地方略作调整了。
* 配合前面的 `PHP_FUNCTION(su_test)`，功能实现的差不多了。我们去编译看看：

```bash
./configure
sudo make
sudo make install
```

* 太棒了，编译通过。我们去执行一下 PHP 脚本：

```php
$res = su_test('-', [
	2019, '01', '01', ['1', '2',],
]);
var_dump($res);
```

* 输出如下：

```bash
string(14) "2019-01-01-1-2"
```

* 恭喜，我们已经大功告成！

## 参考资料
* 深入理解 PHP 内核 http://www.php-internals.com/book/?p=chapt01/01-03-comm-code-in-php-src
* http://www.phppan.com/2010/02/php-source-12-return_value/
* https://github.com/pangudashu/php7-internal/blob/master/7/var.md
