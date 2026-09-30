<?php

namespace Plugin\Waffo;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    private const BRIDGE_URL = 'http://127.0.0.1:18792';

    public function boot(): void
    {
        $this->filter('available_payment_methods', function (array $methods): array {
            if ($this->getConfig('enabled', true)) {
                $methods['Waffo'] = [
                    'name' => $this->getConfig('display_name', 'Waffo'),
                    'icon' => $this->getConfig('icon', 'W'),
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
            'bridge_token' => [
                'label' => 'Bridge Token',
                'type' => 'string',
                'required' => true,
                'description' => 'Waffo localhost bridge internal token',
            ],
        ];
    }

    public function pay($order): array
    {
        $response = $this->post('/checkout', [
            'trade_no' => (string) $order['trade_no'],
            'total_amount' => (int) $order['total_amount'],
            'user_id' => (string) $order['user_id'],
            'return_url' => (string) $order['return_url'],
        ]);
        $url = $response['checkout_url'] ?? null;
        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            throw new ApiException('Waffo 未返回有效支付地址');
        }
        return ['type' => 1, 'data' => $url];
    }

    public function notify($params): array|bool
    {
        $response = $this->post('/verify-webhook', [
            'raw_body' => (string) request()->getContent(),
            'signature' => (string) request()->header('X-Waffo-Signature', ''),
        ]);
        if (($response['paid'] ?? false) !== true) {
            return false;
        }
        $tradeNo = (string) ($response['trade_no'] ?? '');
        $callbackNo = (string) ($response['callback_no'] ?? '');
        if ($tradeNo === '' || $callbackNo === '') {
            return false;
        }
        return [
            'trade_no' => $tradeNo,
            'callback_no' => $callbackNo,
            'custom_result' => 'ok',
        ];
    }

    private function post(string $path, array $payload): array
    {
        $token = trim((string) $this->getConfig('bridge_token'));
        if ($token === '') {
            throw new ApiException('Waffo Bridge Token 未配置');
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new ApiException('Waffo 请求编码失败');
        }

        $curl = curl_init(self::BRIDGE_URL . $path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        $raw = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $response = json_decode((string) $raw, true);
        if ($raw === false || $error !== '' || $status < 200 || $status >= 300 || !is_array($response)) {
            throw new ApiException('Waffo Bridge 请求失败');
        }
        return $response;
    }
}
