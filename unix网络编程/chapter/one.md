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
* Mac下，单独编译某个文件，从Makefile文件中可以看出，使用命令 `gcc -I../lib -g -O2 -D_REENTRANT -Wall -o daytimetcpsrv daytimetcpsrv.o ../libunp.a -lresolv -lpthread`

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

## 1.7 OSI模型
* 七层模型：应用层、表示层、会话层、传输层、网络层、数据链路层、物理层
* OSI的顶上3层被合并成一层，称为应用层。这就是Web客户端、Telnet客户、Web服务器等
* 顶上3层通常构成所谓的用户进程，底下4层却通常作为操作系统内核的一部分提供

# 第2章

## 2.2
* traceroute程序可以连接ICMP套接字，还能使用TCP套接字。我们的应用层程序一般使用TCP套接字
* TCP是一个传输控制协议。它是一个面向连接的协议，为用户进程提供可靠的全双工字节流
* TCP节能使用 IPv4 ，也能使用 IPv6
* UDP是用户数据报协议，是一个无连接的协议。它能使用 IPv4 ，也能使用 IPv6
* SCTP 是流控制传输协议，是一个全双工关联的面向连接的协议，关联的意思是指"多宿"。它能使用 IPv4 ，也能使用 IPv6
* ICMP 是网际控制消息协议。它处理在路由器和主机之间流通的错误和控制消息。

### TCP的可靠性
* 当TCP向另一端发送数据时，它要求对端返回一个确认，如果没有收到确认，TCP就自动重传数据并等待更长时间。数次重传失败后，才会放弃。总时间一般4-10分钟
* 实际上TCP也不是100%可靠的，他提供的只是可靠递送或故障的可靠通知。不能保证一定会被对端接收，引申的是拜占庭将军问题。。。
* 因为TCP会给分节数据进行编号，所以可以判断是否重复，对重复的编号数据 可以进行丢弃处理
* TCP提供流量控制，告知对端一次接收多少字节，这个叫通告窗口
>在任何时候，该窗口指出接收缓冲区中当前的可用的空间量，从而确保发送端发送的数据不会使接收缓冲区溢出。该窗口时刻动态变化：
当接收到来自发送端的数据时，窗口大小就减小，但是当接收端应用从缓冲区中读取数据时，窗口大小就增大。通告窗口大小减小到0是有可能的：
当TCP对应某个套接字的接收缓冲区已满，导致它必须等待应用从缓冲区读取数据时，方能从对端再接收数据。

* TCP是全双工的，而UDP也能做到全双工

## 2.6
* 一个客户端发起一个TCP连接，首先发送一个SYN分节，这个分节中包含IP首部、TCP首部以及TCP选项

### TCP选项
* MSS选项。告知对端，它愿意接受的最大数据量。发送端TCP使用接收端的MSS值作为所发送分节的最大大小。
* 窗口规模选项。TCP链接任何一端能够通告对端的最大窗口大小是65535。因为在TCP首部中相应的字段占16位，它能表示的最大值是65535。
    这在当今的互联网下，可能不满足要求，需要有更大的窗口以获得更大的吞吐量。
* 时间戳选项。





