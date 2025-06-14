<?php

namespace MonkeysLegion\Stripe\Interface;

interface ProdcutInterface
{
    /**
     * Create a new product.
     *
     * @param array<string, mixed> $params Parameters for the product.
     * @return \Stripe\Product
     * @throws \Exception if the creation fails
     */
    public function createProduct(array $params): \Stripe\Product;

    /**
     * Retrieve a product by its ID.
     *
     * @param string $productId The ID of the product to retrieve.
     * @return \Stripe\Product
     * @throws \Exception if the retrieval fails
     */
    public function retrieveProduct(string $productId, array $options = []): \Stripe\Product;

    /**
     * Update a product.
     *
     * @param string $productId The ID of the product to update.
     * @param array<string, mixed> $params Parameters to update the product with.
     * @return \Stripe\Product
     * @throws \Exception if the update fails
     */
    public function updateProduct(string $productId, array $params): \Stripe\Product;

    /**
     * Delete a product.
     *
     * @param string $productId The ID of the product to delete.
     * @return \Stripe\Product
     * @throws \Exception if the deletion fails
     */
    public function deleteProduct(string $productId, array $options = []): \Stripe\Product;

    /**
     * List all products.
     *
     * @param array<string, mixed> $params Optional parameters for listing products.
     * @return \Stripe\Collection<\Stripe\Product>
     * @throws \Exception if the listing fails
     */
    public function listProducts(array $params = []): \Stripe\Collection;

    /**
     * Search for products using a query.
     *
     * @param string $query The search query.
     * @param array<string, mixed> $params Optional parameters for the search.
     * @return \Stripe\Collection<\Stripe\Product>
     * @throws \Exception if the search fails
     */
    public function searchProducts(string $query, array $params = []): \Stripe\SearchResult;
}
