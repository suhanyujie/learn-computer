# PHP 扩展常用写法汇总

## 常用写法
### 循环相关

### 数组相关
#### 初始化一个数组并带有存储空间
* 

#### 数组的 foreach
* 在扩展中，循环一个数组，可以参考如下：

```c
ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(pieces), tmp) {
    // pieces 是数组
    // tmp 是一个 zval
    // todo 
} ZEND_HASH_FOREACH_END();
```

### 字符串相关
#### 获取 zend_string 长度

```c
ZSTR_LEN(delim);
```

#### 获取 zend_string 对应值的指针
* `char* p = ZSTR_VAL(p_zend_string)`

#### 申请存储 zend_string 类型数据的空间

```c
// len(str) = numelems * len(glue) + len
str = zend_string_safe_alloc(numelems, ZSTR_LEN(glue), len, 0);
```

* 方法2 

```c
// 其中 var 是 char*；var_len 是字符长度
retval = zend_string_init(var, var_len, 0);
```

* 方法3

```c
// 其中 var 是 char*；返回的 retval 是 zend_string* 类型
retval = strpprintf(0, "%s", var);
```

## 返回值相关
* 返回 false `RETURN_FALSE`

```c
if (ZSTR_LEN(delim) == 0) {
    php_error_docref(NULL, E_WARNING, "Empty delimiter");
    RETURN_FALSE;
}
```

### zval 相关
#### 获取一个 zval 中 value 存储的类型
* `Z_TYPE_P(tmp)`

### 申请内存
* 申请内存相关 API

```
emalloc(size_t size);
efree(void *ptr);
ecalloc(size_t nmemb, size_t size);
erealloc(void *ptr, size_t size);
estrdup(const char *s);
estrndup(const char *s, unsigned int length);
```

#### 申请堆内存
* 如果你在写扩展过程中，需要申请一个自定义结构体的存储空间，可以参考以下：

```c
struct {
    zend_string *str;
    zend_long    lval;
} *strings, *ptr;
ALLOCA_FLAG(use_heap)
strings = do_alloca((sizeof(*strings)) * numelems, use_heap);
// 释放方式 
free_alloca(strings, use_heap);
```

### 输出
#### php_printf
* `php_printf("%s\n", var_p);`，其中 var_p 是指针变量，对应的值类型需要跟前面的占位符保持一致。否则报错
* `php_printf("%s\n", local_var);`，也可进行当前作用域的变量打印输出

#### PHPWRITE
* 更加安全的字符串输出：`PHPWRITE(string, strlen(string))`

#### snprintf
* `snprintf(char * str, length, format, var...)`

#### spprintf
* `spprintf(char ** str, max, format, var...)`

### 配置相关
#### 获取 ini 配置
* 通过 php_ini_get_configuration_hash 接口可以获取到所有的 ini 配置项


## 其他
### 语法层面
#### zval 指针
* 声明指针变量后如果直接使用赋值，可能会导致错误，因为申请了指针之后，不一定有空间来存放具体的内容，如下错误的示例：

```c
zval* tmp_zval;
hello = zend_string_init("hello", strlen("hello"), 0);
ZVAL_STR(tmp_zval, hello);
```

* zval 在 PHP 7 后，变得比较精简，可以直接在栈中分配空间：`zval tmp_zval;`


### 官方扩展函数编写文档
* https://www.php.net/manual/zh/internals2.funcs.php
* 深入理解php中的ini配置(1) https://www.cnblogs.com/driftcloudy/p/4011954.html

## 参考资料
* PHP 输出相关 https://github.com/maomao2011/php-zend-api/blob/master/book/zh/output.md
* PHP扩展编写第二步：参数，数组，以及ZVAL http://ju.outofmemory.cn/entry/108223
* 推荐的“PHP扩展开发相关总结” https://www.cnblogs.com/chenpingzhao/p/4922246.html
* PHP7扩展开发之创建变量 https://www.bo56.com/php7%E6%89%A9%E5%B1%95%E5%BC%80%E5%8F%91%E4%B9%8B%E5%88%9B%E5%BB%BA%E5%8F%98%E9%87%8F/
* PHP 内核文档 https://phpinternals.net/docs/alloc_hashtable
* PHP二十一问：PHP的垃圾回收机制 https://www.iminho.me/wiki/blog-18.html
