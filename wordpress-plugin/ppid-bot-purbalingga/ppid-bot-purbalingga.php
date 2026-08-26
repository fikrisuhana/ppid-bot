<?php
/**
 * Plugin Name: PPID Bot Purbalingga
 * Plugin URI:  https://ppid.purbalinggakab.go.id
 * Description: Widget Chatbot AI Cerdas & Pelayanan Informasi Publik untuk PPID Kabupaten Purbalingga dengan Dashboard Kelola FAQ & Template di Admin WordPress.
 * Version:     1.1.0
 * Author:      Dinkominfo Kabupaten Purbalingga
 * Author URI:  https://dinkominfo.purbalinggakab.go.id
 * Text Domain: ppid-bot-purbalingga
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PPID_BOT_DIR', plugin_dir_path(__FILE__));
define('PPID_BOT_URL', plugin_dir_url(__FILE__));

require_once PPID_BOT_DIR . 'includes/rest-api.php';

class PPID_Bot_Purbalingga_Plugin {
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_chatbot_widget'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        register_activation_hook(__FILE__, array($this, 'plugin_activation'));
    }

    public function plugin_activation() {
        // Default initial templates if not set
        if (!get_option('ppid_bot_templates')) {
            $default_templates = array(
                "Bagaimana cara mengajukan permohonan informasi?",
                "Berapa lama proses pelayanan permohonan informasi?",
                "Berapa biaya pengajuan informasi publik?",
                "Bagaimana alur pengajuan keberatan informasi?",
                "Apa saja kategori informasi publik yang tersedia?",
                "Di mana alamat dan kontak layanan PPID Purbalingga?"
            );
            update_option('ppid_bot_templates', $default_templates);
        }

        // Default initial FAQs if not set
        if (!get_option('ppid_bot_faqs')) {
            $default_faqs = array(
                array(
                    "keywords" => "cara permohonan, syarat informasi, alur permohonan, mengajukan informasi, prosedur",
                    "question" => "Bagaimana cara mengajukan permohonan informasi publik di PPID Purbalingga?",
                    "answer"   => "Permohonan informasi publik di PPID Kabupaten Purbalingga dapat diajukan secara online maupun offline:\n\n1. **Online**: Kunjungi website resmi PPID Purbalingga lalu isi formulir Permohonan Informasi Publik online.\n2. **Offline**: Datang ke Desk Layanan PPID Utama di Kantor Dinkominfo Kab. Purbalingga dengan membawa identitas resmi (KTP untuk perorangan, Akta/SK untuk Lembaga).\n3. Petugas akan memverifikasi permohonan dan memberikan tanda bukti penerimaan.",
                    "links"    => "Formulir Permohonan Informasi Online | https://ppid.purbalinggakab.go.id/permohonan-informasi"
                ),
                array(
                    "keywords" => "biaya, tarif, bayar, gratis, ongkos",
                    "question" => "Berapa biaya untuk permohonan informasi publik?",
                    "answer"   => "Pelayanan informasi publik di PPID Kabupaten Purbalingga adalah **GRATIS (Rp 0)**. Pemohon tidak dipungut biaya pendaftaran atau administrasi.\n\n*Catatan*: Jika pemohon meminta penggandaan fisik (fotokopi), biaya ditanggung pemohon sesuai ketentuan.",
                    "links"    => "Standar Biaya Layanan PPID | https://ppid.purbalinggakab.go.id/standar-biaya"
                ),
                array(
                    "keywords" => "waktu, berapa lama, jangka waktu, durasi proses, hari kerja",
                    "question" => "Berapa lama jangka waktu proses pelayanan informasi?",
                    "answer"   => "Sesuai UU KIP No. 14 Tahun 2008 dan Standar Layanan PPID Purbalingga:\n\n* **Pemberitahuan Tertulis**: Maksimal **10 (sepuluh) hari kerja** sejak permohonan dinyatakan lengkap.\n* **Perpanjangan Waktu**: Dapat diperpanjang maksimal **7 (tujuh) hari kerja** dengan pemberitahuan tertulis.",
                    "links"    => "SOP Layanan Informasi | https://ppid.purbalinggakab.go.id/sop-layanan"
                ),
                array(
                    "keywords" => "keberatan, sengketa, tidak puas, komplain, alur keberatan",
                    "question" => "Bagaimana prosedur pengajuan keberatan informasi publik?",
                    "answer"   => "Jika permohonan ditolak atau tidak ditanggapi dalam 10 hari kerja, pemohon berhak mengajukan **Keberatan Informasi** kepada Atasan PPID dalam waktu paling lambat **30 hari kerja**. Atasan PPID wajib memberikan tanggapan tertulis paling lambat **30 hari kerja**.",
                    "links"    => "Formulir Pengajuan Keberatan | https://ppid.purbalinggakab.go.id/pengajuan-keberatan"
                ),
                array(
                    "keywords" => "jam layanan, kantor, alamat, kontak, lokasi, telepon",
                    "question" => "Di mana lokasi kantor PPID dan jam operasional layanannya?",
                    "answer"   => "Desk Layanan PPID Utama Kab. Purbalingga beralamat di:\n📍 **Dinas Komunikasi dan Informatika Kab. Purbalingga**\nJl. Letkol Isdiman No. 17A, Purbalingga, Jawa Tengah 53313\n\n🕒 **Jam Layanan**: Senin - Kamis (07.30 - 15.30 WIB), Jumat (07.30 - 14.30 WIB).",
                    "links"    => "Kontak & Peta PPID | https://ppid.purbalinggakab.go.id/kontak"
                )
            );
            update_option('ppid_bot_faqs', $default_faqs);
        }
    }

    public function enqueue_assets() {
        wp_enqueue_style('ppid-bot-style', PPID_BOT_URL . 'assets/css/ppid-chatbot.css', array(), '1.1.0');
        wp_enqueue_script('ppid-bot-script', PPID_BOT_URL . 'assets/js/ppid-chatbot.js', array(), '1.1.0', true);

        wp_localize_script('ppid-bot-script', 'ppid_bot_config', array(
            'apiUrl'         => rest_url('chatbot/v1/ask'),
            'templatesUrl'   => rest_url('chatbot/v1/templates'),
            'nonce'          => wp_create_nonce('wp_rest'),
            'portalUrl'      => 'https://ppid.purbalinggakab.go.id'
        ));
    }

    public function render_chatbot_widget() {
        ?>
        <div id="ppid-chatbot-container">
            <div class="ppid-chatbot-toggle-container" id="ppidToggleContainer">
                <button class="ppid-chatbot-toggle" id="ppidChatbotToggle" aria-label="Buka Chatbot">
                    <span class="toggle-icon">💬</span>
                    <span>Tanya PPIDbot</span>
                </button>
            </div>

            <div id="ppid-floating-chatbot">
                <div class="ppid-chatbot-header">
                    <div class="ppid-chatbot-brand">
                        <div class="ppid-chatbot-avatar">🤖</div>
                        <div class="ppid-chatbot-info">
                            <h3>PPIDbot Purbalingga</h3>
                            <p>Layanan Informasi Publik</p>
                        </div>
                    </div>
                    <button class="ppid-close-btn" id="ppidCloseBtn" title="Tutup Chat">&times;</button>
                </div>

                <div class="ppid-chatbot-body" id="ppidChatBody">
                    <div class="ppid-message bot">
                        <div class="ppid-bubble">
                            Halo Sedulur Purbalingga! 👋<br>
                            Saya <strong>PPIDbot</strong>, asisten pintar PPID Kabupaten Purbalingga. Ada yang bisa saya bantu terkait layanan permohonan informasi publik?
                        </div>
                    </div>
                </div>

                <div class="ppid-typing" id="ppidTyping">
                    <div class="ppid-typing-dot"></div>
                    <div class="ppid-typing-dot"></div>
                    <div class="ppid-typing-dot"></div>
                </div>

                <div class="ppid-quick-accordion" id="ppidQuickAccordion">
                    <div class="ppid-accordion-header" id="ppidAccordionHeader">
                        <span>⚡ Pertanyaan Template</span>
                        <span>▾</span>
                    </div>
                    <div class="ppid-accordion-content" id="ppidAccordionContent"></div>
                </div>

                <div class="ppid-chatbot-footer">
                    <input type="text" id="ppidChatInput" class="ppid-chat-input" placeholder="Tulis pertanyaan seputar PPID...">
                    <button id="ppidSendBtn" class="ppid-send-btn" title="Kirim Pesan">➤</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_admin_menu() {
        add_menu_page(
            'PPID Bot Purbalingga',
            'PPID Bot',
            'manage_options',
            'ppid-bot-admin',
            array($this, 'render_admin_dashboard'),
            'dashicons-format-chat',
            30
        );
    }

    public function register_settings() {
        register_setting('ppid_bot_options_group', 'ppid_bot_backend_url');
        register_setting('ppid_bot_options_group', 'ppid_bot_templates');
        register_setting('ppid_bot_options_group', 'ppid_bot_faqs');
    }

    public function render_admin_dashboard() {
        // Handle Save
        if (isset($_POST['ppid_bot_save_all']) && check_admin_referer('ppid_bot_admin_nonce')) {
            $backend_url = sanitize_text_field($_POST['ppid_bot_backend_url']);
            update_option('ppid_bot_backend_url', $backend_url);

            // Process Templates
            $templates_raw = sanitize_textarea_field($_POST['ppid_templates_text']);
            $templates_array = array_filter(array_map('trim', explode("\n", $templates_raw)));
            update_option('ppid_bot_templates', array_values($templates_array));

            // Process FAQs
            $faqs_json = wp_unslash($_POST['ppid_faqs_json']);
            $decoded_faqs = json_decode($faqs_json, true);
            if (is_array($decoded_faqs)) {
                update_option('ppid_bot_faqs', $decoded_faqs);
            }

            echo '<div class="notice notice-success is-dismissible"><p>✅ Pengaturan & Database FAQ PPIDbot berhasil disimpan!</p></div>';
        }

        $backend_url = get_option('ppid_bot_backend_url', 'http://127.0.0.1:5000/ask');
        $templates = get_option('ppid_bot_templates', array());
        $faqs = get_option('ppid_bot_faqs', array());
        ?>
        <div class="wrap">
            <h1>🤖 Kelola PPID Bot Purbalingga</h1>
            <p>Kelola pertanyaan template, database FAQ lokal, dan konfigurasi server backend bot tanpa perlu koding.</p>

            <form method="post" action="">
                <?php wp_nonce_field('ppid_bot_admin_nonce'); ?>

                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px;">
                    <h2>⚙️ Konfigurasi Server AI</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">URL Backend AI (Python FastAPI):</th>
                            <td>
                                <input type="text" name="ppid_bot_backend_url" value="<?php echo esc_attr($backend_url); ?>" style="width:100%; max-width:500px;" />
                                <p class="description">Default lokal: <code>http://127.0.0.1:5000/ask</code></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px;">
                    <h2>⚡ Tombol Pertanyaan Template (Accordion)</h2>
                    <p class="description">Tulis satu pertanyaan per baris. Pertanyaan ini akan langsung muncul sebagai tombol template yang bisa diklik user:</p>
                    <textarea name="ppid_templates_text" rows="6" style="width:100%; font-family:monospace;"><?php echo esc_textarea(implode("\n", $templates)); ?></textarea>
                </div>

                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px;">
                    <h2>📚 Kelola Database Tanya-Jawab (FAQ)</h2>
                    <p class="description">Tambah dan edit pertanyaan serta jawaban resmi yang otomatis dikenali oleh PPIDbot:</p>
                    
                    <div id="faq-list-container">
                        <!-- Rendered by JS -->
                    </div>
                    <button type="button" class="button" id="btn-add-faq" style="margin-top:10px;">➕ Tambah FAQ Baru</button>

                    <input type="hidden" name="ppid_faqs_json" id="ppid_faqs_json" value="<?php echo esc_attr(wp_json_encode($faqs)); ?>" />
                </div>

                <p class="submit">
                    <input type="submit" name="ppid_bot_save_all" class="button button-primary button-large" value="💾 Simpan Semua Perubahan" />
                </p>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            let faqsData = <?php echo wp_json_encode($faqs); ?> || [];
            const container = document.getElementById('faq-list-container');
            const hiddenInput = document.getElementById('ppid_faqs_json');
            const btnAdd = document.getElementById('btn-add-faq');

            function renderFaqs() {
                container.innerHTML = '';
                faqsData.forEach((item, index) => {
                    const box = document.createElement('div');
                    box.style.cssText = 'background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:6px; margin-bottom:12px; position:relative;';
                    box.innerHTML = `
                        <button type="button" class="button button-link-delete" style="position:absolute; right:10px; top:10px; color:#a00;" onclick="removeFaq(${index})">❌ Hapus</button>
                        <h4 style="margin:0 0 10px 0;">FAQ #${index + 1}</h4>
                        <div style="margin-bottom:8px;">
                            <label><strong>Pertanyaan:</strong></label><br>
                            <input type="text" style="width:100%;" value="${escapeHtml(item.question || '')}" onchange="updateFaq(${index}, 'question', this.value)">
                        </div>
                        <div style="margin-bottom:8px;">
                            <label><strong>Kata Kunci Pencarian (Pisahkan dengan koma):</strong></label><br>
                            <input type="text" style="width:100%;" value="${escapeHtml(item.keywords || '')}" onchange="updateFaq(${index}, 'keywords', this.value)">
                        </div>
                        <div style="margin-bottom:8px;">
                            <label><strong>Jawaban:</strong></label><br>
                            <textarea rows="3" style="width:100%;" onchange="updateFaq(${index}, 'answer', this.value)">${escapeHtml(item.answer || '')}</textarea>
                        </div>
                        <div>
                            <label><strong>Tautan Terkait (Format: Judul | URL):</strong></label><br>
                            <input type="text" style="width:100%;" value="${escapeHtml(item.links || '')}" onchange="updateFaq(${index}, 'links', this.value)">
                        </div>
                    `;
                    container.appendChild(box);
                });
                hiddenInput.value = JSON.stringify(faqsData);
            }

            window.updateFaq = function(idx, key, val) {
                faqsData[idx][key] = val;
                hiddenInput.value = JSON.stringify(faqsData);
            };

            window.removeFaq = function(idx) {
                if (confirm('Hapus FAQ ini?')) {
                    faqsData.splice(idx, 1);
                    renderFaqs();
                }
            };

            btnAdd.addEventListener('click', function() {
                faqsData.push({
                    question: 'Pertanyaan baru?',
                    keywords: 'kata, kunci',
                    answer: 'Tulis jawaban di sini...',
                    links: ''
                });
                renderFaqs();
            });

            function escapeHtml(text) {
                return (text || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            }

            renderFaqs();
        });
        </script>
        <?php
    }
}

new PPID_Bot_Purbalingga_Plugin();
