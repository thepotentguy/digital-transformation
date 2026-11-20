<?php
if (!defined('ABSPATH')) { exit; }

function ecs_fields() {
    return array('company','location','employment_type','summary','responsibilities','qualifications','experience','skills','systems','conditions','eligibility','benefits','application_email','contact_email','application_subject','closing_date','equity','shortlist_notice','image_id','image_alt','online_applications');
}
add_action('admin_menu', function () {
    add_menu_page('Careers Studio', 'Careers Studio', 'edit_posts', 'extraordinary-careers-studio', function () {
        echo '<div class="wrap"><div id="ecs-app"></div></div>';
    }, 'dashicons-businessperson', 26);
});
add_action('admin_notices', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || empty($_GET['post'])) { return; }
    $id = absint($_GET['post']);
    if (get_post_meta($id, '_ecs_managed', true) && current_user_can('edit_post', $id)) {
        echo '<div class="notice notice-info"><p>This career post is managed by <a href="' . esc_url(admin_url('admin.php?page=extraordinary-careers-studio')) . '">Careers Studio</a>. Changes made here remain visible, but saving in Careers Studio rebuilds its layout from the stored career fields. Keep its application panel intact so closing dates can hide applications.</p></div>';
    }
});
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_extraordinary-careers-studio') { return; }
    if (current_user_can('upload_files')) { wp_enqueue_media(); }
    wp_enqueue_style('ecs-admin', ECS_URL . 'assets/admin.css', array(), ECS_VERSION);
    wp_enqueue_script('ecs-admin', ECS_URL . 'assets/admin.js', array(), ECS_VERSION, true);
    wp_localize_script('ecs-admin', 'ecsConfig', array('restUrl'=>esc_url_raw(rest_url('extraordinary-careers/v1/')), 'nonce'=>wp_create_nonce('wp_rest'), 'canPublish'=>current_user_can('publish_posts'), 'siteUrl'=>home_url('/'), 'frontendCss'=>ECS_URL . 'assets/frontend.css'));
});
add_action('wp_enqueue_scripts', function () {
    if (is_singular('post') && get_post_meta(get_queried_object_id(), '_ecs_managed', true)) {
        wp_enqueue_style('ecs-frontend', ECS_URL . 'assets/frontend.css', array(), ECS_VERSION);
    }
});

