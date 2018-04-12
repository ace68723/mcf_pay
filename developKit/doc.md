
 * [create_order](#create_order)
 * [check_order_status](#check_order_status)
 * [get_txn_by_id](#get_txn_by_id)
 * [get_exchange_rate](#get_exchange_rate)

## create_order

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/web/create_order/ |      |
| HTTP请求方式 |      POST      |      |
|  授权访问限制  |     MD5校验      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |


Body参数:

| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| total_fee_in_cent | int(1,inf) | 是 | 标价金额，以分为单位的整数 | ---------------- | 
| total_fee_currency | string(16) | 是 | 标价金额的币种 | ---------------- | 
| description | string(32) | 否 | 商品标题，将显示在顾客端 |  Supported by MCF | 
| notify_url | string(256) | 是 | ---------------- | ---------------- | 


## check_order_status

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/web/check_order_status/ |      |
| HTTP请求方式 |      POST      |      |
|  授权访问限制  |     MD5校验      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |


Body参数:

| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| out_trade_no | string(64) | 是 | MCF开头的交易单号 | ---------------- | 


## get_txn_by_id

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/web/get_txn_by_id/ |      |
| HTTP请求方式 |      POST      |      |
|  授权访问限制  |     MD5校验      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |


Body参数:

| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| ref_id | string(64) | 是 | out_trade_no or refund_id | ---------------- | 


## get_exchange_rate

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/web/get_exchange_rate/ |      |
| HTTP请求方式 |      POST      |      |
|  授权访问限制  |     MD5校验      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |


Body参数:

| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| currency_type | string(16) | 是 | ---------------- | ---------------- | 


