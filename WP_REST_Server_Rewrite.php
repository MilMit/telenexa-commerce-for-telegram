<?php
if (!defined('ABSPATH')) exit;

/**
 * TeleNexa custom REST server rewrite wrapper.
 */
class TeleNexa_REST_Server_Rewrite extends WP_REST_Server {
    public function serve_request_rewrite($path = null) {
        if (empty($path)) {
            $path = isset($_SERVER['PATH_INFO']) ? sanitize_text_field(wp_unslash($_SERVER['PATH_INFO'])) : '/';
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';
        $request = new WP_REST_Request($method, $path);
        $result  = $this->dispatch($request);

        if (is_wp_error($result)) {
            $result = $this->error_to_response($result);
        }

        if ('HEAD' === $request->get_method()) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- REST query parameter only controls response embedding.
        $embed = !empty($_GET['_embed']) && sanitize_key(wp_unslash($_GET['_embed'])) !== '';
        return $this->response_to_data($result, $embed);
    }
}

if (!class_exists('WP_REST_Server_Rewrite', false)) {
    class_alias('TeleNexa_REST_Server_Rewrite', 'WP_REST_Server_Rewrite');
}
