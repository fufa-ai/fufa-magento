<?php
declare(strict_types=1);

namespace Fufa\Webhook\Service\Payload;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use Magento\Sales\Model\Order\Shipment\Track;

class SalesPayloadBuilder
{
    public function buildOrder(Order $order): array
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        return [
            'id' => (int) $order->getEntityId(),
            'increment_id' => (string) $order->getIncrementId(),
            'quote_id' => $order->getQuoteId() !== null ? (int) $order->getQuoteId() : null,
            'state' => (string) $order->getState(),
            'status' => (string) $order->getStatus(),
            'created_at' => (string) $order->getCreatedAt(),
            'updated_at' => (string) $order->getUpdatedAt(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'grand_total' => (float) $order->getGrandTotal(),
            'subtotal' => (float) $order->getSubtotal(),
            'tax_amount' => (float) $order->getTaxAmount(),
            'discount_amount' => (float) $order->getDiscountAmount(),
            'shipping_amount' => (float) $order->getShippingAmount(),
            'total_paid' => (float) $order->getTotalPaid(),
            'total_refunded' => (float) $order->getTotalRefunded(),
            'total_due' => (float) $order->getTotalDue(),
            'total_canceled' => (float) $order->getTotalCanceled(),
            'shipping_description' => (string) $order->getShippingDescription(),
            'customer_id' => $order->getCustomerId() !== null ? (int) $order->getCustomerId() : null,
            'customer_email' => (string) $order->getCustomerEmail(),
            'customer_phone' => $this->firstNonEmpty(
                $billingAddress?->getTelephone(),
                $shippingAddress?->getTelephone()
            ),
            'customer_firstname' => (string) $order->getCustomerFirstname(),
            'customer_lastname' => (string) $order->getCustomerLastname(),
            'customer_is_guest' => (bool) $order->getCustomerIsGuest(),
            'coupon_code' => $order->getCouponCode() ?: null,
            'billing_address' => $billingAddress ? $this->buildOrderAddress($billingAddress) : null,
            'shipping_address' => $shippingAddress ? $this->buildOrderAddress($shippingAddress) : null,
            'items' => array_values(array_map(
                fn (OrderItem $item): array => $this->buildOrderItem($item),
                $order->getAllVisibleItems()
            )),
        ];
    }

    public function buildShipment(Shipment $shipment): array
    {
        return [
            'id' => (int) $shipment->getEntityId(),
            'increment_id' => (string) $shipment->getIncrementId(),
            'order_id' => (int) $shipment->getOrderId(),
            'created_at' => (string) $shipment->getCreatedAt(),
            'updated_at' => (string) $shipment->getUpdatedAt(),
            'total_qty' => (float) $shipment->getTotalQty(),
            'tracks' => array_values(array_map(
                fn (Track $track): array => [
                    'id' => (int) $track->getEntityId(),
                    'carrier_code' => (string) $track->getCarrierCode(),
                    'title' => (string) $track->getTitle(),
                    'track_number' => (string) $track->getTrackNumber(),
                ],
                $shipment->getAllTracks()
            )),
            'items' => array_values(array_map(
                fn (ShipmentItem $item): array => [
                    'id' => (int) $item->getEntityId(),
                    'order_item_id' => $item->getOrderItemId() !== null ? (int) $item->getOrderItemId() : null,
                    'sku' => (string) $item->getSku(),
                    'name' => (string) $item->getName(),
                    'qty' => (float) $item->getQty(),
                ],
                $shipment->getAllItems()
            )),
        ];
    }

    public function buildCreditmemo(Creditmemo $creditmemo): array
    {
        return [
            'id' => (int) $creditmemo->getEntityId(),
            'increment_id' => (string) $creditmemo->getIncrementId(),
            'order_id' => (int) $creditmemo->getOrderId(),
            'state' => $creditmemo->getState() !== null ? (int) $creditmemo->getState() : null,
            'created_at' => (string) $creditmemo->getCreatedAt(),
            'updated_at' => (string) $creditmemo->getUpdatedAt(),
            'grand_total' => (float) $creditmemo->getGrandTotal(),
            'subtotal' => (float) $creditmemo->getSubtotal(),
            'tax_amount' => (float) $creditmemo->getTaxAmount(),
            'shipping_amount' => (float) $creditmemo->getShippingAmount(),
            'adjustment_positive' => (float) $creditmemo->getAdjustmentPositive(),
            'adjustment_negative' => (float) $creditmemo->getAdjustmentNegative(),
            'items' => array_values(array_map(
                fn (CreditmemoItem $item): array => [
                    'id' => (int) $item->getEntityId(),
                    'order_item_id' => $item->getOrderItemId() !== null ? (int) $item->getOrderItemId() : null,
                    'sku' => (string) $item->getSku(),
                    'name' => (string) $item->getName(),
                    'qty' => (float) $item->getQty(),
                    'row_total' => (float) $item->getRowTotal(),
                ],
                $creditmemo->getAllItems()
            )),
        ];
    }

