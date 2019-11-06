# ScandiPWA persisted query

The main goal of persisted query approach is to reduce the amount of data transfered from the client to the server within a POST body containing GraphQl Document.
However, current module extends the usage of persisted queries to actually cache the responses in Varnish or CDN node
 for fast responses. (avg. 5ms vs 5000ms on dev machine)

## Prerequisites
1. Varnish
2. Redis
3. PHP ext-phpredis is suggested for faster serialization and deserialization 

## Config
### magento setup:config:set
For the convenience there are additional flags available for `php bin/magento setup:config:set` command:

`--pq-host`[mandatory] - persisted query redis host  (`redis` for ScandiPWA docker setup)

`--pq-port`[mandatory] - persisted query redis port (`6379` for ScandiPWA docker setup)

`--pq-database`[mandatory] - persisted query redis database (`5` for ScandiPWA docker setup)

`--pq-scheme`[mandatory] - persisted query redis scheme

`--pq-password`[optional, **empty password is not allowed**] - persisted query redis password

### Manual configuration
Configuration for custom Redis storage, where hashes and GraphQl documents are kept in environment config 
(`app/etc/env.php`) -> `cache/persisted-query` and can be configured manually:
```
	'persisted-query' => [
		'redis' => [
			'host' => 'redis',
			'scheme' => 'tcp',
			'port' => '6379',
			'database' => '5'
		]
	]
```

## Cache control

Available from v1.3.0

CLI command `magento cache:flush` and admin panel `Cache Management` has necessary logic to flush GraphQl responses 
stored in varnish.

`persisted_query_response` - can be disabled, controls `varnish` caches (graphql response caches).

`bin/magento scandipwa:pq:flush` - flushes persisted query REDIS storage(query body)



## Usage
Dynamic persisted query suppose Client to register unknown queries with a series of request-response.

Recommended usage:
1) Optimistically request query execution, referencing query by hash, passing necessary query variables as request 
parameters: `GET 
/graphql?hash=135811058&hideChildren=true`
2) Server has multiple options:
- respond with resolved query (status code `200`)
- respond with Unknown query error (status code `410`)
3) Status code `410` - client must issue PUT request, with the same hash and **entire GraphQl query document within 
the body**. `PUT /graphql?hash=135811058`
4) Server responds with status code `201` on successful query registration.
5) Server will now executes the registered query referenced by hash`GET /graphql?hash=135811058`

In order to effectively utilize persisted query mechanism and avoid unnecessary time-consuming request-response 
interactions GraphQl query must utilize variables.

### Variables
Variables must be passed in pseudo-json format. You must keep the structure, but skip usage of quotes.

#### Array
Array is a list of values, separated with coma, i.e.: `cmsBlocks_identifiers=homepage-promo-categories,homepage-top-items,homepage-about-us`

Let's consider the details:
`cmsBlocks_identifiers` - GraphQl variable name
`homepage-promo-categories,homepage-top-items,homepage-about-us` - Array of values

#### Complex structures
Complex structures must keep structures described with special chars `{`, `}`, `:`.
Array within complex structs MUST use special chars: `[`, `]`.

Example:

`_filter={category_url_path:{eq:men},max_price:{lteq:300},color:{in:[74,75]}}`
Let's consider the details:
`_filter` - GraphQl variable name
`{
	category_url_path:
	{
		eq:men
	},
	max_price:{
		lteq:300
	},
	color:{
		in:[74,75]
	}
}` - "Object", passed as GET parameter. It has not quotation marks, as these are automatically added by the server 
during the processing.

---
### Examples
Query body:

`query ($cmsBlocks_identifiers:[String]) {cmsBlocks:cmsBlocks(identifiers:$cmsBlocks_identifiers){ items{ title, content, identifier } }}`

Request URI:

`GET /graphql?hash=2443957263&cmsBlocks_identifiers=homepage-promo-categories,homepage-top-items,homepage-about-us`

---
Query body:

`"query ($_currentPage:Int!, $_pageSize:Int!, $_filter:ProductFilterInput!, $category_url_path:String!) {products(currentPage:$_currentPage, pageSize:$_pageSize, filter:$_filter){ total_count, items{ id, name, short_description, url_key, special_price, sku, categories{ name, url_path, breadcrumbs{ category_name, category_url_key } }, price{ regularPrice{ amount{ value, currency } }, minimalPrice{ amount{ value, currency } } }, thumbnail, thumbnail_label, small_image, small_image_label, brand, color, size, shoes_size, type_id }, filters{ name, request_var, filter_items{ label, value_string, ... on SwatchLayerFilterItem { label, swatch_data{ type, value } } } } }, category:category(url_path:$category_url_path){ id, name, description, url_path, image, url_key, product_count, meta_title, meta_description, breadcrumbs{ category_name, category_url_key, category_level }, children{ id, name, description, url_path, image, url_key, product_count, meta_title, meta_description, breadcrumbs{ category_name, category_url_key, category_level } } }}"`

Request URI:

`https://scandipwa.local/graphql?hash=1713013963&_currentPage=1&_pageSize=12&_filter={category_url_path:{eq:men},max_price:{lteq:300}}&category_url_path=men`