function ecs_permission($request) {
    if (!is_user_logged_in() || !wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
        return new WP_Error('ecs_auth', 'Please sign in again and refresh the page.', array('status'=>401));
    }
    return current_user_can('edit_posts') ? true : new WP_Error('ecs_forbidden', 'You cannot edit career posts.', array('status'=>403));
}
function ecs_post($id) {
    $post = get_post($id);
    if (!$post || $post->post_type !== 'post' || !get_post_meta($id, '_ecs_managed', true) || !in_array($post->post_status, array('draft','publish','pending','future','private'), true)) {
        return new WP_Error('ecs_not_found', 'Career post not found.', array('status'=>404));
    }
    if (!current_user_can('edit_post', $id)) { return new WP_Error('ecs_forbidden', 'You cannot edit this career post.', array('status'=>403)); }
    return $post;
}
function ecs_item($post) {
    $fields = get_post_meta($post->ID, '_ecs_fields', true);
    if (!is_array($fields)) { $fields = array(); }
    $fields['image_url'] = !empty($fields['image_id']) ? (wp_get_attachment_image_url((int)$fields['image_id'], 'large') ?: '') : '';
    return array('id'=>$post->ID,'title'=>$post->post_title,'status'=>$post->post_status,'modified'=>ecs_version($post),'link'=>get_permalink($post),'fields'=>array_merge(array_fill_keys(ecs_fields(), ''), is_array($fields) ? $fields : array()));
}
function ecs_version($post) {
    return hash('sha256', wp_json_encode(array($post->post_modified_gmt, $post->post_title, $post->post_content, $post->post_status, get_post_meta($post->ID, '_ecs_fields', true))));
}
function ecs_validate($body, $publish = false) {
    if (!is_array($body) || !isset($body['title'], $body['fields']) || !is_string($body['title']) || !is_array($body['fields'])) { return new WP_Error('ecs_invalid', 'A title and fields object are required.', array('status'=>400)); }
    $fields = array_fill_keys(ecs_fields(), '');
    foreach ($body['fields'] as $key=>$value) {
        // This display-only value is always resolved from the attachment ID.
        if ($key === 'image_url') { continue; }
        if (!in_array($key, ecs_fields(), true) || !is_string($value)) { return new WP_Error('ecs_invalid', 'Fields must contain supported text values only.', array('status'=>400)); }
        $fields[$key] = sanitize_textarea_field($value);
    }
    foreach (array('application_email','contact_email') as $key) {
        if ($fields[$key] !== '' && !is_email($fields[$key])) { return new WP_Error('ecs_invalid_email', 'Enter a valid ' . str_replace('_', ' ', $key) . '.', array('status'=>400)); }
    }
    if (!in_array($fields['online_applications'], array('', '1'), true)) {
        return new WP_Error('ecs_invalid_online', 'Online applications must be enabled or disabled.', array('status'=>400));
    }
    if ($fields['image_id'] !== '') {
        if (!ctype_digit($fields['image_id']) || (int)$fields['image_id'] < 1 || !wp_attachment_is_image((int)$fields['image_id']) || !current_user_can('read_post', (int)$fields['image_id'])) {
            return new WP_Error('ecs_invalid_image', 'Select an accessible image from the Media Library.', array('status'=>400));
        }
        if ($publish && trim($fields['image_alt']) === '') {
            return new WP_Error('ecs_image_alt', 'Describe the job image before publishing.', array('status'=>400));
        }
    }
    if ($fields['closing_date'] !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $fields['closing_date']);
        if (!$date || $date->format('Y-m-d') !== $fields['closing_date']) { return new WP_Error('ecs_invalid_date', 'Closing date must be a real date in YYYY-MM-DD format.', array('status'=>400)); }
    }
    $title = sanitize_text_field($body['title']);
    if ($publish) {
        foreach (array('company','location','employment_type','summary','responsibilities','qualifications','application_email','closing_date') as $key) {
            if (trim($fields[$key]) === '') { return new WP_Error('ecs_required', 'Complete ' . str_replace('_',' ', $key) . ' before publishing.', array('status'=>400)); }
        }
        if ($title === '') { return new WP_Error('ecs_required', 'A job title is required before publishing.', array('status'=>400)); }
    }
    return array('title'=>$title,'fields'=>$fields);
}

