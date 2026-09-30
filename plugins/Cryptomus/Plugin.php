<?php

namespace Plugin\Cryptomus;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function (array $methods): array {
            if ($this->getConfig('enabled', true)) {
                $methods['Cryptomus'] = [
                    'name' => $this->getConfig('display_name', 'Cryptomus'),
                    'icon' => $this->getConfig('icon', '₿'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin',
                ];
            }

            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'merchant_id' => [
                'label' => 'Merchant UUID',
                'type' => 'string',
                'required' => true,
                'description' => 'Cryptomus 商户设置页面中的 Merchant UUID，不是 User API Key',
            ],
            'payment_api_key' => [
                'label' => 'Payment API Key',
                'type' => 'string',
                'required' => true,
                'description' => 'Cryptomus 商户支付 API 密钥',
            ],
            'api_base_url' => [
                'label' => 'API 地址',
                'type' => 'string',
                'required' => true,
                'default' => 'https://api.cryptomus.com/v1',
                'description' => 'Cryptomus 使用 https://api.cryptomus.com/v1',
            ],
            'currency' => [
                'label' => '法币计价单位',
                'type' => 'string',
                'required' => true,
                'default' => 'CNY',
                'description' => '锌元素当前使用 CNY',
            ],
            'lifetime' => [
                'label' => '订单有效期（秒）',
                'type' => 'number',
                'required' => true,
                'default' => 3600,
                'description' => '允许范围 300-43200，默认 3600 秒',
            ],
        ];
    }

    public function pay($order): array
    {
        $payload = [
            'amount' => number_format($order['total_amount'] / 100, 2, '.', ''),
            'currency' => strtoupper(trim((string) $this->getConfig('currency', 'CNY'))),
            'order_id' => (string) $order['trade_no'],
            'url_callback' => (string) $order['notify_url'],
            'url_return' => (string) $order['return_url'],
            'url_success' => (string) $order['return_url'],
            'lifetime' => $this->getLifetime(),
            'is_payment_multiple' => false,
        ];

        $response = $this->post('/payment', $payload);
        $paymentUrl = $response['result']['url'] ?? null;

        if (!is_string($paymentUrl) || $paymentUrl === '') {
            throw new ApiException('Cryptomus 未返回支付地址');
        }

        return [
            'type' => 1,
            'data' => $paymentUrl,
        ];
    }

    public function notify($params): array|bool
    {
        $rawBody = trim((string) request()->getContent());
        $data = json_decode($rawBody, true);

        if (!is_array($data) || empty($data['sign'])) {
            return false;
        }

        $receivedSign = (string) $data['sign'];
        unset($data['sign']);

        $expectedSign = $this->sign($data);
        if (!hash_equals($expectedSign, $receivedSign)) {
            return false;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        if (!in_array($status, ['paid', 'paid_over'], true)) {
            return false;
        }

        $tradeNo = (string) ($data['order_id'] ?? '');
        $callbackNo = (string) ($data['uuid'] ?? $data['txid'] ?? '');
        if ($tradeNo === '' || $callbackNo === '') {
            return false;
        }

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $callbackNo,
            'custom_result' => json_encode(['state' => 0], JSON_UNESCAPED_UNICODE),
        ];
    }

    private function post(string $path, array $payload): array
    {
        $merchantId = trim((string) $this->getConfig('merchant_id'));
        $apiKey = trim((string) $this->getConfig('payment_api_key'));
        if ($merchantId === '' || $apiKey === '') {
            throw new ApiException('Cryptomus Merchant UUID 或 Payment API Key 未配置');
        }

        $json = $this->encode($payload);
        $baseUrl = rtrim((string) $this->getConfig('api_base_url', 'https://api.cryptomus.com/v1'), '/');

        $curl = curl_init($baseUrl . $path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'merchant: ' . $merchantId,
                'sign: ' . md5(base64_encode($json) . $apiKey),
            ],
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $error !== '') {
            throw new ApiException('Cryptomus 网络请求失败');
        }

        $response = json_decode((string) $body, true);
        if (!is_array($response)) {
            throw new ApiException('Cryptomus 返回内容无法解析');
        }

        if ($statusCode < 200 || $statusCode >= 300 || (isset($response['state']) && (int) $response['state'] !== 0)) {
            $message = $response['message'] ?? $response['errors'] ?? 'Cryptomus API 请求失败';
            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }
            throw new ApiException((string) $message);
        }

        return $response;
    }

    private function sign(array $payload): string
    {
        return md5(base64_encode($this->encode($payload)) . trim((string) $this->getConfig('payment_api_key')));
    }

    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new ApiException('Cryptomus 请求数据编码失败');
        }

        return $json;
    }

    private function getLifetime(): int
    {
        $lifetime = (int) $this->getConfig('lifetime', 3600);
        return max(300, min(43200, $lifetime));
    }
}
