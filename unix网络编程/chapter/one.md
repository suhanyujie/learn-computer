## 前言和第一节
* 学习本书，需要结合源码进行学习，因为对于我们而言，计算机的知识都是重在实践。
* 在前言中，本书描述，可以到http://www.unpbook.com进行下载，很容易就能找到[下载链接](http://www.unpbook.com/unpv13e.tar.gz)
* 解压文件unpv13e.tar.gz
* 通过阅读README，可以知道如何对代码进行编译：
```html
QUICK AND DIRTY
 ===============
 
 Execute the following from the src/ directory:
 
     ./configure    # try to figure out all implementation differences
 
     cd lib         # build the basic library that all programs need
     make           # use "gmake" everywhere on BSD/OS systems
 
     cd ../libfree  # continue building the basic library
     make
 
     cd ../libroute # only if your system supports 4.4BSD style routing sockets
     make           # only if your system supports 4.4BSD style routing sockets
 
     cd ../libxti   # only if your system supports XTI
     make           # only if your system supports XTI
 
     cd ../intro    # build and test a basic client program
     make daytimetcpcli
     ./daytimetcpcli 127.0.0.1
```  
     
* 可是在执行时，因为一些运行环境的原因，难免遇到报错
* 我是按照这篇文章进行解决的 `https://www.cnblogs.com/52php/p/5684487.html`

## 创建TCP套接字
* 使用套接字的api：`socket(AF_INET,SOCK_STREAM,0);`，它返回的实际上是一个小整数描述符，之后的所有函数调用就使用该描述符来标识这个套接字
* TCP套接字就是TCP端点
