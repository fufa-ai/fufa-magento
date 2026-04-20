<?php
/**
 * Copyright © 2026 Fufa All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Fufa\Webhook\Cron;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Fufa\Webhook\Model\Config;
use Fufa\Webhook\Service\Payload\SalesPayloadBuilder;
use Fufa\Webhook\Service\WebhookSender;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Psr\Log\LoggerInterface;

class AbandonedCartChecker
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly QuoteCollectionFactory $quoteCollectionFactory,
        private readonly SalesPayloadBuilder $payloadBuilder,
        private readonly WebhookSender $webhookSender
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $thresholdMinutes = $this->config->getAbandonThresholdMinutes();
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->sub(new DateInterval(sprintf('PT%dM', $thresholdMinutes)))
            ->format('Y-m-d H:i:s');

        $maxAge = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->sub(new DateInterval('P1D'))
            ->format('Y-m-d H:i:s');

        $sentCount = 0;
        $currentPage = 1;

        while (true) {
            $collection = $this->quoteCollectionFactory->create();
            $collection->addFieldToFilter('is_active', 1);
            $collection->addFieldToFilter('items_count', ['gt' => 0]);
            $collection->addFieldToFilter('updated_at', ['lteq' => $cutoff]);
            $collection->addFieldToFilter('updated_at', ['gteq' => $maxAge]);
            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize(200);
            $collection->setCurPage($currentPage);

            if (!$collection->getSize()) {
                break;
            }

            /** @var Quote $quote */
            foreach ($collection as $quote) {
                if ($quote->getReservedOrderId()) {
                    continue;
                }

                $payload = $this->payloadBuilder->buildQuote($quote);
                $email = trim((string) ($payload['customer_email'] ?? ''));
                $phone = trim((string) ($payload['customer_phone'] ?? ''));

                if ($email === '' && $phone === '') {
                    continue;
                }

                $sent = $this->webhookSender->sendEvent(
                    'carts/abandoned',
                    'cart_abandoned',
                    'cart',
                    $payload,
                    (string) $quote->getId(),
                    sprintf('quote-%s-abandoned-%s', $quote->getId(), strtotime((string) $quote->getUpdatedAt())),
                    $quote->getStore()
                );
                if ($sent) {
                    $sentCount++;
                }
            }

            if ($currentPage >= (int) $collection->getLastPageNumber()) {
                break;
            }

            $currentPage++;
            $collection->clear();
        }

        $this->logger->info('Fufa abandoned cart cron completed.', [
            'quotes_sent' => $sentCount,
            'threshold_minutes' => $thresholdMinutes,
        ]);
    }
}

