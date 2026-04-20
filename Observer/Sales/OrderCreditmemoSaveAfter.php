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
use Magento\Sales\Model\Order\Creditmemo;

class OrderCreditmemoSaveAfter implements ObserverInterface
{
    public function __construct(
        private readonly WebhookSender $webhookSender,
        private readonly SalesPayloadBuilder $payloadBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo instanceof Creditmemo || !$creditmemo->getOrder()) {
            return;
        }

        $payload = $this->payloadBuilder->buildCreditmemo($creditmemo);
        $payload['order'] = $this->payloadBuilder->buildOrder($creditmemo->getOrder());

        $this->webhookSender->sendEvent(
            'refunds/create',
            'refund_created',
            'refund',
            $payload,
            (string) $creditmemo->getEntityId(),
            sprintf('creditmemo-%s-save-%s', $creditmemo->getEntityId(), strtotime((string) $creditmemo->getUpdatedAt())),
            $creditmemo->getStore()
        );
    }
}