function ecs_render($title, $f, $expired = false) {
    $f = array_merge(array_fill_keys(ecs_fields(), ''), $f);
    $expired = $expired || ($f['closing_date'] !== '' && $f['closing_date'] < current_time('Y-m-d'));
    $html = '<article class="ecs-career"><header class="ecs-career__header"><p class="ecs-career__eyebrow">Extraordinary careers</p><h1>' . esc_html($title) . '</h1><p class="ecs-career__meta">' . esc_html(implode(' · ', array_filter(array($f['company'],$f['location'],$f['employment_type'])))) . '</p></header><div class="ecs-career__body">';
    if (!empty($f['image_id']) && wp_attachment_is_image((int)$f['image_id'])) {
        $html .= '<figure class="ecs-career__image">' . wp_get_attachment_image((int)$f['image_id'], 'large', false, array('alt'=>$f['image_alt'], 'loading'=>'lazy')) . '</figure>';
    }
    $labels = array('summary'=>'The opportunity','responsibilities'=>'Key responsibilities','qualifications'=>'Qualifications','experience'=>'Experience','skills'=>'Skills and attributes','systems'=>'Systems and tools','conditions'=>'Working conditions','eligibility'=>'Eligibility','benefits'=>'What we offer','equity'=>'Employment equity','shortlist_notice'=>'Please note');
    $lists = array('responsibilities','qualifications','experience','skills','systems','conditions','eligibility','benefits');
    foreach ($labels as $key=>$label) {
        if (trim($f[$key]) === '') { continue; }
        $html .= '<section class="ecs-career__section"><h2>' . esc_html($label) . '</h2>';
        $lines = preg_split('/\r\n|\r|\n/', $f[$key]);
        if (in_array($key, $lists, true)) {
            $html .= '<ul>';
            foreach ($lines as $line) { if (trim($line) !== '') { $html .= '<li>' . esc_html(preg_replace('/^\s*[-•]\s*/u', '', $line)) . '</li>'; } }
            $html .= '</ul>';
        } else {
            foreach ($lines as $line) { if (trim($line) !== '') { $html .= '<p>' . esc_html($line) . '</p>'; } }
        }
        $html .= '</section>';
    }
    $html .= '<section class="ecs-career__apply"><h2>' . ($expired ? 'Applications closed' : 'Apply for this role') . '</h2>';
    if ($f['closing_date']) { $html .= '<p>Closing date: <strong>' . esc_html($f['closing_date']) . '</strong></p>'; }
    if (!$expired && is_email($f['application_email'])) {
        $subject = str_replace('[Position]', $title, $f['application_subject'] ?: $title);
        $html .= '<p>Email your application to <a href="' . esc_url('mailto:' . $f['application_email'] . '?subject=' . rawurlencode($subject)) . '">' . esc_html($f['application_email']) . '</a>.</p><a class="ecs-career__button" href="' . esc_url('mailto:' . $f['application_email'] . '?subject=' . rawurlencode($subject)) . '">Apply by email</a>';
    }
    if (is_email($f['contact_email'])) { $html .= '<p>Enquiries: <a href="' . esc_url('mailto:' . $f['contact_email']) . '">' . esc_html($f['contact_email']) . '</a></p>'; }
    return $html . '</section></div></article>';
}
add_filter('the_content', function ($content) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query() || !get_post_meta(get_the_ID(), '_ecs_managed', true)) { return $content; }
    $f = get_post_meta(get_the_ID(), '_ecs_fields', true);
    if (!is_array($f)) { return $content; }
    if (!empty($f['closing_date']) && $f['closing_date'] < current_time('Y-m-d')) {
        // Change only the application panel; native-editor changes elsewhere remain intact.
        return preg_replace('/<section class="ecs-career__apply">.*?<\/section>/s', '<section class="ecs-career__apply"><h2>Applications closed</h2><p>Closing date: <strong>' . esc_html($f['closing_date']) . '</strong></p></section>', $content);
    }
    return $content;
}, 20);

