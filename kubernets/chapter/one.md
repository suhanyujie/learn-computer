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
* 按顺序启动服务：
>systemctl start etcd   <br>
systemctl start docker <br>
systemctl start kube-apiserver  <br>
systemctl start kube-controller-manager <br>
systemctl start kube-scheduler <br>
systemctl start kubelet <br>
systemctl start kube-proxy <br>

* 此时可以直接编辑为一个shell文件 `startKube.sh`:
```shell
systemctl start etcd
systemctl start docker
systemctl start kube-apiserver
systemctl start kube-controller-manager
systemctl start kube-scheduler
systemctl start kubelet
systemctl start kube-proxy
```

* 先为MySQL服务创建一个RC定义文件：mysql-rc.yaml

```html
apiserver: v1
kind: ReplicationController
metadata:
    name: mysql
spec:
    replicas: 1
    selector:
        app: mysql
    template:
        metadata:
            labels:
                app: mysql
        spec:
            containers:
                - name: mysql
                image: mysql
                ports:
                    - containerPort: 3306
                env:
                    - name: MYSQL_ROOT_PASSWORD
                    value: "123456"
```

* 编辑好文件以后，启动：`kubectl create -f mysql-rc.yaml`
* 查看：`kubectl get rc`
* `kubectl get pods`
* 创建文件 `mysql-svc.yaml`

```html
apiVersion: v1
kind: Service
metadata:
    name: mysql
spec:
    ports:
        - port: 3306
    selector:
        app: mysql
```

* 文件编辑好之后，创建service：`kubectl create -f mysql-svc.yaml`
* 查看service：`kubectl get svc`

```shell
[root@localhost kubernetes]# kubectl get svc
 NAME         CLUSTER-IP     EXTERNAL-IP   PORT(S)    AGE
 kubernetes   10.254.0.1     <none>        443/TCP    6h
 mysql        10.254.80.26   <none>        3306/TCP   47s
```

* 此时，可以看到mysql对应的service有一个cluster ip，这是一个虚拟地址，由Kubenetes系统自动分配

### 1.3.3 启动web应用
* 创建文件 myweb-rc.yaml

```html
kind: ReplicationController
metadata:
    name: myweb
spec:
    replicas: 3
    selector:
        app: myweb
    template:
        metadata:
            labels:
                app: myweb
        spec:
            containers:
                - name: myweb
                  image: Kubeguide/tomcat-app:v1
                  ports:
                    - containerPort: 8080
                  env:
                    - name: MYSQL_SERVICE_HOST
                      value: "mysql"
                    - name: MYSQL_SERVICE_PORT
                      value: '3306'
```

* 创建好Pods之后，再创建对应的Service，配置文件myweb-svc.yaml：

```html
apiVersion: v1
kind: Service
metadata:
    name: myweb
spec:
    type: NodePort
    ports:
        - port: 8080
          nodePort: 30001
    selector:
        app: myweb
```

## 1.4 Kubernetes基本概念和术语
* 诸如Node、Pod、Replication Controller、Service等可以看做一种资源对象
* 我们可以通过Kubernetes提供的api对这些对象进行增、删、改、查操作，并将其保存在etcd中持久化存储
* 因此，他其实是一种高度自动化的资源控制系统

### 1.4.1 Master
* Kubernetes API Server(kube-apiserver)，它提供了HTTP Rest接口的关键服务进程，是资源CURD的唯一入口，也是集群Cluster的入口进程
* Kubernetes Controller Manager (kube-controller-manager)，Kubernetes里所有的资源对象的自动化控制中心
* Kubernetes Scheduler (kube-scheduler)，负责资源调度（Pod调度）的进程，相当于公交公司的"调度室"

## 遇到的问题
### docker无法启动问题，提示Cannot connect to the Docker daemon at unix:///var/run/docker.sock. Is the docker daemon running?
* 编辑配置文件 vi /etc/sysconfig/docker

### pod服务一直处于ContainerCreating状态
* 查看 pods 的状态：`kubectl get pods`
* 使用 `kubectl describe pod {NAME}` 命令查看 `pod` 详情，如`kubectl describe pod myweb-mbsqm`





