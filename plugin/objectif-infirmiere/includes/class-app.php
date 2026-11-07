<?php
defined('ABSPATH') || exit;
final class OI_App {
    public static function url(string $view): string {
        $id = (int)get_option('oi_page_' . $view);
        $url = $id && get_post_status($id) === 'publish' ? get_permalink($id) : false;
        if ($view === 'home') { return home_url('/'); }
        return $url ?: home_url('/' . ['member'=>'espace-revision', 'register'=>'inscription', 'login'=>'connexion', 'legal'=>'mentions-legales', 'privacy'=>'politique-de-confidentialite', 'home'=>''][$view] . '/');
    }
    public static function assets(): void {
        wp_enqueue_style('oi-base', OI_URL . 'assets/base.css', [], OI_VERSION);
    }
    public static function view(): string {
        if (is_singular('page')) {
            foreach (['legal', 'privacy'] as $view) {
                if (get_queried_object_id() === (int)get_option('oi_page_' . $view)) { return $view; }
            }
        }
        $content = is_singular('page') ? trim((string)get_post()->post_content) : '';
        return ['[objectif_infirmiere]'=>'member', '[objectif_infirmiere_home]'=>'home', '[objectif_infirmiere_register]'=>'register', '[objectif_infirmiere_login]'=>'login'][$content] ?? '';
    }
    public static function landing(): string {
        OI_Metrics::count('home_view');
        self::assets();
        wp_enqueue_style('oi-landing', OI_URL . 'assets/landing.css', ['oi-base'], OI_VERSION);
        wp_enqueue_script('oi-demo-content', OI_URL . 'assets/demo-content.js', [], OI_VERSION, true);
        wp_enqueue_script('oi-public', OI_URL . 'assets/public.js', ['oi-demo-content'], OI_VERSION, true);
        ob_start(); require OI_DIR . 'templates/landing.php'; return ob_get_clean();
    }
    public static function render(): string {
        self::assets();
        if (!is_user_logged_in()) { return OI_Auth::render('login'); }
        nocache_headers();
        wp_enqueue_style('oi-app', OI_URL . 'assets/app.css', ['oi-base'], OI_VERSION);
        wp_enqueue_style('oi-reader-refinement',OI_URL.'assets/reader-refinement.css',['oi-app'],OI_VERSION);
        wp_enqueue_style('oi-sidebar-refinement',OI_URL.'assets/sidebar-refinement.css',['oi-reader-refinement'],OI_VERSION);
        wp_enqueue_script('oi-qcm-display',OI_URL.'assets/qcm-display.js',[],OI_VERSION,true);
        if(current_user_can('manage_options')){wp_enqueue_style('oi-user-admin',OI_URL.'assets/user-admin.css',['oi-app'],OI_VERSION);wp_enqueue_script('oi-user-admin',OI_URL.'assets/user-admin.js',[],OI_VERSION,true);wp_enqueue_style('oi-imports',OI_URL.'assets/imports.css',['oi-app'],OI_VERSION);wp_enqueue_script('oi-image-imports',OI_URL.'assets/image-imports.js',[],OI_VERSION,true);wp_enqueue_script('oi-imports',OI_URL.'assets/imports.js',['oi-image-imports'],OI_VERSION,true);wp_enqueue_script('oi-qcm-imports',OI_URL.'assets/qcm-imports.js',['oi-qcm-display'],OI_VERSION,true);}
        wp_enqueue_script('oi-goals',OI_URL.'assets/goals.js',[],OI_VERSION,true);
        wp_enqueue_style('oi-training',OI_URL.'assets/training.css',['oi-app'],OI_VERSION);
        wp_enqueue_script('oi-training',OI_URL.'assets/training.js',['oi-qcm-display'],OI_VERSION,true);
        wp_enqueue_script('oi-reader-headings',OI_URL.'assets/reader-headings.js',[],OI_VERSION,true);
        wp_enqueue_script('oi-reader-diagrams',OI_URL.'assets/reader-diagrams.js',[],OI_VERSION,true);
        wp_enqueue_script('oi-app', OI_URL . 'assets/app.js', ['oi-training','oi-goals','oi-qcm-display','oi-reader-diagrams','oi-reader-headings'], OI_VERSION, true);
        $display_name = wp_get_current_user()->display_name;
        preg_match('/^./us', $display_name ?: 'É', $initial);
        wp_localize_script('oi-app', 'OI', [
            'api' => esc_url_raw(rest_url('oi/v1/')), 'nonce' => wp_create_nonce('wp_rest'),
            'adminUserId'=>current_user_can('manage_options')?get_current_user_id():0, 'admin'=>current_user_can('manage_options'), 'loggedIn' => true, 'name' => $display_name,
            'homeUrl'=>self::url('home'), 'memberUrl'=>self::url('member'),
            'brandUrl'=>esc_url_raw(OI_URL . 'assets/brand/'),
            'logoutUrl'=>wp_logout_url(self::url('home')),
            'aiConfigured'=>(bool)(get_option('oi_vector_store') && OI_Stripe::secret('OI_OPENAI_API_KEY')),
        ]);
        return '<main id="oi-app" class="oi-app"><header class="oi-top"><a href="' . esc_url(self::url('home')) . '" class="oi-brand" aria-label="Objectif Infirmière, accueil"><img class="oi-member-logo" src="' . esc_url(OI_URL . 'assets/brand/logo.png') . '" width="1300" height="436" alt="Objectif Infirmière — La référence de la révision en ligne"></a><div class="oi-top-account"><div class="oi-student oi-header-student"><span class="oi-avatar" aria-hidden="true">' . esc_html($initial[0] ?? 'É') . '</span><span><strong title="' . esc_attr($display_name) . '">' . esc_html($display_name) . '</strong><small>Espace personnel</small></span></div><a class="oi-logout" href="' . esc_url(wp_logout_url(self::url('home'))) . '">Déconnexion</a></div></header><div id="oi-view"><div id="oi-status" class="oi-loading" role="status" aria-live="polite"><div class="oi-loading-art" aria-hidden="true"><span class="oi-loading-halo"></span><img src="' . esc_url(OI_URL . 'assets/brand/mascot.png') . '" width="500" height="500" alt="" fetchpriority="high"></div><p class="oi-loading-title">On prépare ton espace…</p><span class="oi-loading-dots" aria-hidden="true"><i></i><i></i><i></i></span></div></div></main>';
    }
    /** Explicit activation creates product pages while preserving existing content. */
    public static function install_pages(): void {
        $definitions = [
            'member'=>['espace-revision','Mon espace de révision','[objectif_infirmiere]'],
            'home'=>['accueil','Objectif Infirmière — Réviser autrement','[objectif_infirmiere_home]'],
            'register'=>['inscription','Créer mon espace','[objectif_infirmiere_register]'],
            'login'=>['connexion','Se connecter','[objectif_infirmiere_login]'],
        ];
        foreach ($definitions as $key=>[$slug,$title,$content]) {
            $id=(int)get_option('oi_page_'.$key);
            if (!$id || get_post_status($id)!=='publish') {
                $existing=get_page_by_path($slug);
                if ($existing && $existing->post_status==='publish' && trim($existing->post_content)===$content) { $id=$existing->ID; }
                else {
                    $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$content],true);
                    if (is_wp_error($id)) { continue; }
                }
                update_option('oi_page_'.$key,$id);
            }
        }
        if (!get_option('oi_before_redesign_front')) {
            update_option('oi_before_redesign_front',['show_on_front'=>get_option('show_on_front'),'page_on_front'=>get_option('page_on_front')],false);
        }
        if ((int)get_option('oi_page_home')) {
            update_option('show_on_front','page');update_option('page_on_front',(int)get_option('oi_page_home'));
        }
        $demo=(int)(get_option('oi_review_pack') ?: get_option('oi_demo_pack'));
        if ($demo && get_post_type($demo)==='oi_pack' && get_post_status($demo)==='publish' && !get_post_meta($demo,'oi_price',true)) {
            update_post_meta($demo,'oi_free_demo',1);update_option('oi_registration_demo_pack',$demo);
        }
    }
}
