## 1.1
* 在kubernetes中，Service是分布式集群的核心
* 一个Service对象拥有以下特征
    * 唯一的指定的名字
    * 一个虚拟IP
    * 提供某种远程服务能力
    * 被映射到提供这种能力的一组容器应用上
* 基于socket通信方式对外提服务
* service本身一旦创建就不再变化
* Pod对象，每个Pod中运行着一个特殊的Pause容器
* Master节点包含：kube-apiserver、kube-controller-manager、kube-scheduler进程
* Node运行的是：kubelet kube-proxy进程，这些进程负责Pod创建、启动、监控、重启、销毁
* 在传统的集群中，服务的扩容、实例部署和启动基本上靠人工完成
* 在Kubernetes集群中，只需为扩容的Service关联的Pod创建一个Replication Controller（简称RC）
* 一个RC定义文件中有3个关键信息：
    * 目标Pod定义
    * 目标Pod需要运行的副本数量
    * 要监控的目标Pod的标签
    
### 1.3.1
* 下载和安装一个vmware软件，并用镜像centos7创建一个虚拟机环境
* 关闭centos的防火墙服务
>systemctl disable firewalld <br>
systemctl stop firewalld

* 安装etcd和Kubernates软件
>yum install -y etcd kubernetes

* 安装完毕后，修改配置文件：
    * Docker的配置文件：`/etc/sysconfig/docker`
    >OPTIONS='--selinux-enabled=false --insecure-registry grc.io'
    
    * `Kubernetes apiserver` 配置文件为 `/etc/kubernetes/apiserver` ，把 `--admission_control` 参数中的 `ServiceAccount` 删除


 

