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
use Magento\Sales\Model\Order\Shipment;

class OrderShipmentSaveAfter implements ObserverInterface
{
    public function __construct(
        private readonly WebhookSender $webhookSender,
        private readonly SalesPayloadBuilder $payloadBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getShipment();
        if (!$shipment instanceof Shipment || !$shipment->getOrder()) {
            return;
        }

        $orderPayload = $this->payloadBuilder->buildOrder($shipment->getOrder());
        $shipmentPayload = $this->payloadBuilder->buildShipment($shipment);
        $orderPayload['shipments'] = [$shipmentPayload];
        $orderPayload['latest_shipment'] = $shipmentPayload;

        $this->webhookSender->sendEvent(
            'orders/fulfilled',
            'order_fulfilled',
            'order',
            $orderPayload,
            (string) $shipment->getOrderId(),
            sprintf('shipment-%s-save-%s', $shipment->getEntityId(), strtotime((string) $shipment->getUpdatedAt())),
            $shipment->getStore()
        );
    }
}

