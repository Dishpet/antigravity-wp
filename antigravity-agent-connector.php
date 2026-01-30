<?php
/**
 * Plugin Name: Antigravity Agent Connector
 * Description: Provides custom REST API endpoints for Google Antigravity agent to manage WordPress content, WooCommerce products, and theme code.
 * Version: 1.0.0
 * Author: Antigravity
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class Antigravity_Agent_Connector {
    const REST_NAMESPACE = 'antigravity/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/update-post/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_post'),
                'permission_callback' => array(__CLASS__, 'can_edit_posts'),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/update-product/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_product'),
                'permission_callback' => array(__CLASS__, 'can_manage_woocommerce'),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/edit-theme-file',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'edit_theme_file'),
                'permission_callback' => array(__CLASS__, 'can_edit_theme_files'),
                'args'                => array(
                    'file' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'code' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'previous_hash' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/read-theme-file',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'read_theme_file'),
                'permission_callback' => array(__CLASS__, 'can_edit_theme_files'),
                'args'                => array(
                    'file' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
            )
        );
    }

    public static function update_post(WP_REST_Request $request) {
        $post_id = (int) $request['id'];
        $params  = $request->get_json_params();

        if (!is_array($params)) {
            return new WP_Error('invalid_params', 'JSON body is required.', array('status' => 400));
        }

        $update = array('ID' => $post_id);
        $fields = array(
            'title'   => 'post_title',
            'content' => 'post_content',
            'excerpt' => 'post_excerpt',
            'status'  => 'post_status',
            'slug'    => 'post_name',
        );

        foreach ($fields as $param_key => $post_key) {
            if (!array_key_exists($param_key, $params)) {
                continue;
            }

            switch ($param_key) {
                case 'status':
                    $update[$post_key] = sanitize_key((string) $params[$param_key]);
                    break;
                case 'slug':
                    $update[$post_key] = sanitize_title((string) $params[$param_key]);
                    break;
                default:
                    $update[$post_key] = wp_kses_post($params[$param_key]);
                    break;
            }
        }

        if (count($update) === 1) {
            return new WP_Error('no_fields', 'No valid fields provided to update.', array('status' => 400));
        }

        $updated_id = wp_update_post($update, true);
        if (is_wp_error($updated_id)) {
            return $updated_id;
        }

        $post = get_post($updated_id);
        if (!$post) {
            return new WP_Error('missing_post', 'Post not found after update.', array('status' => 404));
        }

        return rest_ensure_response(
            array(
                'id'      => $post->ID,
                'title'   => get_the_title($post),
                'status'  => $post->post_status,
                'updated' => true,
            )
        );
    }

    public static function update_product(WP_REST_Request $request) {
        if (!function_exists('wc_get_product')) {
            return new WP_Error('woocommerce_missing', 'WooCommerce is not active.', array('status' => 400));
        }

        $product_id = (int) $request['id'];
        $params     = $request->get_json_params();

        if (!is_array($params)) {
            return new WP_Error('invalid_params', 'JSON body is required.', array('status' => 400));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found.', array('status' => 404));
        }

        if (array_key_exists('name', $params)) {
            $product->set_name(sanitize_text_field((string) $params['name']));
        }

        if (array_key_exists('description', $params)) {
            $product->set_description(wp_kses_post($params['description']));
        }

        if (array_key_exists('short_description', $params)) {
            $product->set_short_description(wp_kses_post($params['short_description']));
        }

        if (array_key_exists('regular_price', $params)) {
            $product->set_regular_price(wc_format_decimal($params['regular_price']));
        }

        if (array_key_exists('sale_price', $params)) {
            $product->set_sale_price(wc_format_decimal($params['sale_price']));
        }

        if (array_key_exists('manage_stock', $params)) {
            $product->set_manage_stock((bool) $params['manage_stock']);
        }

        if (array_key_exists('stock', $params)) {
            $product->set_stock_quantity((int) $params['stock']);
        }

        if (array_key_exists('stock_status', $params)) {
            $product->set_stock_status(sanitize_key((string) $params['stock_status']));
        }

        $product->save();

        return rest_ensure_response(
            array(
                'id'             => $product->get_id(),
                'name'           => $product->get_name(),
                'regular_price'  => $product->get_regular_price(),
                'sale_price'     => $product->get_sale_price(),
                'stock_quantity' => $product->get_stock_quantity(),
                'updated'        => true,
            )
        );
    }

    public static function edit_theme_file(WP_REST_Request $request) {
        $relative_path = (string) $request->get_param('file');
        $code          = (string) $request->get_param('code');
        $previous_hash = (string) $request->get_param('previous_hash');

        if ($relative_path === '') {
            return new WP_Error('missing_file', 'File parameter is required.', array('status' => 400));
        }

        if ($previous_hash === '') {
            return new WP_Error(
                'missing_previous_hash',
                'previous_hash is required. Read the file before writing.',
                array('status' => 400)
            );
        }

        $relative_path = ltrim($relative_path, '/');
        $theme_dir     = wp_normalize_path(get_stylesheet_directory());
        $target_path   = wp_normalize_path($theme_dir . '/' . $relative_path);

        $real_theme_dir = realpath($theme_dir);
        $real_target    = realpath($target_path);

        if ($real_theme_dir === false || $real_target === false) {
            return new WP_Error('invalid_path', 'File path is invalid.', array('status' => 400));
        }

        $real_theme_dir = wp_normalize_path($real_theme_dir);
        $real_target    = wp_normalize_path($real_target);

        if (strpos($real_target, $real_theme_dir . '/') !== 0) {
            return new WP_Error('path_outside_theme', 'File must be within the active theme directory.', array('status' => 403));
        }

        if (!file_exists($real_target) || !is_file($real_target)) {
            return new WP_Error('file_not_found', 'File does not exist.', array('status' => 404));
        }

        if (!is_writable($real_target)) {
            return new WP_Error('file_not_writable', 'File is not writable.', array('status' => 500));
        }

        $current_contents = file_get_contents($real_target);
        if ($current_contents === false) {
            return new WP_Error('read_failed', 'Unable to read file before writing.', array('status' => 500));
        }

        $current_hash = hash('sha256', $current_contents);
        if (!hash_equals($current_hash, $previous_hash)) {
            return new WP_Error(
                'hash_mismatch',
                'File contents changed since last read. Please read the file again before writing.',
                array('status' => 409)
            );
        }

        $bytes = file_put_contents($real_target, $code, LOCK_EX);
        if ($bytes === false) {
            return new WP_Error('write_failed', 'Unable to write file.', array('status' => 500));
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'file'    => $relative_path,
                'hash'    => hash('sha256', $code),
            )
        );
    }

    public static function read_theme_file(WP_REST_Request $request) {
        $relative_path = (string) $request->get_param('file');

        if ($relative_path === '') {
            return new WP_Error('missing_file', 'File parameter is required.', array('status' => 400));
        }

        $relative_path = ltrim($relative_path, '/');
        $theme_dir     = wp_normalize_path(get_stylesheet_directory());
        $target_path   = wp_normalize_path($theme_dir . '/' . $relative_path);

        $real_theme_dir = realpath($theme_dir);
        $real_target    = realpath($target_path);

        if ($real_theme_dir === false || $real_target === false) {
            return new WP_Error('invalid_path', 'File path is invalid.', array('status' => 400));
        }

        $real_theme_dir = wp_normalize_path($real_theme_dir);
        $real_target    = wp_normalize_path($real_target);

        if (strpos($real_target, $real_theme_dir . '/') !== 0) {
            return new WP_Error('path_outside_theme', 'File must be within the active theme directory.', array('status' => 403));
        }

        if (!file_exists($real_target) || !is_file($real_target)) {
            return new WP_Error('file_not_found', 'File does not exist.', array('status' => 404));
        }

        if (!is_readable($real_target)) {
            return new WP_Error('file_not_readable', 'File is not readable.', array('status' => 500));
        }

        $contents = file_get_contents($real_target);
        if ($contents === false) {
            return new WP_Error('read_failed', 'Unable to read file.', array('status' => 500));
        }

        return rest_ensure_response(
            array(
                'file'    => $relative_path,
                'code'    => $contents,
                'hash'    => hash('sha256', $contents),
                'success' => true,
            )
        );
    }

    public static function can_edit_posts() {
        return current_user_can('edit_posts');
    }

    public static function can_manage_woocommerce() {
        return current_user_can('manage_woocommerce');
    }

    public static function can_edit_theme_files() {
        return current_user_can('edit_theme_options');
    }
}

Antigravity_Agent_Connector::init();
