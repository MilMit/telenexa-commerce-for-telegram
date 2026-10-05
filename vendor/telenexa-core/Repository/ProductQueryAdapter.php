<?php

namespace TeleNexa\Repository;

/** Compatibility iterator for legacy Telegram product renderers. */
class ProductQueryAdapter {
    public $max_num_pages = 1;
    public $post_count = 0;
    private $products = [];
    private $index = 0;

    public function __construct(array $products, $total = 0, $max_pages = 1) {
        $this->products = array_values($products);
        $this->post_count = count($this->products);
        $this->max_num_pages = max(1, (int) $max_pages);
    }

    public function have_posts() {
        return isset($this->products[$this->index]);
    }

    public function the_post() {
        $product = $this->products[$this->index++];
        $GLOBALS['product'] = $product;
        $GLOBALS['post'] = get_post($product->get_id());
        return $product;
    }
}
