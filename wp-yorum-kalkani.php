<?php
/**
 * Plugin Name: WP Yorum Güvenlik Kalkanı
 * Description: 7 Katmanlı Spam Koruma.
 * Version: 1.0
 * Author: JRodix Internet Hizmetleri (Omer ATABER - OmerAti)
 */

if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_wp-yorum-kalkani') return;

    wp_enqueue_style('ygk-admin-css', plugin_dir_url(__FILE__) . 'admin/css/admin-style.css', [], '1.1');
    wp_enqueue_script('ygk-admin-js', plugin_dir_url(__FILE__) . 'admin/js/admin-script.js', ['jquery'], '1.1', true);
});

add_action('admin_menu', function () {
    add_menu_page(
        'Yorum Kalkanı',
        'Yorum Kalkanı',
        'manage_options',
        'wp-yorum-kalkani',
        'wp_yorum_kalkani_ayar_sayfasi',
        'dashicons-shield-alt',
        81
    );
});

function wp_yorum_kalkani_ayar_sayfasi() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['ygk_submit']) && check_admin_referer('ygk_save_settings')) {
        update_option('ygk_yasakli_kelimeler', sanitize_textarea_field($_POST['ygk_yasakli_kelimeler']));
        update_option('ygk_yasakli_ipler', sanitize_text_field($_POST['ygk_yasakli_ipler']));
        update_option('ygk_yasakli_linkler', sanitize_text_field($_POST['ygk_yasakli_linkler']));
        update_option('ygk_honeypot_aktif', isset($_POST['ygk_honeypot_aktif']) ? 1 : 0);
        update_option('ygk_min_sure', intval($_POST['ygk_min_sure']));
        echo '<div class="notice notice-success is-dismissible"><p>Ayarlar kaydedildi.</p></div>';
    }

    $kelimeler = get_option('ygk_yasakli_kelimeler', 'viagra,casino,sex,loan,porn,escort');
    $ipler = get_option('ygk_yasakli_ipler', '');
    $linkler = get_option('ygk_yasakli_linkler', 'ru,cn,tk,ml,gq');
    $honeypot = get_option('ygk_honeypot_aktif', 1);
    $min_sure = get_option('ygk_min_sure', 5);

    ?>
    <div class="wrap ygk-admin-wrap">
        <h1>Yorum Güvenlik Kalkanı Ayarları</h1>
        <form method="post" action="" class="ygk-admin-form">
            <?php wp_nonce_field('ygk_save_settings'); ?>
            <table class="form-table ygk-table">
                <tr>
                    <th><label for="ygk_yasakli_kelimeler">Yasaklı Kelimeler (virgülle ayır)</label></th>
                    <td><textarea id="ygk_yasakli_kelimeler" name="ygk_yasakli_kelimeler" rows="4" class="ygk-textarea"><?php echo esc_textarea($kelimeler); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="ygk_yasakli_ipler">Yasaklı IP Adresleri (virgülle ayır)</label></th>
                    <td><input id="ygk_yasakli_ipler" name="ygk_yasakli_ipler" type="text" class="ygk-input" value="<?php echo esc_attr($ipler); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="ygk_yasakli_linkler">Yasaklı Link Uzantıları (virgülle ayır)</label></th>
                    <td><input id="ygk_yasakli_linkler" name="ygk_yasakli_linkler" type="text" class="ygk-input" value="<?php echo esc_attr($linkler); ?>" /></td>
                </tr>
                <tr>
                    <th>Honeypot Koruması Aktif mi?</th>
                    <td><input id="ygk_honeypot_aktif" name="ygk_honeypot_aktif" type="checkbox" value="1" <?php checked($honeypot, 1); ?> /></td>
                </tr>
                <tr>
                    <th><label for="ygk_min_sure">Minimum Sayfa Açılış Süresi (saniye)</label></th>
                    <td><input id="ygk_min_sure" name="ygk_min_sure" type="number" min="1" max="60" class="ygk-input" value="<?php echo intval($min_sure); ?>" /></td>
                </tr>
            </table>
            <p><input type="submit" name="ygk_submit" class="button button-primary ygk-button" value="Ayarları Kaydet"></p>
        </form>
    </div>
    <?php
}

add_action('comment_form', function () {
    $token = md5(time() . rand());
    set_transient('ygk_token_' . $token, time(), 10 * MINUTE_IN_SECONDS);

    echo '<input type="hidden" name="ygk_token" value="' . esc_attr($token) . '">';
    echo '<input type="text" name="ygk_honeypot" value="" style="display:none" tabindex="-1" autocomplete="off">';
    echo '<input type="hidden" name="ygk_js_passed" id="ygk_js_passed" value="0">';
    echo '<script>document.getElementById("ygk_js_passed").value = "1";</script>';
});

