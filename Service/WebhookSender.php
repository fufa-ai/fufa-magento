<?php
declare(strict_types=1);

namespace Fufa\Webhook\Service;

use DateTimeImmutable;
use DateTimeZone;
use Fufa\Webhook\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class WebhookSender
{
    private const MAX_ATTEMPTS = 3;
    private const RETRYABLE_STATUS_CODES = [408, 425, 429, 500, 502, 503, 504];

    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendEvent(
        string $topic,
        string $eventType,
        string $entityType,
        array $rawPayload,
        ?string $externalEntityId = null,
        ?string $externalEventId = null,
        ?StoreInterface $store = null
    ): bool {
        $storeId = $store?->getId();
        if (!$this->config->isEnabled($storeId)) {
            return false;
        }

        $endpointUrl = $this->config->getEndpointUrl($storeId);
        $secret = $this->config->getHmacSecret($storeId);

        if ($endpointUrl === '' || $secret === '') {
            $this->logger->warning('Fufa webhook skipped because endpoint or HMAC secret is not configured.', [
                'topic' => $topic,
                'store_id' => $storeId,
            ]);
            return false;
        }

        $storeDomain = '';
        if ($store !== null) {
            $baseUrl = (string) $store->getBaseUrl();
            $host = parse_url($baseUrl, PHP_URL_HOST);
            $storeDomain = is_string($host) ? $host : '';
        }

        $occurredAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
        $timestamp = (string) time();

        $payload = [
            'platform' => 'magento',
            'topic' => $topic,
            'eventType' => $eventType,
            'entityType' => $entityType,
            'externalEventId' => $externalEventId,
            'externalEntityId' => $externalEntityId,
            'occurredAt' => $occurredAt,
            'store' => $store ? [
                'id' => (int) $store->getId(),
                'code' => (string) $store->getCode(),
                'websiteId' => (int) $store->getWebsiteId(),
                'name' => (string) $store->getName(),
                'domain' => $storeDomain,
            ] : null,
            'rawPayload' => $rawPayload,
        ];

        $body = $this->json->serialize($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Fufa-Magento-Webhook/1.0',
            'X-Fufa-Platform' => 'magento',
            'X-Fufa-Topic' => $topic,
            'X-Fufa-Event-Type' => $eventType,
            'X-Fufa-Entity-Type' => $entityType,
            'X-Fufa-Signature' => $signature,
            'X-Fufa-Signature-Alg' => 'sha256',
            'X-Fufa-Timestamp' => $timestamp,
        ];
        if ($storeDomain !== '') {
            $headers['X-Fufa-Store-Domain'] = $storeDomain;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->curl->setTimeout(10);
                $this->curl->setHeaders($headers);
                $this->curl->post($endpointUrl, $body);

                $status = (int) $this->curl->getStatus();
                if ($status >= 400) {
                    if ($this->shouldRetryStatus($status) && $attempt < self::MAX_ATTEMPTS) {
                        usleep($attempt * 250000);
                        continue;
                    }

                    throw new LocalizedException(__(
                        'Fufa webhook returned HTTP %1.',
                        $status
                    ));
                }

                return true;
            } catch (Throwable $e) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep($attempt * 250000);
                    continue;
                }

                $this->logger->error('Fufa webhook delivery failed.', [
                    'topic' => $topic,
                    'event_type' => $eventType,
                    'entity_type' => $entityType,
                    'store_id' => $storeId,
                    'external_entity_id' => $externalEntityId,
                    'external_event_id' => $externalEventId,
                    'attempts' => $attempt,
                    'exception' => $e,
                ]);
            }
        }

        return false;
    }

    private function shouldRetryStatus(int $status): bool
    {
        return in_array($status, self::RETRYABLE_STATUS_CODES, true);
    }
}
