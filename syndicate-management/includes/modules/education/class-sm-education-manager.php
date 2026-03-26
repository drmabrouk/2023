<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Education_Manager {
    private static function check_capability($cap) {
        if (!current_user_can($cap)) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
    }

    public static function ajax_add_survey() {
        try {
            if (!current_user_can('manage_options') && !current_user_can('sm_manage_system')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = SM_DB::add_survey($_POST);
            if ($id) {
                wp_send_json_success($id);
            } else {
                wp_send_json_error(['message' => 'Failed to create test']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding survey: ' . $e->getMessage()]);
        }
    }

    public static function ajax_update_survey() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = intval($_POST['id']);
            if (SM_DB::update_survey_data($id, $_POST)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed to update test']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating survey: ' . $e->getMessage()]);
        }
    }

    public static function ajax_add_test_question() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = SM_DB::add_test_question($_POST);
            if ($id) {
                wp_send_json_success($id);
            } else {
                wp_send_json_error(['message' => 'Failed to add question']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding question: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_test_question() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = intval($_POST['id']);
            if (SM_DB::delete_test_question($id)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed to delete question']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error deleting question: ' . $e->getMessage()]);
        }
    }

    public static function ajax_assign_test() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $sid = intval($_POST['survey_id']);
            $uids = array_map('intval', (array)$_POST['user_ids']);

            if (empty($uids)) {
                wp_send_json_error(['message' => 'يرجى اختيار مستخدم واحد على الأقل']);
            }

            foreach ($uids as $uid) {
                SM_DB::assign_test($sid, $uid);
            }
            wp_send_json_success();
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_start_test_session() {
        try {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
            check_ajax_referer('sm_test_nonce', 'nonce');

            $aid = intval($_POST['assignment_id']);
            global $wpdb;
            $assign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_test_assignments WHERE id = %d AND user_id = %d", $aid, get_current_user_id()));
            if (!$assign) wp_send_json_error(['message' => 'Invalid assignment']);

            $wpdb->update("{$wpdb->prefix}sm_test_assignments", [
                'started_at' => current_time('mysql'),
                'last_heartbeat' => current_time('mysql'),
                'status' => 'active'
            ], ['id' => $aid]);

            self::log_test_action($aid, 'start', 'بدء الاختبار المهني');
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_log_test_action() {
        try {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
            check_ajax_referer('sm_test_nonce', 'nonce');
            $aid = intval($_POST['assignment_id']);
            $type = sanitize_text_field($_POST['type']);
            $details = sanitize_textarea_field($_POST['details']);
            self::log_test_action($aid, $type, $details);
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    private static function log_test_action($aid, $type, $details) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_test_logs", [
            'assignment_id' => $aid,
            'user_id' => get_current_user_id(),
            'action_type' => $type,
            'details' => $details,
            'created_at' => current_time('mysql')
        ]);
    }

    public static function ajax_sync_test_progress() {
        try {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
            check_ajax_referer('sm_test_nonce', 'nonce');

            $aid = intval($_POST['assignment_id']);
            $data = $_POST['progress']; // JSON string
            global $wpdb;

            $wpdb->update("{$wpdb->prefix}sm_test_assignments", [
                'session_data' => $data,
                'last_heartbeat' => current_time('mysql')
            ], ['id' => $aid, 'user_id' => get_current_user_id()]);

            // Check if terminated by admin
            $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}sm_test_assignments WHERE id = %d", $aid));
            wp_send_json_success(['status' => $status]);
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_terminate_test_admin() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            $aid = intval($_POST['assignment_id']);
            global $wpdb;
            $wpdb->update("{$wpdb->prefix}sm_test_assignments", ['status' => 'terminated'], ['id' => $aid]);
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_submit_survey_response() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_survey_action', 'nonce');
            } else {
                check_ajax_referer('sm_survey_action', '_wpnonce');
            }

        $sid = intval($_POST['survey_id']);
        $aid = intval($_POST['assignment_id'] ?? 0);
        $user_id = get_current_user_id();
        $responses = json_decode(stripslashes($_POST['responses'] ?? '[]'), true);
        $questions = SM_DB::get_test_questions($sid);
        $survey = SM_DB::get_survey($sid);

        if (!$survey) {
            wp_send_json_error(['message' => 'Test not found']);
        }

        // Security: Check attempt limits (skip if it's an auto-submit from active session)
        if (!$aid) {
            $attempts_made = SM_DB::get_user_attempts_count($sid, $user_id);
            if ($attempts_made >= $survey->max_attempts) {
                wp_send_json_error(['message' => 'لقد استنفدت كافة المحاولات المتاحة لهذا الاختبار.']);
            }
        }

        $score = 0;
        $total_points = 0;

        if (!empty($questions)) {
            foreach ($questions as $q) {
                $total_points += $q->points;
                $user_ans = $responses[$q->id] ?? '';
                if (trim((string)$user_ans) === trim((string)$q->correct_answer)) {
                    $score += $q->points;
                }
            }
        }

        $percent = $total_points > 0 ? ($score / $total_points) * 100 : 0;
        $passed = ($percent >= $survey->pass_score);

        SM_DB::save_test_response([
            'survey_id' => $sid,
            'user_id' => get_current_user_id(),
            'responses' => $responses,
            'score' => $percent,
            'status' => $passed ? 'passed' : 'failed'
        ]);

        if ($aid) {
            global $wpdb;
            $wpdb->update("{$wpdb->prefix}sm_test_assignments", ['status' => 'completed'], ['id' => $aid]);
            self::log_test_action($aid, 'submit', 'تم تسليم الاختبار بنجاح');
        }

        // Notify member of result
        $user = wp_get_current_user();
        $msg = "لقد أكملت اختبار: {$survey->title}\nالنتيجة: " . round($percent) . "%\nالحالة: " . ($passed ? 'ناجح ✅' : 'لم تجتز ❌');

        SM_DB::send_message(
            0, // System
            $user->ID,
            $msg,
            null,
            null,
            get_user_meta($user->ID, 'sm_governorate', true)
        );

            wp_send_json_success([
                'score' => $percent,
                'passed' => $passed
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error submitting test results: ' . $e->getMessage()]);
        }
    }

    public static function ajax_cancel_survey() {
        try {
            self::check_capability('manage_options');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            if (SM_DB::update_survey_data(intval($_POST['id']), ['status' => 'cancelled'])) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_get_survey_results() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            wp_send_json_success(SM_DB::get_survey_results(intval($_GET['id'])));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_export_survey_results() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $id = intval($_GET['id']);
        $results = SM_DB::get_survey_results($id);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="survey-'.$id.'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Question', 'Answer', 'Count']);
        foreach ($results as $r) {
            foreach ($r['answers'] as $ans => $count) {
                fputcsv($out, [$r['question'], $ans, $count]);
            }
        }
        fclose($out);
        exit;
    }

    public static function ajax_get_test_questions() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            $test_id = intval($_GET['test_id']);
        // Capability check: admins or the user assigned to the test
        $can_view = current_user_can('sm_manage_system');
        if (!$can_view) {
            $assignments = SM_DB::get_test_assignments($test_id);
            foreach ($assignments as $a) {
                if ($a->user_id == get_current_user_id()) {
                    $can_view = true;
                    break;
                }
            }
        }

        if (!$can_view) {
            wp_send_json_error(['message' => 'Access denied']);
        }

            wp_send_json_success(SM_DB::get_test_questions($test_id));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
