<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;

interface PaymentProvider
{
    /**
     * 创建支付订单（主扫，返回二维码内容）
     * @return array{qr_code:string,provider_order_no:?string,raw:array}
     */
    public function createOrder(Order $order, string $payMethod, string $clientIp = '127.0.0.1'): array;

    /**
     * 查询订单状态
     * @return array{status:string,provider_trade_no:?string,raw:array}
     */
    public function queryOrder(Order $order): array;

    /**
     * 验证异步通知，返回解析后的数据；失败返回 null
     */
    public function verifyNotify(array $payload): ?array;

    /**
     * 返回 notify 给支付方的成功响应内容
     */
    public function notifySuccessResponse(): string;
}
