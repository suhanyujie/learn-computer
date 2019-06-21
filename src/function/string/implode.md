# implode 函数源码分析
>*本文[首发](https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/implode.md)于 https://github.com/suhanyujie/learn-computer/blob/master/src/function/string/implode.md* <br>
基于PHP 7.3.3

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
* 


## 参考资料
* 深入理解 PHP 内核 http://www.php-internals.com/book/?p=chapt01/01-03-comm-code-in-php-src
* http://www.phppan.com/2010/02/php-source-12-return_value/








