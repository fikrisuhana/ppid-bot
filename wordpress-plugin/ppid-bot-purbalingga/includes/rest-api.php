<?php
/**
 * REST API Endpoints Bridge for PPID Bot Purbalingga
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('chatbot/v1', '/ask', array(
        'methods'             => array('POST', 'GET'),
        'callback'            => 'ppid_bot_handle_ask_request',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('chatbot/v1', '/templates', array(
        'methods'             => 'GET',
        'callback'            => 'ppid_bot_handle_templates_request',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('chatbot/v1', '/test', array(
        'methods'             => 'GET',
        'callback'            => 'ppid_bot_handle_test_request',
        'permission_callback' => '__return_true'
    ));
});

function ppid_bot_handle_templates_request() {
    $templates = get_option('ppid_bot_templates', array(
        "Bagaimana cara mengajukan permohonan informasi?",
        "Berapa lama proses pelayanan permohonan informasi?",
        "Berapa biaya pengajuan informasi publik?",
        "Bagaimana alur pengajuan keberatan informasi?"
    ));
    return new WP_REST_Response(array('templates' => $templates), 200);
}

function ppid_bot_handle_ask_request(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $question = isset($params['question']) ? sanitize_text_field($params['question']) : '';

    if (empty($question)) {
        return new WP_Error('empty_question', 'Pertanyaan tidak boleh kosong', array('status' => 400));
    }

    // 1. Cek langsung ke database FAQ WordPress yang diatur Admin
    $wp_faqs = get_option('ppid_bot_faqs', array());
    $clean_q = strtolower(trim($question));

    foreach ($wp_faqs as $faq) {
        $keywords = array_map('trim', explode(',', strtolower($faq['keywords'] ?? '')));
        foreach ($keywords as $kw) {
            if (!empty($kw) && (strpos($clean_q, $kw) !== false || levenshtein($clean_q, strtolower($faq['question'])) < 6)) {
                $links_array = array();
                if (!empty($faq['links'])) {
                    $parts = explode('|', $faq['links']);
                    $links_array[] = array(
                        'title' => trim($parts[0]),
                        'url'   => isset($parts[1]) ? trim($parts[1]) : 'https://ppid.purbalinggakab.go.id'
                    );
                }
                return new WP_REST_Response(array(
                    'status'     => 'success',
                    'answer'     => $faq['answer'],
                    'links'      => $links_array,
                    'source'     => 'wp_admin_db',
                    'confidence' => 1.0
                ), 200);
            }
        }
    }

    // 2. Jika tidak ada kecocokan di WP DB, forward ke Backend Python (RAG / DeepSeek)
    $backend_url = get_option('ppid_bot_backend_url', 'http://127.0.0.1:5000/ask');

    $response = wp_remote_post($backend_url, array(
        'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
        'body'        => wp_json_encode(array('question' => $question, 'env' => 'ppid')),
        'method'      => 'POST',
        'data_format' => 'body',
        'timeout'     => 15,
    ));

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($data) {
            return new WP_REST_Response($data, 200);
        }
    }

    // 3. Default Fallback
    return new WP_REST_Response(array(
        'status'  => 'success',
        'answer'  => 'Mohon maaf, saya belum menemukan informasi spesifik terkait hal tersebut di basis data PPID Purbalingga. Silakan hubungi langsung Desk Layanan PPID Utama Dinkominfo Purbalingga di (0281) 891040.',
        'links'   => array(
            array('title' => 'Portal Resmi PPID Purbalingga', 'url' => 'https://ppid.purbalinggakab.go.id'),
            array('title' => 'Kanal Aduan & Kontak', 'url' => 'https://ppid.purbalinggakab.go.id/kontak')
        ),
        'source'  => 'fallback'
    ), 200);
}

function ppid_bot_handle_test_request() {
    return new WP_REST_Response(array(
        'status'    => 'success',
        'message'   => 'PPID Bot Purbalingga REST API is active',
        'timestamp' => current_time('mysql')
    ), 200);
}
