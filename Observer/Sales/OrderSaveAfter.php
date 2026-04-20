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

class OrderSaveAfter implements ObserverInterface
{
    private const TRACKED_FIELDS = ['status', 'state', 'total_paid', 'total_refunded', 'total_due'];

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

        // Skip the initial insert — OrderPlaceAfter handles order_created.
        if (!$order->getOrigData('entity_id')) {
            return;
        }

        // Only fire if at least one tracked field actually changed.
        if (!$this->hasRelevantChange($order)) {
            return;
        }

        $newState = (string) $order->getState();
        $newStatus = (string) $order->getStatus();
        $updatedAtUnix = (int) strtotime((string) $order->getUpdatedAt());

        $isCancellation = in_array($newState, ['canceled', 'cancelled'], true);

        if ($isCancellation) {
            $topic = 'orders/cancel';
            $eventType = 'order_cancelled';
        } else {
            $topic = 'orders/update';
            $eventType = 'order_updated';
        }

        // Dedupe key encodes state + status + updated_at so financial-only saves
        // (same status, different total_paid) still produce a distinct event id.
        $externalEventId = sprintf(
            'order-%s-update-%s-%s-%s',
            $order->getEntityId(),
            $newState,
            $newStatus,
            $updatedAtUnix
        );

        $this->webhookSender->sendEvent(
            $topic,
            $eventType,
            'order',
            $this->payloadBuilder->buildOrder($order),
            (string) $order->getEntityId(),
            $externalEventId,
            $order->getStore()
        );
    }

    private function hasRelevantChange(Order $order): bool
    {
        foreach (self::TRACKED_FIELDS as $field) {
            $old = $order->getOrigData($field);
            $new = $order->getData($field);
            if ((string) $old !== (string) $new) {
                return true;
            }
        }

        return false;
    }
}