add_action('rest_api_init', function () {
    $namespace = 'extraordinary-careers/v1';
    register_rest_route($namespace, '/jobs', array(
        array('methods'=>'GET','permission_callback'=>'ecs_permission','callback'=>function () {
            $posts = get_posts(array('post_type'=>'post','post_status'=>array('draft','publish','pending','future','private'),'meta_key'=>'_ecs_managed','meta_value'=>'1','numberposts'=>-1,'orderby'=>'modified','order'=>'DESC','suppress_filters'=>false));
            return array('jobs'=>array_values(array_map('ecs_item', array_filter($posts, function ($p) { return current_user_can('edit_post', $p->ID); }))));
        }),
        array('methods'=>'POST','permission_callback'=>'ecs_permission','callback'=>'ecs_save')
    ));
    register_rest_route($namespace, '/jobs/(?P<id>\d+)', array('methods'=>'GET','permission_callback'=>'ecs_permission','callback'=>function ($r) { $p = ecs_post((int)$r['id']); return is_wp_error($p) ? $p : ecs_item($p); }));
    register_rest_route($namespace, '/preview', array('methods'=>'POST','permission_callback'=>'ecs_permission','callback'=>function ($r) {
        $data = ecs_validate($r->get_json_params());
        if (is_wp_error($data)) { return $data; }
        $html = ecs_render($data['title'], $data['fields']);
        if ($data['fields']['online_applications'] === '1') {
            $closed = $data['fields']['closing_date'] !== '' && $data['fields']['closing_date'] < current_time('Y-m-d');
            $ready = function_exists('ecs_cv_ready') && !is_wp_error(ecs_cv_ready());
            $html .= '<section class="ecs-career__apply"><h2>Apply online</h2>';
            if ($closed || !$ready) {
                $html .= '<p>' . ($closed ? 'Online applications are closed for this role.' : 'Online applications will appear only after private storage, the privacy notice and scanning are configured.') . '</p>';
            } else {
                $html .= '<p>' . esc_html(ECS_CV_PRIVACY_NOTICE) . '</p><p>Applications are retained for twelve months. PDF CVs up to 5 MB.</p><fieldset disabled class="ecs-career__application-form"><legend>Form preview — no submission</legend><p><label>Full name <input type="text"></label></p><p><label>Email address <input type="email"></label></p><p><label>Phone number (optional) <input type="tel"></label></p><p><label>PDF CV <input type="file"></label></p><p><label><input type="checkbox"> I have read the privacy notice and consent to processing my application.</label></p><button type="button">Submit application</button></fieldset>';
            }
            $html .= '</section>';
        }
        return array('html'=>$html);
    }));
});

function ecs_save($request) {
    $body = $request->get_json_params();
    if (!is_array($body) || !isset($body['status']) || !in_array($body['status'], array('draft','publish'), true)) { return new WP_Error('ecs_status', 'Status must be draft or publish.', array('status'=>400)); }
    if (array_key_exists('id', $body) && (!is_int($body['id']) || $body['id'] < 1)) { return new WP_Error('ecs_id', 'ID must be a positive integer.', array('status'=>400)); }
    $id = isset($body['id']) ? $body['id'] : 0;
    $post = $id ? ecs_post($id) : null;
    if (is_wp_error($post)) { return $post; }
    if ($post && $post->post_status === 'publish' && $body['status'] !== 'publish') { return new WP_Error('ecs_downgrade', 'Published posts must remain published when edited in Careers Studio.', array('status'=>409)); }
    if ($body['status'] === 'publish' && !current_user_can('publish_posts')) { return new WP_Error('ecs_publish', 'You cannot publish career posts.', array('status'=>403)); }
    if ($post && (!isset($body['modified']) || !is_string($body['modified']) || !hash_equals(ecs_version($post), $body['modified']))) { return new WP_Error('ecs_conflict', 'This post changed since you opened it. Reload it before saving.', array('status'=>409)); }
    // Preserve stored values when a caller sends only changed field keys.
    if ($post && isset($body['fields']) && is_array($body['fields'])) {
        $existing = get_post_meta($id, '_ecs_fields', true);
        $body['fields'] = array_merge(is_array($existing) ? $existing : array(), $body['fields']);
    }
    $data = ecs_validate($body, $body['status'] === 'publish');
    if (is_wp_error($data)) { return $data; }
    $args = array('post_type'=>'post','post_title'=>$data['title'],'post_content'=>ecs_render($data['title'], $data['fields']),'post_status'=>$body['status'],'meta_input'=>array('_ecs_managed'=>'1','_ecs_fields'=>$data['fields']));
    if ($id) { $args['ID'] = $id; }
    $result = wp_insert_post(wp_slash($args), true);
    if (is_wp_error($result)) { return $result; }
    if (!empty($data['fields']['image_id'])) {
        set_post_thumbnail($result, (int)$data['fields']['image_id']);
    } else {
        delete_post_thumbnail($result);
    }
    return ecs_item(get_post($result));
}
