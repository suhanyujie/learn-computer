# PHP 扩展常用写法汇总

## 常用写法
### 循环相关
### 数组的 foreach
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
#### 申请堆内存
* 如果你在写扩展过程中，需要申请一个自定义结构体的存储空间，可以参考以下：

```c
struct {
    zend_string *str;
    zend_long    lval;
} *strings, *ptr;
ALLOCA_FLAG(use_heap)
strings = do_alloca((sizeof(*strings)) * numelems, use_heap);
```

### 配置相关
#### 获取 ini 配置
* 通过 php_ini_get_configuration_hash 接口可以获取到所有的 ini 配置项


## 其他
### 官方扩展函数编写文档
* https://www.php.net/manual/zh/internals2.funcs.php
* 深入理解php中的ini配置(1) https://www.cnblogs.com/driftcloudy/p/4011954.html
