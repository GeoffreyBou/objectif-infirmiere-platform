<?php
defined('ABSPATH') || exit;

/** Initial copies are editable WordPress pages; later releases never replace their text. */
final class OI_Legal {
    public static function install(): void {
        if (get_option('oi_legal_pages_version') === '1') { return; }
        $locked_at = get_option('oi_legal_install_lock');
        if ($locked_at !== false && (int)$locked_at < time() - 300) { delete_option('oi_legal_install_lock'); }
        if (!add_option('oi_legal_install_lock', time(), '', false)) { return; }
        try { self::install_pages(); } finally { delete_option('oi_legal_install_lock'); }
    }

    private static function install_pages(): void {
        foreach (['legal'=>['mentions-legales','Mentions légales'], 'privacy'=>['politique-de-confidentialite','Politique de confidentialité']] as $key=>[$slug,$title]) {
            $page = get_page_by_path($slug);
            if ($page) {
                // Preserve any pre-existing document, including unpublished work.
                if ($page->post_status !== 'publish') { return; }
                $id = $page->ID;
            } else {
                $content = file_get_contents(OI_DIR . 'content/' . $slug . '.html');
                if ($content === false) { return; }
                $id = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$title,'post_content'=>wp_kses_post($content)], true);
                if (is_wp_error($id)) { return; }
            }
            update_option('oi_page_' . $key, (int)$id);
        }
        add_option('oi_legal_previous_privacy_page', (int)get_option('wp_page_for_privacy_policy'), '', false);
        update_option('wp_page_for_privacy_policy', (int)get_option('oi_page_privacy'));
        update_option('oi_legal_pages_version', '1');
    }

    public static function render(): string {
        OI_App::assets();
        wp_enqueue_style('oi-landing', OI_URL . 'assets/landing.css', ['oi-base'], OI_VERSION);
        wp_enqueue_style('oi-legal', OI_URL . 'assets/legal.css', ['oi-landing'], OI_VERSION);
        $content = wp_kses_post(apply_filters('the_content', get_post()->post_content));
        // Use editor-provided anchors when present, adding anchors only for headings without one.
        $sections = [];
        $content = preg_replace_callback('/<h2\b([^>]*)>(.*?)<\/h2>/is', static function ($match) use (&$sections) {
            $index = count($sections) + 1;
            $id = preg_match('/\bid=["\']([^"\']+)["\']/i', $match[1], $anchor) ? $anchor[1] : 'legal-section-' . $index;
            $sections[] = ['id'=>$id, 'title'=>wp_strip_all_tags($match[2])];
            return '<h2' . $match[1] . (isset($anchor[1]) ? '' : ' id="' . esc_attr($id) . '"') . '>' . $match[2] . '</h2>';
        }, $content);
        ob_start(); require OI_DIR . 'templates/legal.php'; return ob_get_clean();
    }
}
