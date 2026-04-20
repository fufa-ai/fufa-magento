<?php
/**
 * Copyright © 2026 Fufa All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Fufa\Webhook\Observer\Sales;

use Fufa\Webhook\Service\Payload\SalesPayloadBuilder;
use Fufa\Webhook\Service\WebhookSender;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class OrderPlaceAfter implements ObserverInterface
{
    public function __construct(
        private readonly WebhookSender $webhookSender,
        private readonly SalesPayloadBuilder $payloadBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order) {
            return;
        }

        $incrementId = (string) $order->getIncrementId();
        $updatedAtUnix = (int) strtotime((string) $order->getUpdatedAt()) ?: time();

        $this->webhookSender->sendEvent(
            'orders/create',
            'order_created',
            'order',
            $this->payloadBuilder->buildOrder($order),
            $incrementId,
            sprintf('order-%s-create-%s', $incrementId, $updatedAtUnix),
            $order->getStore()
        );
    }
}

