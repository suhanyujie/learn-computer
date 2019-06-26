# PHP 扩展常用写法汇总

## 常用写法
### 字符串相关
#### 获取 zend_string 长度

```c
ZSTR_LEN(delim);
```

#### 获取 zend_string 对应值的指针
* `char* p = ZSTR_VAL(p_zend_string)`

## 返回值相关
* 返回 false `RETURN_FALSE`

```c
if (ZSTR_LEN(delim) == 0) {
    php_error_docref(NULL, E_WARNING, "Empty delimiter");
    RETURN_FALSE;
}
```

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


## 其他
### 官方扩展函数编写文档
* https://www.php.net/manual/zh/internals2.funcs.php
