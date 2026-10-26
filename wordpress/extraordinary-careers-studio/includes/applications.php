<?php
/** Private, opt-in applications. No scheduled deletion or outbound notifications. */
if (!defined('ABSPATH')) { exit; }

function ecs_cv_tables() { global $wpdb; return array($wpdb->prefix . 'ecs_applications', $wpdb->prefix . 'ecs_cv_events'); }
function ecs_cv_activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    list($applications, $events) = ecs_cv_tables();
    $collate = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $applications (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      job_id bigint(20) unsigned NOT NULL,
      applicant_name varchar(200) NOT NULL,
      email varchar(254) NOT NULL,
      phone varchar(80) NOT NULL DEFAULT '',
      status varchar(20) NOT NULL DEFAULT 'new',
      file_name varchar(80) NOT NULL,
      file_size bigint(20) unsigned NOT NULL,
      created_at datetime NOT NULL,
      expires_at datetime NOT NULL,
      consent_at datetime NOT NULL,
      privacy_hash varchar(64) NOT NULL,
      privacy_notice longtext NOT NULL,
      request_hash varchar(64) NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY request_hash (request_hash),
      KEY job_id (job_id),
      KEY expires_at (expires_at),
      KEY status (status)
    ) $collate;");
    dbDelta("CREATE TABLE $events (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      event_type varchar(40) NOT NULL,
      application_id bigint(20) unsigned NOT NULL DEFAULT 0,
      job_id bigint(20) unsigned NOT NULL DEFAULT 0,
      actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
      occurred_at datetime NOT NULL,
      PRIMARY KEY  (id),
      KEY event_type (event_type),
      KEY job_id (job_id)
    ) $collate;");
    $role = get_role('administrator');
    if ($role) { $role->add_cap('manage_career_applications'); }
}
function ecs_cv_error($message, $status = 400) { return new WP_Error('ecs_application', $message, array('status'=>$status)); }
function ecs_cv_now() { return gmdate('Y-m-d H:i:s'); }
function ecs_cv_audit($type, $id = 0, $job = 0) {
    global $wpdb; list(, $table) = ecs_cv_tables();
    return $wpdb->insert($table, array('event_type'=>$type,'application_id'=>$id,'job_id'=>$job,'actor_id'=>$type==='contact_click' ? 0 : get_current_user_id(),'occurred_at'=>ecs_cv_now()), array('%s','%d','%d','%d','%s')) !== false;
}
function ecs_cv_storage() {
    if (!defined('ECS_CV_STORAGE_DIR') || !is_string(ECS_CV_STORAGE_DIR)) { return ecs_cv_error('Private CV storage is not configured.', 503); }
    $dir = realpath(ECS_CV_STORAGE_DIR);
    if (!$dir || !is_dir($dir) || !is_writable($dir) || is_link(ECS_CV_STORAGE_DIR)) { return ecs_cv_error('Private CV storage is unavailable.', 503); }
    $normal = trailingslashit(strtolower(wp_normalize_path($dir)));
    $roots = array(ABSPATH, WP_CONTENT_DIR);
    if (!empty($_SERVER['DOCUMENT_ROOT'])) { $roots[] = $_SERVER['DOCUMENT_ROOT']; }
    foreach ($roots as $root) {
        $real = realpath($root);
        if (!$real) { return ecs_cv_error('The public document root could not be verified.', 503); }
        $public = trailingslashit(strtolower(wp_normalize_path($real)));
        if (strpos($normal, $public) === 0 || strpos($public, $normal) === 0) { return ecs_cv_error('CV storage must be outside all public site directories.', 503); }
    }
    // Hosting aliases cannot be detected from PHP. Explicit deployment attestation is required.
    if (!defined('ECS_CV_STORAGE_PRIVATE') || ECS_CV_STORAGE_PRIVATE !== true) { return ecs_cv_error('The host must verify that CV storage has no public URL or server alias.', 503); }
    return $dir;
}
function ecs_cv_ready() {
    if (!defined('ECS_CV_UPLOADS_ENABLED') || ECS_CV_UPLOADS_ENABLED !== true) { return ecs_cv_error('Online applications are disabled.', 503); }
    if (!defined('ECS_CV_PRIVACY_NOTICE') || !is_string(ECS_CV_PRIVACY_NOTICE) || trim(ECS_CV_PRIVACY_NOTICE) === '') { return ecs_cv_error('An approved application privacy notice is required.', 503); }
    if (!has_filter('ecs_cv_scan_file') && !(defined('ECS_CV_TEST_MODE') && ECS_CV_TEST_MODE === true && in_array(wp_get_environment_type(), array('local','development'), true))) { return ecs_cv_error('Document scanning has not been configured.',503); }
    return ecs_cv_storage();
}
function ecs_cv_job($id) {
    $post = get_post($id); $fields = get_post_meta($id, '_ecs_fields', true);
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish' || !get_post_meta($id, '_ecs_managed', true)) { return ecs_cv_error('This career opportunity is unavailable.', 404); }
    if (!is_array($fields) || empty($fields['online_applications']) || $fields['online_applications'] !== '1') { return ecs_cv_error('Online applications are not enabled for this role.', 403); }
    if (empty($fields['closing_date']) || $fields['closing_date'] < current_time('Y-m-d')) { return ecs_cv_error('Applications for this role have closed.', 410); }
    return $post;
}
function ecs_cv_hr_permission($request) {
    if (!is_user_logged_in() || !wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) { return ecs_cv_error('Please sign in and refresh this page.', 401); }
    return current_user_can('manage_career_applications') ? true : ecs_cv_error('HR application access is required.', 403);
}
function ecs_cv_rate($purpose, $limit) {
    // Store only a short-lived keyed digest, never a raw address or browser identifier.
    $key = 'ecs_cv_' . hash_hmac('sha256', $purpose . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('nonce'));
    $count = (int)get_transient($key);
    if ($count >= $limit) { return false; }
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    return true;
}
function ecs_cv_scan($path) {
    $default = ecs_cv_error('Document security scanning is not configured. Please use the email application option.', 503);
    if (defined('ECS_CV_TEST_MODE') && ECS_CV_TEST_MODE === true && in_array(wp_get_environment_type(), array('local','development'), true)) { $default = true; }
    $result = apply_filters('ecs_cv_scan_file', $default, $path);
    return $result === true ? true : (is_wp_error($result) ? $result : ecs_cv_error('This document did not pass security scanning.', 400));
}
function ecs_cv_settings() {
    $ready = ecs_cv_ready();
    $response = new WP_REST_Response(array('enabled'=>!is_wp_error($ready),'reason'=>is_wp_error($ready) ? 'Online applications are currently unavailable. Please use the advertised contact option.' : '', 'privacyNotice'=>defined('ECS_CV_PRIVACY_NOTICE') ? (string)ECS_CV_PRIVACY_NOTICE : '', 'maxBytes'=>5 * MB_IN_BYTES,'token'=>wp_create_nonce('ecs_cv_apply'),'retentionMonths'=>12));
    $response->header('Cache-Control', 'no-store, private');
    return $response;
}
function ecs_cv_replay($hash) {
    global $wpdb; list($table)=ecs_cv_tables();
    $row=$wpdb->get_row($wpdb->prepare("SELECT id,status FROM $table WHERE request_hash=%s",$hash),ARRAY_A);
    if (!$row) { return null; }
    if ($row['status']==='upload_pending') { return ecs_cv_error('This application has not completed. Please retry later or contact HR.',503); }
    return array('accepted'=>true,'reference'=>strtoupper(substr($hash,0,16)),'message'=>'Your application has been received.');
}
function ecs_cv_abort_pending($id,$path) {
    global $wpdb; list($table)=ecs_cv_tables();
    // Keep the pending row whenever cleanup fails: retention and HR deletion still own the file.
    if (!file_exists($path) || @unlink($path)) { $wpdb->delete($table,array('id'=>$id),array('%d')); }
}
function ecs_cv_apply($request) {
    global $wpdb; list($table) = ecs_cv_tables();
    $storage = ecs_cv_ready(); if (is_wp_error($storage)) { return $storage; }
    if (!is_string($request->get_param('token')) || !wp_verify_nonce($request->get_param('token'), 'ecs_cv_apply')) { return ecs_cv_error('Refresh the form before applying.', 403); }
    if (!ecs_cv_rate('apply', 10)) { return ecs_cv_error('Too many attempts. Please try again later.', 429); }
    if ($request->get_param('website')) { return ecs_cv_error('Unable to accept this application.'); }
    $raw_id = $request->get_param('job_id');
    if (!is_scalar($raw_id) || !preg_match('/^[1-9][0-9]*$/D', (string)$raw_id)) { return ecs_cv_error('Choose a valid role.'); }
    $job = ecs_cv_job((int)$raw_id); if (is_wp_error($job)) { return $job; }
    foreach (array('name','email','phone','consent','idempotency_key') as $key) { if ($request->get_param($key) !== null && !is_string($request->get_param($key))) { return ecs_cv_error('Invalid application fields.'); } }
    $name = sanitize_text_field((string)$request->get_param('name')); $email = sanitize_email((string)$request->get_param('email')); $phone = sanitize_text_field((string)$request->get_param('phone'));
    if (!$name || strlen($name) > 200 || !is_email($email) || strlen($email) > 254 || strlen($phone) > 80 || $request->get_param('consent') !== '1') { return ecs_cv_error('Provide your name, valid email and privacy consent.'); }
    $key = $request->get_param('idempotency_key');
    if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) { return ecs_cv_error('Refresh the application form and try again.'); }
    $hash = hash_hmac('sha256', $job->ID . '|' . $key, wp_salt('auth'));
    $replay=ecs_cv_replay($hash); if ($replay!==null) { return $replay; }
    $files = $request->get_file_params(); $file = $files['cv'] ?? null;
    if (!is_array($file) || !isset($file['error'],$file['tmp_name'],$file['name'],$file['size']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_string($file['name']) || !is_uploaded_file($file['tmp_name'])) { return ecs_cv_error('Attach a PDF CV to continue.'); }
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 5 * MB_IN_BYTES || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') { return ecs_cv_error('Use a PDF document no larger than 5 MB.'); }
    $signature = file_get_contents($file['tmp_name'], false, null, 0, 5);
    if (!class_exists('finfo')) { return ecs_cv_error('Document validation is unavailable.', 503); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($signature !== '%PDF-' || $mime !== 'application/pdf') { return ecs_cv_error('The document must be a valid PDF.'); }
    $scan = ecs_cv_scan($file['tmp_name']); if (is_wp_error($scan)) { return $scan; }
    $filename = bin2hex(random_bytes(24)) . '.pdf'; $target = $storage . DIRECTORY_SEPARATOR . $filename;
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiry = $now->modify('+12 months')->format('Y-m-d H:i:s');
    $saved = $wpdb->insert($table, array('job_id'=>$job->ID,'applicant_name'=>$name,'email'=>$email,'phone'=>$phone,'status'=>'upload_pending','file_name'=>$filename,'file_size'=>$size,'created_at'=>$now->format('Y-m-d H:i:s'),'expires_at'=>$expiry,'consent_at'=>$now->format('Y-m-d H:i:s'),'privacy_hash'=>hash('sha256', ECS_CV_PRIVACY_NOTICE),'privacy_notice'=>ECS_CV_PRIVACY_NOTICE,'request_hash'=>$hash));
    if ($saved === false) { $replay=ecs_cv_replay($hash); return $replay!==null ? $replay : ecs_cv_error('Your application could not be saved. Please try again.',500); }
    $application_id=(int)$wpdb->insert_id;
    if (!@move_uploaded_file($file['tmp_name'], $target)) { ecs_cv_abort_pending($application_id,$target); return ecs_cv_error('The document could not be stored. Please try again.',500); }
    if (!@chmod($target,0600)) { ecs_cv_abort_pending($application_id,$target); return ecs_cv_error('Private document permissions could not be applied.',500); }
    if ($wpdb->update($table,array('status'=>'new'),array('id'=>$application_id,'status'=>'upload_pending'))!==1) { ecs_cv_abort_pending($application_id,$target); return ecs_cv_error('Your application could not be completed. Please contact HR if the problem persists.',500); }
    ecs_cv_audit('submitted',$application_id,$job->ID);
    $response = new WP_REST_Response(array('accepted'=>true,'reference'=>strtoupper(substr($hash,0,16)),'message'=>'Your application has been received.'), 201); $response->header('Cache-Control','no-store'); return $response;
}
function ecs_cv_list($request) {
    global $wpdb; list($table,$events) = ecs_cv_tables();
    foreach(array('page','per_page','search','job','status','date_from','date_to') as $key) { if ($request->get_param($key)!==null && !is_scalar($request->get_param($key))) { return ecs_cv_error('Invalid search filters.'); } }
    $page = max(1, (int)$request->get_param('page')); $limit = min(50, max(1, (int)($request->get_param('per_page') ?: 20)));
    $where = '1=1'; $values = array();
    $search = $request->get_param('search');
    if (is_string($search) && $search !== '') { $where .= ' AND (applicant_name LIKE %s OR email LIKE %s)'; $like = '%' . $wpdb->esc_like(substr($search,0,200)) . '%'; $values[]=$like; $values[]=$like; }
    if ($request->get_param('job')) { $where .= ' AND job_id=%d'; $values[] = absint($request->get_param('job')); }
    if ($request->get_param('status')) { $where .= ' AND status=%s'; $values[] = sanitize_key($request->get_param('status')); }
    foreach (array('date_from'=>'>=','date_to'=>'<=') as $key=>$operator) {
        $date=$request->get_param($key);
        if ($date === null || $date === '') { continue; }
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !($parsed=DateTime::createFromFormat('!Y-m-d',$date)) || $parsed->format('Y-m-d')!==$date) { return ecs_cv_error('Date filters must use real YYYY-MM-DD dates.'); }
        $where .= ' AND created_at ' . $operator . ' %s'; $values[]=$date . ($key==='date_from'?' 00:00:00':' 23:59:59');
    }
    $sql = "SELECT * FROM $table WHERE $where"; $count = "SELECT COUNT(*) FROM $table WHERE $where";
    if ($values) { $sql=$wpdb->prepare($sql,$values); $count=$wpdb->prepare($count,$values); }
    $total = (int)$wpdb->get_var($count);
    $rows = $wpdb->get_results($sql . $wpdb->prepare(' ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d', $limit, ($page-1)*$limit), ARRAY_A);
    foreach ($rows as &$row) {
        unset($row['file_name'],$row['request_hash'],$row['privacy_hash'],$row['privacy_notice']);
        $row['expired'] = $row['expires_at'] <= ecs_cv_now(); $row['job_title'] = get_the_title((int)$row['job_id']);
        $row['download_url'] = $row['expired'] || $row['status']==='upload_pending' ? null : add_query_arg('_wpnonce',wp_create_nonce('ecs_cv_download_' . $row['id']),admin_url('admin-post.php?action=ecs_cv_download&id=' . $row['id']));
    } unset($row);
    ecs_cv_audit('list_view');
    $job_ids=$wpdb->get_col("SELECT DISTINCT job_id FROM $table ORDER BY job_id DESC"); $jobs=array(); foreach($job_ids as $job_id) { $jobs[]=array('id'=>(int)$job_id,'title'=>get_the_title((int)$job_id)); }
    $response = new WP_REST_Response(array('applications'=>$rows,'jobs'=>$jobs,'total'=>$total,'pages'=>(int)ceil($total/$limit),'page'=>$page,'expired'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE expires_at<=%s",ecs_cv_now())),'stats'=>array('applications'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $table"),'new'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status='new' AND expires_at>%s",ecs_cv_now())),'contact_clicks'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $events WHERE event_type='contact_click'"))));
    $response->header('Cache-Control','private, no-store'); return $response;
}
function ecs_cv_row($id) { global $wpdb; list($table)=ecs_cv_tables(); return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id), ARRAY_A); }
function ecs_cv_path($row) {
    $dir = ecs_cv_storage(); if (is_wp_error($dir)) { return $dir; }
    if (!preg_match('/^[a-f0-9]{48}\.pdf$/D', $row['file_name'])) { return ecs_cv_error('Invalid stored document.',500); }
    $path = $dir . DIRECTORY_SEPARATOR . $row['file_name'];
    if (is_link($path)) { return ecs_cv_error('Invalid stored document.',500); }
    if (file_exists($path) && dirname(realpath($path)) !== $dir) { return ecs_cv_error('Invalid stored document.',500); }
    return $path;
}
function ecs_cv_delete($id, $event='deleted') {
    global $wpdb; list($table) = ecs_cv_tables(); $row = ecs_cv_row($id);
    if (!$row) { return ecs_cv_error('Application not found.',404); }
    $path=ecs_cv_path($row); if (is_wp_error($path)) { return $path; }
    if (file_exists($path) && !@unlink($path)) { return ecs_cv_error('The private document could not be deleted; the application remains recorded.',500); }
    if ($wpdb->delete($table,array('id'=>$id),array('%d')) === false) { return ecs_cv_error('The application record could not be removed. Retry deletion.',500); }
    ecs_cv_audit($event,$id,(int)$row['job_id']); return array('deleted'=>true);
}
add_action('admin_post_ecs_cv_download', function () {
    if (!current_user_can('manage_career_applications')) { wp_die('HR application access is required.',403); }
    $id=isset($_GET['id']) ? absint($_GET['id']) : 0; check_admin_referer('ecs_cv_download_' . $id);
    $row=ecs_cv_row($id); if (!$row || $row['status']==='upload_pending' || $row['expires_at'] <= ecs_cv_now()) { wp_die('This document is unavailable or its retention period has expired.',410); }
    $path=ecs_cv_path($row); if (is_wp_error($path) || !is_file($path)) { wp_die('Document unavailable.',404); }
    ecs_cv_audit('downloaded',$id,(int)$row['job_id']);
    nocache_headers(); header('Cache-Control: private, no-store, max-age=0'); header('X-Content-Type-Options: nosniff'); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="application-' . $id . '.pdf"'); header('Content-Length: ' . filesize($path)); readfile($path); exit;
});
add_action('rest_api_init', function () {
    $ns='extraordinary-careers/v1';
    register_rest_route($ns,'/application-settings',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>'ecs_cv_settings'));
    register_rest_route($ns,'/apply',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>'ecs_cv_apply'));
    register_rest_route($ns,'/applications',array('methods'=>'GET','permission_callback'=>'ecs_cv_hr_permission','callback'=>'ecs_cv_list'));
    register_rest_route($ns,'/applications/(?P<id>\d+)/status',array('methods'=>'POST','permission_callback'=>'ecs_cv_hr_permission','callback'=>function($r) {
        global $wpdb; list($table)=ecs_cv_tables(); $status=$r->get_param('status'); $row=ecs_cv_row((int)$r['id']);
        if (!$row) { return ecs_cv_error('Application not found.',404); }
        if ($row['status']==='upload_pending') { return ecs_cv_error('This upload did not complete. It can be deleted, but cannot be marked as a completed application.',409); }
        if (!in_array($status,array('new','reviewing','shortlisted','rejected','hired'),true)) { return ecs_cv_error('Choose a supported application status.'); }
        if ($row['expires_at']<=ecs_cv_now()) { return ecs_cv_error('Purge this expired application instead of changing its status.',410); }
        if ($wpdb->update($table,array('status'=>$status),array('id'=>(int)$r['id']))===false) { return ecs_cv_error('Status could not be saved.',500); }
        ecs_cv_audit('status_' . $status,(int)$r['id'],(int)$row['job_id']); return array('id'=>(int)$r['id'],'status'=>$status);
    }));
    register_rest_route($ns,'/applications/(?P<id>\d+)',array('methods'=>'DELETE','permission_callback'=>'ecs_cv_hr_permission','callback'=>function($r) { return ecs_cv_delete((int)$r['id']); }));
    register_rest_route($ns,'/applications/purge',array('methods'=>'POST','permission_callback'=>'ecs_cv_hr_permission','callback'=>function($r) {
        global $wpdb; list($table)=ecs_cv_tables();
        if ($r->get_param('confirm')!=='DELETE EXPIRED APPLICATIONS') { return ecs_cv_error('Confirm deletion of expired applications.'); }
        $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE expires_at<=%s ORDER BY id LIMIT 100",ecs_cv_now())); $deleted=0;
        foreach ($ids as $id) { $result=ecs_cv_delete((int)$id,'retention_purged'); if (is_wp_error($result)) { return $result; } $deleted++; }
        return array('deleted'=>$deleted,'remaining'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE expires_at<=%s",ecs_cv_now())));
    }));
    register_rest_route($ns,'/jobs/(?P<id>\d+)/contact-click',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($r) {
        $id=(int)$r['id']; $p=get_post($id); $f=get_post_meta($id,'_ecs_fields',true);
        if (!$p || $p->post_status!=='publish' || $p->post_type!=='post' || !get_post_meta($id,'_ecs_managed',true) || empty($f['contact_email']) || !is_email($f['contact_email']) || empty($f['closing_date']) || $f['closing_date']<current_time('Y-m-d')) { return ecs_cv_error('This career opportunity is unavailable.',404); }
        if (!is_string($r->get_param('token')) || !wp_verify_nonce($r->get_param('token'),'ecs_cv_apply')) { return ecs_cv_error('Refresh this page.',403); }
        if (!ecs_cv_rate('click_' . $id,20)) { return ecs_cv_error('Too many requests.',429); }
        if (!ecs_cv_audit('contact_click',0,$id)) { return ecs_cv_error('The click could not be recorded.',500); } return array('recorded'=>true);
    }));
});
add_action('admin_menu',function() {
    add_menu_page('Career applications','HR Applications','manage_career_applications','ecs-applications',function() {
        echo '<div class="wrap"><h1>Career applications</h1><p>Private HR records. CVs become unavailable twelve months after application. Use the manual purge to remove expired records and documents. No automatic purge is scheduled.</p><div id="ecs-hr-app"></div></div>';
    },'dashicons-id-alt',27);
});
add_action('admin_enqueue_scripts',function($hook) {
    if ($hook!=='toplevel_page_ecs-applications') { return; }
    wp_enqueue_style('ecs-hr',ECS_URL . 'assets/hr.css',array(),ECS_VERSION);
    wp_enqueue_script('ecs-hr',ECS_URL . 'assets/hr.js',array(),ECS_VERSION,true);
    wp_localize_script('ecs-hr','ecsHrConfig',array('restUrl'=>rest_url('extraordinary-careers/v1/'),'nonce'=>wp_create_nonce('wp_rest')));
});
add_action('wp_enqueue_scripts',function() {
    if (!is_singular('post') || !get_post_meta(get_queried_object_id(),'_ecs_managed',true)) { return; }
    wp_enqueue_script('ecs-applications',ECS_URL . 'assets/application.js',array(),ECS_VERSION,true);
    $fields=get_post_meta(get_queried_object_id(),'_ecs_fields',true);
    wp_localize_script('ecs-applications','ecsApplications',array('restUrl'=>rest_url('extraordinary-careers/v1/'),'mode'=>'public','jobId'=>get_queried_object_id(),'hrEmail'=>is_array($fields) ? ($fields['contact_email'] ?? '') : ''));
});
add_filter('the_content',function($content) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query() || is_wp_error(ecs_cv_ready()) || is_wp_error(ecs_cv_job(get_the_ID()))) { return $content; }
    return $content . '<section class="ecs-career__apply" aria-labelledby="ecs-online-heading"><h2 id="ecs-online-heading">Apply online</h2><div id="ecs-application-form" data-job-id="' . esc_attr(get_the_ID()) . '"></div><noscript>Please use the email application option above.</noscript></section>';
},30);