function ygk_spam_hata_mesaji($mesaj) {
    $home_url = esc_url(home_url('/'));
    ?>
    <style>
    .ygk-modal-bg {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.75);
        z-index: 9999999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: ygkFadeIn 0.3s ease forwards;
    }
    .ygk-modal {
        background: #fff;
        border-radius: 15px;
        padding: 30px 40px;
        max-width: 420px;
        box-shadow: 0 12px 36px rgba(0,0,0,0.2);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        text-align: center;
        animation: ygkScaleIn 0.3s ease forwards;
    }
    .ygk-modal-title {
        color: #d93025;
        font-weight: 700;
        font-size: 1.8em;
        margin-bottom: 10px;
    }
    .ygk-modal-text {
        font-size: 1.15em;
        color: #333;
        margin-bottom: 25px;
    }
    .ygk-modal-close {
        background: #d93025;
        color: #fff;
        border: none;
        padding: 12px 28px;
        font-weight: 700;
        border-radius: 8px;
        cursor: pointer;
        box-shadow: 0 6px 15px rgba(217,48,37,0.5);
        transition: background-color 0.25s ease;
    }
    .ygk-modal-close:hover {
        background: #a8251b;
    }
    @keyframes ygkFadeIn {
        0% {opacity: 0;}
        100% {opacity: 1;}
    }
    @keyframes ygkScaleIn {
        0% {transform: scale(0.85);}
        100% {transform: scale(1);}
    }
    </style>
    <div id="ygk_modal_bg" class="ygk-modal-bg" role="alertdialog" aria-modal="true" aria-labelledby="ygk_modal_title" aria-describedby="ygk_modal_desc">
        <div id="ygk_modal" class="ygk-modal">
            <h2 id="ygk_modal_title" class="ygk-modal-title">Yorum Engellendi</h2>
            <p id="ygk_modal_desc" class="ygk-modal-text"><?php echo esc_html($mesaj); ?></p>
            <button id="ygk_modal_close" class="ygk-modal-close" aria-label="Kapat">Kapat</button>
        </div>
    </div>
    <script>
    document.body.style.overflow = 'hidden';
    document.getElementById('ygk_modal_close').addEventListener('click', function() {
        window.location.href = '<?php echo $home_url; ?>';
    });
    </script>
    <?php
    exit;
}

add_filter('preprocess_comment', function ($commentdata) {
    $kelimeler = array_map('trim', explode(',', strtolower(get_option('ygk_yasakli_kelimeler', 'viagra,casino,sex,loan,porn,escort'))));
    $ipler = array_map('trim', explode(',', get_option('ygk_yasakli_ipler', '')));
    $linkler = array_map('trim', explode(',', strtolower(get_option('ygk_yasakli_linkler', 'ru,cn,tk,ml,gq'))));
    $honeypot_aktif = get_option('ygk_honeypot_aktif', 1);
    $min_sure = intval(get_option('ygk_min_sure', 5));

    $yorum = strtolower($commentdata['comment_content']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $token = $_POST['ygk_token'] ?? '';
    $honeypot = $_POST['ygk_honeypot'] ?? '';
    $js_passed = $_POST['ygk_js_passed'] ?? '0';

    if ($honeypot_aktif && !empty($honeypot)) {
        ygk_spam_hata_mesaji('Spam algılandı (honeypot).');
    }

    if ($js_passed !== '1') {
        ygk_spam_hata_mesaji('Spam algılandı (JavaScript doğrulaması başarısız).');
    }

    if (empty($token)) {
        ygk_spam_hata_mesaji('Spam algılandı (eksik token).');
    }
    $start = get_transient('ygk_token_' . $token);
    delete_transient('ygk_token_' . $token);
    if (!$start || (time() - $start) < $min_sure) {
        ygk_spam_hata_mesaji('Spam algılandı (çok hızlı gönderim).');
    }

    foreach ($ipler as $engelli_ip) {
        if ($engelli_ip && strpos($ip, $engelli_ip) !== false) {
            ygk_spam_hata_mesaji('Spam algılandı (engelli IP).');
        }
    }

    foreach ($kelimeler as $kelime) {
        if ($kelime && strpos($yorum, $kelime) !== false) {
            ygk_spam_hata_mesaji('Spam algılandı (yasaklı kelime).');
        }
    }

    foreach ($linkler as $uzanti) {
        if ($uzanti && strpos($yorum, '.' . $uzanti) !== false) {
            ygk_spam_hata_mesaji('Spam algılandı (yasaklı link uzantısı).');
        }
    }

    if (preg_match('/[\p{Cyrillic}\p{Arabic}\p{Han}]/u', $yorum)) {
        ygk_spam_hata_mesaji('Spam algılandı (şüpheli karakter).');
    }

    return $commentdata;
});
