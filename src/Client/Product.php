<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Interface\ProdcutInterface;
use MonkeysLegion\Stripe\Client\StripeWrapper;

class Product extends StripeWrapper implements ProdcutInterface
{
    public function createProduct(array $params): \Stripe\Product
    {
        return $this->handle(function () use ($params) {
            return $this->stripe->products->create($params);
        });
    }

    public function retrieveProduct(string $productId, array $params = []): \Stripe\Product
    {
        return $this->handle(function () use ($productId, $params) {
            return $this->stripe->products->retrieve($productId, $params);
        });
    }

    public function updateProduct(string $productId, array $params): \Stripe\Product
    {
        return $this->handle(function () use ($productId, $params) {
            return $this->stripe->products->update($productId, $params);
        });
    }

    public function deleteProduct(string $productId, array $options = []): \Stripe\Product
    {
        return $this->handle(function () use ($productId, $options) {
            return $this->stripe->products->delete($productId, $options);
        });
    }

    public function listProducts(array $params = []): \Stripe\Collection
    {
        return $this->handle(function () use ($params) {
            return $this->stripe->products->all($params ?: null);
        });
    }

    public function searchProducts(string $query, array $params = []): \Stripe\SearchResult
    {
        return $this->handle(function () use ($query, $params) {
            return $this->stripe->products->search(array_merge(['query' => $query], $params ?: []));
        });
    }
}
