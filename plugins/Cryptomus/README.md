# Cryptomus for XBoard

XBoard 支付插件。通过 Cryptomus 托管收银台收取加密货币，并使用 Cryptomus Webhook 自动完成订单。

## 配置

- `Merchant UUID`：Cryptomus 商户 UUID，不是 User API Key。
- `Payment API Key`：商户支付 API 密钥。
- `API 地址`：默认 `https://api.cryptomus.com/v1`。
- `法币计价单位`：锌元素使用 `CNY`。
- `订单有效期`：默认 3600 秒。

插件仅把 `paid`、`paid_over` 状态视为支付成功，并验证 Cryptomus 回调签名。
