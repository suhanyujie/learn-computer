# 一个大佬的分享笔记why i learn c
* 作者的[github地址](https://github.com/wrestle)
* [c语言基本语法](http://www.runoob.com/cprogramming/c-basic-syntax.html)参考

## 1
* 谴责 `void main()` 的写法
* 能自己做的事情，就不要让编译器来做
* c++不支持 `void*` 的隐式转换为其他类型的指针
* 

### 一些规范
* 等号两边需要有空格，例如 `int i = 0;`
* 使用多个变量的声明定义，注意使用空格分开变量：`int m, n, a;`
* 多层语句嵌套时，建议使用 `{}` 进行显示的范围规定
* 变量命名使用下划线风格
* 全局变量能少使用就少使用，使用时，可以加 `g_` 作为前缀
* 宏定义，所有字符使用大写，可以使用下划线分隔，如果是多条语句，可以使用 `do{}while(0)` 进行包裹，防止发生错误
* 枚举 enum 也是用大写，下滑线分隔
* 当代码块的作用域超过一个屏幕时，可以使用注释来指明对应的作用域，如 `}/*end of if*/`

### 一些效率问题
* 乘法的时间大于加法
* 位运算的速度是最快的
* 指针变量的递增问题：前缀递增总是在原数上进行递增操作，然而后缀递增呢？它首先拷贝一份原数 放于别处，并且递增这份拷贝，在原数进行的操作完毕后，将这份拷贝再拷贝 进原数取代它。
例如：`int i=0;int* p = &i;p++;++p;`，这里`p++;` 和 `++p;` 的效果是不一样的。
* `volatile` 是一种不常用的关键字，它的作用和 `const` 相反，使用它可能会拖慢你的程序










