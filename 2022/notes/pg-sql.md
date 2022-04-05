>* 文章名称：pg 查询笔记
>* 文章来源：https://www.github.com/suhanyujie
>* tags: postgresql, sql

最近，公司的项目改造，其中涉及到，将一些查询从 MySQL 改为 postgresql。在这个改造过程中，查询了很多资料，同时，也感慨 pg 的强大。

pg 查询示例：

```sql
SELECT
	count(*) "all" ,
	"data"::jsonb -> 'projectId' "projectId",
	sum(case when "data" :: jsonb -> 'projectId'>'16395' then 1 else 0 end),
	sum(case when "data" :: jsonb -> 'issueStatus' in ('1', '7') and ("data"::jsonb->> 'createTime')::timestamp > '2021-04-07 17:00:00'::timestamp then 1 else 0 end) "notFinished"
FROM _form_14396_1430066698182303745
GROUP BY "projectId"
limit 10
```

## 参考资料
* PostgreSQL JSONB类型常用操作 https://blog.yasking.org/a/postgresql-jsonb.html
* 官方英文版文档 https://www.postgresql.org/docs/
* 社区中文版文档（要落后一些） http://www.postgres.cn/v2/document
