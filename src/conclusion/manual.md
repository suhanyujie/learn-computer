# PHP 扩展常用写法汇总

### 常用写法
#### 获取 zend_string 长度

```c
ZSTR_LEN(delim);
```

#### 返回值
* 返回 false `RETURN_FALSE`

```c
if (ZSTR_LEN(delim) == 0) {
    php_error_docref(NULL, E_WARNING, "Empty delimiter");
    RETURN_FALSE;
}
```

## 其他
### 官方扩展函数编写文档
* https://www.php.net/manual/zh/internals2.funcs.php
