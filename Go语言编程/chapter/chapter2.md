## 变量声明
* 使用 `var` 关键字

```go
var v1 int = 10;
var v2 = 10;
v3 := 10;
```

* 对于第2行和第3行的声明，编译器是可以自动根据值推导出对应的变量类型，所以不需要显示的声明对应的类型
* 变量交换，无需引入多余的中间临时变量：
>i,j = j,i

### 字面常量

```go
-12
3.14159265358979323846 // 浮点类型的常量
3.2+12i // 复数类型的常量 
true // 布尔类型的常量 
"foo" // 字符串常量
```

### 常量定义

```go
const Pi float64 = 3.14159265358979323846 
const zero = 0.0 // 无类型浮点常量 
const (
    size int64 = 1024
    eof = -1 // 无类型整型常量 
)
const u, v float32 = 0, 3 // u = 0.0, v = 3.0，常量的多重赋值
```

* 常量定义的右值也可以是一个在编译期运算的常量表达式，请注意，是"在编译期运算的常量表达式"，不可以是"运行期才能得出结果的表达式"
>const mask = 1 << 3 //合法

>在编译期运算的常量表达式，也就是在编译时就能得出结果的表达式

* Go语言中，预设了一些常量，如：true false iota
* iota在每个const开头被重设为0

### 枚举
* 枚举是指一些列相关的常量

## Go语言的基础类型
* 它内置了以下基础类型：
* 布尔类型
* 整形
* 浮点型
* 复数类型
* 字符串
* 字符类型
* 错误类型

### 复合类型如下：
* 指针
* 数组
* 切片
* 字典
* 通道
* 结构体
* 接口

### 位运算
* Go语言的大多数位运算符与C语言都比较类似，除了取反在C语言中是`~x`，而在Go语言中 是`^x`

### 浮点数
>因为浮点数不是一种精确的表达方式，所以像整型那样直接用==来判断两个浮点数是否相等 是不可行的，这可能会导致不稳定的结果。<br>

```go
import "math"

// p为用户自定义的比较精度，比如0.00001 
func IsEqual(f1, f2, p float64) bool { 
    return math.Fdim(f1, f2) < p 
}
```

### 字符类型
* Go中支持两种字符类型：byte(实际是uint8别名)、rune(代表单个Unicode字符)
* 关于rune的操作，可以查看`unicode/utf8`包，它提供了unicode和utf-8之间的转换

### 数组
* 在Go语言中，数组长度在定义后就不可更改
>Go语言中数组是一个值类型（value type）。所有的值类型变量在赋值和作为参数传递时都将产生一次复制动作。<br>
如果将数组作为函数的参数类型，则在函数调用时该 参数将发生数据复制。因此，在函数体中无法修改传入的数组的内容，因为函数内操作的只是所 传入数组的一个副本。

### 数组切片
* 可以直接使用`make`函数创建数组切片
* 可以基于数组创建切片：

```go
// 先定义一个数组 
var myArray [10]int = [10]int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10}
// 基于数组创建一个数组切片 
var mySlice []int = myArray[:5]

//直接创建并初始化包含5个元素的数组切片：
mySlice3 := []int{1, 2, 3, 4, 5}

//基于myArray的所有元素创建数组切片：
mySlice = myArray[:]
//基于myArray的前5个元素创建数组切片：
mySlice = myArray[:5]
//基于从第5个元素开始的所有元素创建数组切片：
mySlice = myArray[5:]
```

* 可动态增减元素是数组切片比数组更为强大的功能
* 与数组相比，数组切片多了一个存储能力
* 在一次扩容过程中，内存重新分配和内容复制的过程很有可能发生多次，但这样会明显降低系统的整体性能
* cap()函数返回的是数组切片分配的空间大小
* len()函数返回的是 数组切片中当前所存储的元素个数。

### 一些注意事项
* 不同类型的值不能互相比较，如：int和int8不能进行比较，也不能交叉赋值
* 早期的Go语言用int类型表示Unicode 字符）



