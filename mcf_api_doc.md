##precreate_authpay

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/precreate_authpay/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| device_id | string(64) | 是 | ---------------- | ---------------- | 
| total_fee_in_cent | int | 是 | 标价金额，以分为单位的整数 | ---------------- | 
| total_fee_currency | string(16) | 是 | 标价金额的币种 | ---------------- | 
| description | string(32) | 否 | 商品标题，将显示在顾客端 |  Supported by MCF | 

##create_authpay

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/create_authpay/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| device_id | string(64) | 是 | ---------------- | ---------------- | 
| total_fee_in_cent | int | 是 | 标价金额，以分为单位的整数 | ---------------- | 
| total_fee_currency | string(16) | 是 | 标价金额的币种 | ---------------- | 
| out_trade_no | string(64) | 是 | MCF开头的交易单号 | ---------------- | 
| auth_code | string(128) | 是 | 顾客授权码 | ---------------- | 

##create_order

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/create_order/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| device_id | string(64) | 是 | ---------------- | ---------------- | 
| total_fee_in_cent | int | 是 | 标价金额，以分为单位的整数 | ---------------- | 
| total_fee_currency | string(16) | 是 | 标价金额的币种 | ---------------- | 
| description | string(32) | 否 | 商品标题，将显示在顾客端 |  Supported by MCF | 

##check_order_status

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/check_order_status/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| type | string(16) | 是 | enum("long_pulling","refresh","force_remote"), long_pulling仅查询缓存，refresh当缓存miss或者交易状态非成功时去支付渠道端查询，forece_remote强制去远端查询 | ---------------- | 
| out_trade_no | string(64) | 是 | MCF开头的交易单号 | ---------------- | 

##get_hot_txns

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/get_hot_txns/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 
| page_size | int(1,50) | 否 | page size | 20 | 

##get_settlements

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/get_settlements/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 
| page_size | int(1,50) | 否 | page size | 20 | 

##query_txns_by_time

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/query_txns_by_time/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| start_time | int | 是 | 开始时间的unix timestamp, inclusive | ---------------- | 
| end_time | int | 是 | 结束时间的unix timestamp, exclusive | ---------------- | 
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 
| page_size | int(1,50) | 否 | page size | 20 | 

##create_refund

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/create_refund/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| device_id | string(64) | 是 | ---------------- | ---------------- | 
| refund_no | int(1,1) | 是 | 第几笔退款，目前仅支持1笔退款 | ---------------- | 
| refund_fee_in_cent | int | 是 | 退款金额，以分为单位的整数 | ---------------- | 
| refund_fee_currency | string(16) | 是 | 退款币种，必须与标价金额的币种一致 | ---------------- | 
| total_fee_in_cent | int | 是 | 标价金额，以分为单位的整数 | ---------------- | 
| total_fee_currency | string(16) | 是 | 标价金额的币种 | ---------------- | 
| out_trade_no | string(64) | 是 | MCF开头的交易单号 | ---------------- | 

##get_exchange_rate

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/get_exchange_rate/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |
| vendor_channel | string(8) | 是 | 付款渠道，目前支持wx或者ali | ---------------- | 
| currency_type | string(16) | 是 | ---------------- | ---------------- | 

##get_company_info

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/merchant/get_company_info/ |      |
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:
无