    public function buildQuote(Quote $quote): array
    {
        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        return [
            'id' => (int) $quote->getId(),
            'store_id' => $quote->getStoreId() !== null ? (int) $quote->getStoreId() : null,
            'is_active' => (bool) $quote->getIsActive(),
            'created_at' => (string) $quote->getCreatedAt(),
            'updated_at' => (string) $quote->getUpdatedAt(),
            'currency' => (string) $quote->getQuoteCurrencyCode(),
            'grand_total' => (float) $quote->getGrandTotal(),
            'subtotal' => (float) $quote->getSubtotal(),
            'items_count' => (int) $quote->getItemsCount(),
            'items_qty' => (float) $quote->getItemsQty(),
            'reserved_order_id' => $quote->getReservedOrderId() ?: null,
            'coupon_code' => $quote->getCouponCode() ?: null,
            'customer_email' => (string) $quote->getCustomerEmail(),
            'customer_phone' => $this->firstNonEmpty(
                $billingAddress?->getTelephone(),
                $shippingAddress?->getTelephone()
            ),
            'customer_firstname' => (string) $quote->getCustomerFirstname(),
            'customer_lastname' => (string) $quote->getCustomerLastname(),
            'billing_address' => $billingAddress ? $this->buildQuoteAddress($billingAddress) : null,
            'shipping_address' => $shippingAddress ? $this->buildQuoteAddress($shippingAddress) : null,
            'items' => array_values(array_map(
                fn (QuoteItem $item): array => $this->buildQuoteItem($item),
                $quote->getAllVisibleItems()
            )),
        ];
    }

    private function buildOrderAddress(OrderAddress $address): array
    {
        return [
            'firstname' => (string) $address->getFirstname(),
            'lastname' => (string) $address->getLastname(),
            'email' => (string) $address->getEmail(),
            'telephone' => (string) $address->getTelephone(),
            'street' => $address->getStreet(),
            'city' => (string) $address->getCity(),
            'region' => (string) $address->getRegion(),
            'postcode' => (string) $address->getPostcode(),
            'country_id' => (string) $address->getCountryId(),
        ];
    }

    private function buildQuoteAddress(QuoteAddress $address): array
    {
        return [
            'firstname' => (string) $address->getFirstname(),
            'lastname' => (string) $address->getLastname(),
            'email' => (string) $address->getEmail(),
            'telephone' => (string) $address->getTelephone(),
            'street' => $address->getStreet(),
            'city' => (string) $address->getCity(),
            'region' => (string) $address->getRegion(),
            'postcode' => (string) $address->getPostcode(),
            'country_id' => (string) $address->getCountryId(),
        ];
    }

    /**
     * Sales order line entity id (sales_order_item.item_id). Avoid (int) null → 0, which breaks downstream unique keys.
     */
    private function resolveOrderItemEntityId(OrderItem $item): ?int
    {
        foreach ([$item->getId(), $item->getItemId(), $item->getEntityId()] as $raw) {
            if ($raw !== null && $raw !== '' && (int) $raw > 0) {
                return (int) $raw;
            }
        }

        return null;
    }

    /**
     * Quote line entity id (quote_item.item_id).
     */
    private function resolveQuoteItemEntityId(QuoteItem $item): ?int
    {
        foreach ([$item->getId(), $item->getItemId(), $item->getEntityId()] as $raw) {
            if ($raw !== null && $raw !== '' && (int) $raw > 0) {
                return (int) $raw;
            }
        }

        return null;
    }

    private function buildOrderItem(OrderItem $item): array
    {
        $lineId = $this->resolveOrderItemEntityId($item);

        return [
            'id' => $lineId,
            'order_item_id' => $lineId,
            'product_id' => $item->getProductId() !== null ? (int) $item->getProductId() : null,
            'sku' => (string) $item->getSku(),
            'name' => (string) $item->getName(),
            'product_type' => (string) $item->getProductType(),
            'qty_ordered' => (float) $item->getQtyOrdered(),
            'price' => (float) $item->getPrice(),
            'row_total' => (float) $item->getRowTotal(),
        ];
    }

    private function buildQuoteItem(QuoteItem $item): array
    {
        $lineId = $this->resolveQuoteItemEntityId($item);

        return [
            'id' => $lineId,
            'product_id' => $item->getProductId() !== null ? (int) $item->getProductId() : null,
            'sku' => (string) $item->getSku(),
            'name' => (string) $item->getName(),
            'product_type' => (string) $item->getProductType(),
            'qty' => (float) $item->getQty(),
            'price' => (float) $item->getPrice(),
            'row_total' => (float) $item->getRowTotal(),
        ];
    }

    private function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
