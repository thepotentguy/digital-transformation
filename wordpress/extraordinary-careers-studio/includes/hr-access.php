<?php
if (!defined('ABSPATH')) { exit; }

// HR membership is explicit and separate from permission to publish jobs.
function ecs_hr_profile_access($user) {
    if (!current_user_can('manage_options') || !current_user_can('edit_user', $user->ID)) { return; }
    wp_nonce_field('ecs_hr_access_' . $user->ID, 'ecs_hr_access_nonce');
    echo '<h2>Careers Studio HR access</h2><table class="form-table"><tr><th>Private applications</th><td><label><input type="checkbox" name="ecs_hr_access" value="1" ' . checked(user_can($user, 'manage_career_applications'), true, false) . '> Allow this user to search, download, review and delete private CV applications.</label><p class="description">Grant only to authorised HR staff. Website administrators retain access through their administrator role.</p></td></tr></table>';
}
add_action('show_user_profile', 'ecs_hr_profile_access');
add_action('edit_user_profile', 'ecs_hr_profile_access');
function ecs_save_hr_profile_access($user_id) {
    if (!current_user_can('manage_options') || !current_user_can('edit_user', $user_id)) { return; }
    if (!isset($_POST['ecs_hr_access_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ecs_hr_access_nonce'])), 'ecs_hr_access_' . $user_id)) { return; }
    $user = get_user_by('id', $user_id);
    if (!$user) { return; }
    if (isset($_POST['ecs_hr_access']) && $_POST['ecs_hr_access'] === '1') {
        $user->add_cap('manage_career_applications');
    } else {
        $user->remove_cap('manage_career_applications');
    }
}
add_action('personal_options_update', 'ecs_save_hr_profile_access');
add_action('edit_user_profile_update', 'ecs_save_hr_profile_access');
