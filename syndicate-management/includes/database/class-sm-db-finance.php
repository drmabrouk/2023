<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Finance {
    public static function delete_payments_by_member_ids($member_ids) {
        global $wpdb;
        $ids_str = implode(',', array_map('intval', $member_ids));
        return $wpdb->query("DELETE FROM {$wpdb->prefix}sm_payments WHERE member_id IN ($ids_str)");
    }

    public static function get_payment_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_payments WHERE id = %d", intval($id)));
    }

    public static function delete_payment($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}sm_payments", ['id' => intval($id)]);
    }

    public static function get_payments($args = []) {
        global $wpdb;
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = "1=1";
        $params = [];

        if (!$has_full_access && $my_gov) {
            $where .= " AND m.governorate = %s";
            $params[] = $my_gov;
        }

        if (!empty($args['day'])) { $where .= " AND DAY(p.payment_date) = %d"; $params[] = intval($args['day']); }
        if (!empty($args['month'])) { $where .= " AND MONTH(p.payment_date) = %d"; $params[] = intval($args['month']); }
        if (!empty($args['year'])) { $where .= " AND YEAR(p.payment_date) = %d"; $params[] = intval($args['year']); }

        if (!empty($args['search'])) {
            $where .= " AND (m.name LIKE %s OR m.national_id LIKE %s)";
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $s; $params[] = $s;
        }

        if (isset($args['include']) && !empty($args['include'])) {
            $include_ids = array_map('intval', (array)$args['include']);
            if (!empty($include_ids)) {
                $where .= " AND p.id IN (" . implode(',', $include_ids) . ")";
            }
        }

        $limit = isset($args['limit']) ? intval($args['limit']) : 500;
        $query = "SELECT p.*, m.name as member_name, m.governorate as member_gov, u.display_name as staff_name
                  FROM {$wpdb->prefix}sm_payments p
                  JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id
                  LEFT JOIN {$wpdb->base_prefix}users u ON p.created_by = u.ID
                  WHERE $where ORDER BY p.created_at DESC";

        if ($limit != -1) {
            $query .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_statistics($filters = array()) {
        global $wpdb;

        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
        $target_gov = $filters['governorate'] ?? null;

        // Caching Logic
        $cache_key = 'sm_stats_' . ($target_gov ?: ($has_full_access ? 'global' : $my_gov));
        $cached = get_transient($cache_key);
        if ($cached !== false && empty($filters['no_cache'])) return $cached;

        $stats = array();
        $is_officer = in_array('sm_syndicate_admin', (array)$user->roles);

        $where_member = "1=1";
        if ($target_gov) {
            $where_member = $wpdb->prepare("governorate = %s", $target_gov);
        } elseif (!$has_full_access) {
            if ($my_gov) {
                $where_member = $wpdb->prepare("governorate = %s", $my_gov);
            } else {
                $where_member = "1=0";
            }
        }

        $query_count = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_members WHERE $where_member";
        if (strpos($where_member, '%') !== false) {
             // Basic check if prepare might be needed if target_gov was passed
             $stats['total_members'] = $wpdb->get_var($wpdb->prepare($query_count, $target_gov ?: $my_gov));
        } else {
             $stats['total_members'] = $wpdb->get_var($query_count);
        }
        $stats['total_officers'] = count(SM_DB_Members::get_staff(['number' => -1]));

        // Total Board Members
        $stats['total_board'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}users u
             JOIN {$wpdb->prefix}usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = '{$wpdb->prefix}capabilities'
             JOIN {$wpdb->prefix}usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'sm_rank'
             WHERE um1.meta_value LIKE %s AND um2.meta_value != ''",
            '%"sm_syndicate_admin"%'
        ));

        // Total Revenue
        $join_member_rev = "";
        $where_rev = "1=1";
        if (!$has_full_access) {
            if ($my_gov) {
                $join_member_rev = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
                $where_rev = $wpdb->prepare("m.governorate = %s", $my_gov);
            } else {
                $where_rev = "1=0";
            }
        }
        $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}sm_payments p $join_member_rev WHERE $where_rev") ?: 0;

        // Financial Trends (Last 30 Days)
        $join_member = "";
        $where_finance = "payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        if (!$has_full_access) {
            if ($my_gov) {
                $join_member = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
                $where_finance .= $wpdb->prepare(" AND m.governorate = %s", $my_gov);
            } else {
                $where_finance .= " AND 1=0";
            }
        }

        $stats['financial_trends'] = $wpdb->get_results("
            SELECT DATE(payment_date) as date, SUM(amount) as total
            FROM {$wpdb->prefix}sm_payments p
            $join_member
            WHERE $where_finance
            GROUP BY DATE(payment_date)
            ORDER BY date ASC
        ");

        // Specialization Distribution
        $stats['specializations'] = $wpdb->get_results("
            SELECT specialization, COUNT(*) as count
            FROM {$wpdb->prefix}sm_members
            WHERE specialization != '' AND $where_member
            GROUP BY specialization
        ");

        // Advanced Stats
        $stats['total_service_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_service_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where_member
        ") ?: 0;

        $stats['total_executed_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_service_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE r.status = 'approved' AND $where_member
        ") ?: 0;

        $stats['total_update_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_update_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where_member
        ") ?: 0;

        $stats['total_membership_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_membership_requests
            WHERE $where_member
        ") ?: 0;

        $stats['total_requests'] = intval($stats['total_service_requests']) + intval($stats['total_update_requests']) + intval($stats['total_membership_requests']);

        $stats['total_practice_licenses'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_members
            WHERE license_number != '' AND $where_member
        ") ?: 0;

        $stats['total_facility_licenses'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_members
            WHERE facility_number != '' AND $where_member
        ") ?: 0;

        // Work permits (assumed same as practice licenses in this context)
        $stats['total_work_permits'] = $stats['total_practice_licenses'];

        set_transient($cache_key, $stats, HOUR_IN_SECONDS);
        return $stats;
    }
}
