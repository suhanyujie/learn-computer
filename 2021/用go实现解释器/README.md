# 用 go 实现解释器
这本书的原名是 [`writing an INTERPRETER in go`](https://book.douban.com/subject/27034273/)

## 前言
要做到对源代码解释执行，我们需要对源码做一些转换。

1.转换为 token
2.转换为 ast

第一步是 token 化。token 本身很小，易于分类。基于 token，我们可以将其转换为 ast



