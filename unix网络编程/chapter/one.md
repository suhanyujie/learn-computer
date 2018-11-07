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
* 作者提到，使用memset时，要格外的小心，TCPv3一书中，这个函数在10处出现错误。
* 我们最好是重新定义一下，如：

```c
#define	bzero(ptr,n)		memset(ptr, 0, n)
```

* 套接口描述字其实是指sockfd，也就是使用时，fd的值
* `sizeof(servaddr)` 这个写法的作用，是让编译器计算 `servaddr` 结构体的长度
* TCP 是一个没有记录边界的字节流协议
* socket、bind、listen这3个调用步骤，是任何TCP服务器准备所谓的监听描述符的正常步骤。
>服务进程在accept调用中被投入睡眠，等待客户链接的到达被内核接收 <br>  
TCP链接使用三次握手来建立连接<br>
握手完毕时，返回一个已连接的描述符<br>
该描述符用于与对应的连接进行通信<br>
accept为每个连接到本服务器的客户返回一个新描述符<br>

* `snprintf` 函数第二个参数传的是数据的大小，确保缓冲区不会溢出。比 `sprintf` 更安全

