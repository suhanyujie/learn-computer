## PHP扩展-循环一个PHP的数组

### 循环一个PHP的数组，在扩展中的实现
* 代码如下：
```
PHP_FUNCTION(xxtea_string)
{
	zval *arr, **data;
	    HashTable *arr_hash;
	    HashPosition pointer;
	    int array_count;

	    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "a", &arr) == FAILURE) {
	        RETURN_NULL();
	    }

	    arr_hash = Z_ARRVAL_P(arr);
	    array_count = zend_hash_num_elements(arr_hash);
	    php_printf("The array passed contains %d elements \n", array_count);
	    for(zend_hash_internal_pointer_reset_ex(arr_hash, &pointer); zend_hash_get_current_data_ex(arr_hash, (void**) &data, &pointer) == SUCCESS; zend_hash_move_forward_ex(arr_hash, &pointer)) {
	        if (Z_TYPE_PP(data) == IS_STRING) {

	            PHPWRITE(Z_STRVAL_PP(data), Z_STRLEN_PP(data));

	            php_printf("-------\n");

	        }
	    }
	    RETURN_TRUE;
}
```

* 参考资料：http://weizhifeng.net/write-php-extension-part2-1.html
