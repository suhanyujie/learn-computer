## 数组
* 该文件位于 `ext/standard/array.c`
* 包含注释在内，一共有6384行

### 用到的宏
#### ZEND_INIT_MODULE_GLOBALS

#### REGISTER_LONG_CONSTANT

### 一些函数
#### php_array_key_compare
* 函数原型：

```c/c++
static int php_array_key_compare(const void *a, const void *b)
{
	Bucket *f = (Bucket *) a;
	Bucket *s = (Bucket *) b;
	zend_uchar t;
	zend_long l1, l2;
	double d;

	if (f->key == NULL) {
		if (s->key == NULL) {
			return (zend_long)f->h > (zend_long)s->h ? 1 : -1;
		} else {
			l1 = (zend_long)f->h;
			t = is_numeric_string(s->key->val, s->key->len, &l2, &d, 1);
			if (t == IS_LONG) {
				/* pass */
			} else if (t == IS_DOUBLE) {
				return ZEND_NORMALIZE_BOOL((double)l1 - d);
			} else {
				l2 = 0;
			}
		}
	} else {
		if (s->key) {
			return zendi_smart_strcmp(f->key, s->key);
		} else {
			l2 = (zend_long)s->h;
			t = is_numeric_string(f->key->val, f->key->len, &l1, &d, 1);
			if (t == IS_LONG) {
				/* pass */
			} else if (t == IS_DOUBLE) {
				return ZEND_NORMALIZE_BOOL(d - (double)l2);
			} else {
				l1 = 0;
			}
		}
	}
	return l1 > l2 ? 1 : (l1 < l2 ? -1 : 0);
}
```
