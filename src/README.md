# PHP 源码解析相关
* PHP 源码分析
* c 语言编写 PHP 扩展

> 所有的源码分析和学习是基于 `PHP 7.2.8` 版本

## 100个PHP常用函数源码解析
> [*100个函数来源*](https://learnku.com/php/t/25799)

* [intval](/src/function/string/intval.md)
* [strlen](/src/function/string/strlen.md)
* [implode](/src/function/string/implode.md)
* [str_replace](/src/function/str_replace.md)
* [explode](/src/function/string/explode.md)
* [in_array](/src/function/array/in_array.md)
* [substr](/src/function/string/substr.md)
* [count](/src/function/array/count.md)
* [is_array](/src/function/array/is_array.md)

## PHP 源码相关
* 开发PHP扩展可以参考这2个网站的资料：
* https://phpinternals.net/docs
* http://www.phpinternalsbook.com/php7/extensions_design/php_functions.html

### 一些全局说明
* `php-src/main/php.h`, 处于PHP包的主目录中。 这个文件包含了PHP的大部分的宏和API定义。
* `php-src/Zend/zend.h`，处于PHP的 `Zend` 目录，这个文件包含了 `Zend` 的宏和定义
* `php-src/Zend/zend_API.h`，也处于`Zend` 目录，它定义了`Zend` 的API

#### 扩展规范
* Zend是基于一些公约构建起来的，为了避免打破这些规范，你应该遵循下面描述的这些规则：

##### 宏
* 对于大部分的重要的任务，Zend使用预定义的宏，是非常方便的，下面的表格和图描述了大部分基本的功能，结构体和宏

## 其他
* 如果你也感兴趣，欢迎提 pr

## 参考
* http://php.net/manual/zh/internals2.ze1.zendapi.php
