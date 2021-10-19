>* 原文链接：https://www.packtpub.com/product/hands-on-data-structures-and-algorithms-with-rust/9781788995528
>* 译文来自：https://github.com/suhanyujie/learn-computer

# crate
## 静态库和动态库
通常，Rust 依赖两种链接类型：
* 静态库：`rlib` 格式
* 动态库：通过共享的方式（如 `.so` 或者 `.dll` 文件）

Rust 执行时，如果可以找到依赖的 rlib 文件，则会优先选择该静态链接库，从而将所有依赖打包输出到二进制文件中，进而使得文件更大。因此，如果多个 Rust 程序使用相同的依赖库，这些程序都可以拥有自己的内置版本，这些都和实际情况有关。正如 Go 语言所带来的成功那样，静态链接库可以简化部署，只需一个文件即可部署。

静态链接除了影响二进制文件大小之外，它还有缺点：对于静态库，所有依赖项必须是 rlib 类型，这是 Rust 的原生包格式，并且其中不能包含动态库，因为其格式（例如，`.so`（动态）文件和 `.a`(静态）不可转换。

对于Rust，动态链接通常用于本地依赖关系，因为它们通常用在操作系统中，并且不需要包含在包中。Rust 编译器可以使用一个 `-c prefer-dynamic` 标志来支持这一点，这会让编译器先查找相应的动态库。

其中是编译器的当前策略：根据输出格式(`--crate-format=rlib,dylib,staticlib,library,` 或 `bin`)，它通过标志根据你的传入的标记决定最佳链接类型。但是，有一条规则是，输出不能静态链接同一库两次，因此它不会链接具有相同静态依赖关系的两个库。

有关编译器的更多信息可以查看 https://doc.rust-lang.org/reference/linkage.html。


