
## get_merchants

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/get_merchants/ |      |
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
| page_size | int(1,50) | 否 | ---------------- | 20 | 
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 


## get_merchant_info

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/get_merchant_info/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| category | string | 是 | enum(basic,user,device,channel,contract) | ---------------- | 
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 
| page_size | int(1,50) | 否 | ---------------- | 20 | 


## set_merchant_basic

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/set_merchant_basic/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| display_name | string | 否 | ---------------- | ---------------- | 
| legal_name | string | 否 | ---------------- | ---------------- | 
| contact_person | string | 否 | ---------------- | ---------------- | 
| email | string | 否 | ---------------- | ---------------- | 
| cell | string | 否 | ---------------- | ---------------- | 
| address | string | 否 | ---------------- | ---------------- | 
| city | string | 否 | ---------------- | ---------------- | 
| province | string | 否 | ---------------- | ---------------- | 
| postal | string | 否 | ---------------- | ---------------- | 
| timezone | string | 否 | ---------------- | ---------------- | 


## set_merchant_contract

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/set_merchant_contract/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| contract_price | string | 否 | ---------------- | ---------------- | 
| tip_mode | string | 否 | ---------------- | ---------------- | 
| remit_min_in_cent | int | 否 | ---------------- | ---------------- | 
| start_date | string | 否 | ---------------- | ---------------- | 
| end_date | string | 否 | ---------------- | ---------------- | 
| note | string | 否 | ---------------- | ---------------- | 
| bank_instit | string | 否 | ---------------- | ---------------- | 
| bank_transit | string | 否 | ---------------- | ---------------- | 
| bank_account | string | 否 | ---------------- | ---------------- | 
| is_deleted | int | 否 | ---------------- | ---------------- | 


## set_merchant_device

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/set_merchant_device/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| device_id | string | 是 | ---------------- | ---------------- | 
| is_deleted | int | 否 | ---------------- | ---------------- | 


## set_merchant_user

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/set_merchant_user/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| username | string | 是 | ---------------- | ---------------- | 
| role | int(101,365) | 否 | ---------------- | ---------------- | 
| is_deleted | int | 否 | ---------------- | ---------------- | 


## add_merchant_user

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/add_merchant_user/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| username | string | 是 | ---------------- | ---------------- | 
| role | int(101,666) | 是 | ---------------- | ---------------- | 


## set_merchant_channel

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/set_merchant_channel/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| channel | string | 是 | ---------------- | ---------------- | 
| sub_mch_id | string | 否 | ---------------- | ---------------- | 
| sub_mch_name | string | 否 | ---------------- | ---------------- | 
| sub_mch_industry | int | 否 | 4 digits code. Food(5812),Shopping(5311),Hotel(7011),Taxi(4121). For the full list, please refer to https://global.alipay.com/help/online/81 | ---------------- | 
| rate | int | 否 | in 1/10000 | ---------------- | 
| is_deleted | int | 否 | ---------------- | ---------------- | 


## get_merchant_settlement

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/get_merchant_settlement/ |      |
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
| account_id | int | 否 | query all accounts if left null | ---------------- | 
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 
| page_size | int(1,50) | 否 | ---------------- | 20 | 


## get_candidate_settle

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/get_candidate_settle/ |      |
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
| page_size | int(1,50) | 否 | ---------------- | 20 | 


## add_settle

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/add_settle/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| end_time | int | 否 | ---------------- | ---------------- | 


## set_settlement

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/set_settlement/ |      |
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
| settle_id | int | 是 | ---------------- | ---------------- | 
| notes | string | 否 | ---------------- | ---------------- | 
| is_remitted | int | 否 | ---------------- | ---------------- | 


## query_txns_by_time

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/query_txns_by_time/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| start_time | int | 是 | 开始时间的unix timestamp, inclusive | ---------------- | 
| end_time | int | 是 | 结束时间的unix timestamp, exclusive | ---------------- | 
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 
| page_size | int(1,50) | 否 | page size | 20 | 


## get_hot_txns

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/get_hot_txns/ |      |
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
| account_id | int | 是 | ---------------- | ---------------- | 
| page_num | int(1,inf) | 否 | starts from 1 | 1 | 
| page_size | int(1,50) | 否 | page size | 20 | 


## create_new_account

|  Tables  |       说明       | 默认值  |
| :------: | :------------: | :--: |
|   URL    | /api/v1/mgt/create_new_account/ |      |
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
| merchant_id | string | 是 | ---------------- | ---------------- | 
| currency_type | string(16) | 否 | ---------------- | CAD | 


