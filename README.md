WooCommerce PayOp Payment Gateway
=====================

## Brief Description

Add the ability to accept payments in WooCommerce via Payop.com.

 


## Request example POST

<img src="https://img.shields.io/badge/-POST-green" />

```shell script
curl -X GET \
  https://payop.com/v1/instrument-settings/payment-methods/available-for-application/0a4e9324-1213-4ee2-aa91-15b2b8dfa56d \
    -H 'Content-Type: application/json' \
    -H 'Authorization: Bearer eyJ0eXAiOiJKV...'
```

## Request example GET

<img src="https://img.shields.io/badge/-GET-blue" />

```shell script
curl -X POST \
  https://payop.com/v1/instrument-settings/payment-methods/available-for-application/0a4e9324-1213-4ee2-aa91-15b2b8dfa56d \
    -H 'Content-Type: application/json' \
    -H 'Authorization: Bearer eyJ0eXAiOiJKV...'
```

## Response example 

<img src="https://img.shields.io/badge/Response-SUCCESS-green" />

```shell script
HTTP/1.1 200 OK
Content-Type: application/json
token: eyJ0eXAiOiJKV...
```

<img src="https://img.shields.io/badge/Response-ERROR-yellowgreen" />

```shell script
HTTP/1.1 404 ERROR
Content-Type: application/json
token: eyJ0eXAiOiJKV...
```


