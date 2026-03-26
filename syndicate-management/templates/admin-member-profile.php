<?php if (!defined('ABSPATH')) exit;

$member_id = intval($_GET['member_id'] ?? 0);
$member = SM_DB::get_member_by_id($member_id);

if (!$member) {
    echo '<div class="error"><p>العضو غير موجود.</p></div>';
    return;
}

$user = wp_get_current_user();
$is_admin = current_user_can('administrator') || current_user_can('manage_options');
$is_sys_manager = current_user_can('sm_manage_system');
$is_syndicate_admin = current_user_can('sm_branch_access') && !current_user_can('sm_full_access');
$is_general_officer = current_user_can('sm_full_access') && !current_user_can('sm_manage_system');
$is_member = in_array('sm_member', (array)$user->roles);
$is_restricted = !current_user_can('sm_manage_members');

$db_branches = SM_DB::get_branches_data();

// IDOR CHECK: Restricted users can only see their own profile
if ($is_restricted) {
    if ($member->wp_user_id != $user->ID) {
        echo '<div class="error" style="padding: 20px; background:#fff5f5; color:#c53030; border-radius:8px; border:1px solid #feb2b2;"><h4>⚠️ عذراً، لا تملك صلاحية الوصول لهذا الملف.</h4><p>لا يمكنك استعراض بيانات الأعضاء الآخرين.</p></div>';
        return;
    }
}

// GEOGRAPHIC ACCESS CHECK
if ($is_syndicate_admin) {
    $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
    if ($my_gov && $member->governorate !== $my_gov) {
        echo '<div class="error" style="padding: 20px; background:#fff5f5; color:#c53030; border-radius:8px; border:1px solid #feb2b2;"><h4>⚠️ عذراً، لا تملك صلاحية الوصول لهذا الملف.</h4><p>هذا العضو يتبع لفرع أخرى غير المسجلة في حسابك.</p></div>';
        return;
    }
}

$grades = SM_Settings::get_professional_grades();
$specs = SM_Settings::get_specializations();
$govs = SM_Settings::get_governorates();
$statuses = SM_Settings::get_membership_statuses();
$finance = SM_Finance::calculate_member_dues($member);
$acc_status = SM_Finance::get_member_status($member->id);
?>

<div class="sm-member-profile-view <?php echo $is_restricted ? 'sm-portal-layout' : ''; ?>" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 30px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="position: relative;">
                <div id="member-photo-container" style="width: 80px; height: 80px; background: #f0f4f8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; border: 3px solid var(--sm-primary-color); overflow: hidden;">
                    <?php if ($member->photo_url): ?>
                        <img src="<?php echo esc_url($member->photo_url); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        👤
                    <?php endif; ?>
                </div>
                <button onclick="smTriggerPhotoUpload()" style="position: absolute; bottom: 0; right: 0; background: var(--sm-primary-color); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                    <span class="dashicons dashicons-camera" style="font-size: 14px; width: 14px; height: 14px;"></span>
                </button>
                <input type="file" id="member-photo-input" style="display:none;" accept="image/*" onchange="smUploadMemberPhoto(<?php echo $member->id; ?>)">
            </div>
            <div>
                <h2 style="margin:0; color: var(--sm-dark-color);"><?php echo esc_html($member->name); ?></h2>
                <div style="display: flex; gap: 10px; margin-top: 5px;">
                    <span class="sm-badge sm-badge-low"><?php echo $grades[$member->professional_grade] ?? $member->professional_grade; ?></span>
                    <span class="sm-badge" style="background: #e2e8f0; color: #4a5568;"><?php echo esc_html(SM_Settings::get_branch_name($member->governorate)); ?></span>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($is_restricted): ?>
                <button onclick="smOpenUpdateMemberRequestModal()" class="sm-btn" style="background: #3182ce; width: auto;"><span class="dashicons dashicons-edit"></span> طلب تحديث بياناتي</button>
            <?php elseif (current_user_can('sm_manage_members')): ?>
                <button onclick="editSmMember(JSON.parse(this.dataset.member))" data-member='<?php echo esc_attr(wp_json_encode($member)); ?>' class="sm-btn" style="background: #3182ce; width: auto;"><span class="dashicons dashicons-edit"></span> تعديل البيانات</button>
            <?php endif; ?>

            <div class="sm-dropdown" style="position:relative; display:inline-block;">
                <button class="sm-btn" style="background: #111F35; width: auto;" onclick="smToggleFinanceDropdown()"><span class="dashicons dashicons-money-alt"></span> المعاملات المالية <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 10px;"></span></button>
                <div id="sm-finance-dropdown" style="display:none; position:absolute; left:0; top:100%; background:white; border:1px solid #eee; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:100; min-width:200px; padding:10px 0;">
                    <?php if (current_user_can('sm_manage_finance')): ?>
                        <a href="javascript:smOpenFinanceModal(<?php echo $member->id; ?>)" class="sm-dropdown-item"><span class="dashicons dashicons-plus"></span> تأكيد سداد دفعة</a>
                    <?php endif; ?>
                    <a href="<?php echo add_query_arg('sm_tab', 'financial-logs'); ?>&member_search=<?php echo urlencode($member->national_id); ?>" class="sm-dropdown-item"><span class="dashicons dashicons-media-spreadsheet"></span> سجل الفواتير والعمليات</a>
                </div>
            </div>

            <?php if (current_user_can('sm_print_reports')): ?>
                <a href="<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=id_card&member_id='.$member->id); ?>" target="_blank" class="sm-btn" style="background: #27ae60; width: auto; text-decoration:none; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-id-alt"></span> طباعة الكارنيه</a>
            <?php endif; ?>
            <?php if ($is_sys_manager): ?>
                <button onclick="deleteMember(<?php echo $member->id; ?>, '<?php echo esc_js($member->name); ?>')" class="sm-btn" style="background: #e53e3e; width: auto;"><span class="dashicons dashicons-trash"></span> حذف العضو</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_restricted): ?>
    <div class="sm-portal-grid" style="display: flex; gap: 30px;">
        <!-- Right Sidebar Navigation -->
        <div class="sm-portal-sidebar" style="width: 300px; flex-shrink: 0;">
            <div style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 16px; padding: 15px; position: sticky; top: 20px; box-shadow: var(--sm-shadow);">
                <div style="padding: 15px 10px 25px; border-bottom: 1px solid #f1f5f9; margin-bottom: 15px; text-align: center;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 15px; background: #f8fafc; border-radius: 50%; border: 3px solid var(--sm-primary-color); padding: 3px;">
                        <img src="<?php echo esc_url($member->photo_url ?: get_avatar_url($user->ID)); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    </div>
                    <h4 style="margin: 0; font-weight: 900; color: var(--sm-dark-color);"><?php echo esc_html($member->name); ?></h4>
                    <div style="font-size: 11px; color: #64748b; margin-top: 5px;">رقم القيد: <?php echo esc_html($member->membership_number); ?></div>
                </div>

                <nav class="sm-portal-nav" style="display: flex; flex-direction: column; gap: 5px;">
                    <button class="sm-portal-nav-btn sm-active" onclick="smOpenInternalTab('profile-info', this)">
                        <span class="dashicons dashicons-admin-users"></span> <span>بيانات العضوية</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('professional-requests-tab', this)">
                        <span class="dashicons dashicons-awards"></span> <span>الطلبات المهنية</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('license-status-tab', this)">
                        <span class="dashicons dashicons-id-alt"></span> <span>حالة التراخيص</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('finance-management', this)">
                        <span class="dashicons dashicons-money-alt"></span> <span>المالية والاستحقاقات</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('document-vault', this); smLoadDocuments();">
                        <span class="dashicons dashicons-portfolio"></span> <span>الأرشيف الرقمي</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('messaging-hub-tab', this); setTimeout(() => smSwitchMessagingTab('direct-comm', $('.sm-messaging-center .sm-tab-btn')[1]), 100);">
                        <span class="dashicons dashicons-email"></span> <span>المراسلات</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('messaging-hub-tab', this); setTimeout(() => smSwitchMessagingTab('tickets', $('.sm-messaging-center .sm-tab-btn')[0]), 100);">
                        <span class="dashicons dashicons-megaphone"></span> <span>الشكاوى والدعم</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('digital-services-tab', this)">
                        <span class="dashicons dashicons-cloud"></span> <span>الخدمات الرقمية</span>
                    </button>
                    <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('exams-tab', this)">
                        <span class="dashicons dashicons-welcome-learn-more"></span> <span>الاختبارات المهنية</span>
                    </button>
                </nav>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="sm-portal-content" style="flex: 1; min-width: 0;">
    <?php else: ?>
        <!-- Profile Tabs (Management View - Standard Tabs) -->
        <div class="sm-tabs-wrapper" style="display: flex; gap: 5px; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; overflow-x: auto; white-space: nowrap;">
            <button class="sm-tab-btn sm-active" onclick="smOpenInternalTab('profile-info', this)"><span class="dashicons dashicons-admin-users"></span> بيانات العضوية</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('professional-requests-tab', this)"><span class="dashicons dashicons-awards"></span> الطلبات المهنية</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('license-status-tab', this)"><span class="dashicons dashicons-id-alt"></span> حالة التراخيص</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('finance-management', this)"><span class="dashicons dashicons-money-alt"></span> المالية والاستحقاقات</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('document-vault', this); smLoadDocuments();"><span class="dashicons dashicons-portfolio"></span> الأرشيف الرقمي</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('messaging-hub-tab', this); setTimeout(() => smSwitchMessagingTab('direct-comm', $('.sm-messaging-center .sm-tab-btn')[1]), 100);"><span class="dashicons dashicons-email"></span> المراسلات</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('messaging-hub-tab', this); setTimeout(() => smSwitchMessagingTab('tickets', $('.sm-messaging-center .sm-tab-btn')[0]), 100);"><span class="dashicons dashicons-megaphone"></span> الشكاوى والدعم</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('digital-services-tab', this)"><span class="dashicons dashicons-cloud"></span> الخدمات الرقمية</button>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('exams-tab', this)"><span class="dashicons dashicons-welcome-learn-more"></span> الاختبارات المهنية</button>
        </div>
    <?php endif; ?>

    <div id="profile-info" class="sm-internal-tab">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <!-- Basic Info -->
                <div style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">البيانات الأساسية والأكاديمية</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div><label class="sm-label">الرقم القومي:</label> <div class="sm-value"><?php echo esc_html($member->national_id); ?></div></div>
                    <div><label class="sm-label">كود العضوية:</label> <div class="sm-value"><?php echo esc_html($member->membership_number); ?></div></div>
                    <div><label class="sm-label">رقم الهاتف:</label> <div class="sm-value"><?php echo esc_html($member->phone); ?></div></div>
                    <div><label class="sm-label">البريد الإلكتروني:</label> <div class="sm-value"><?php echo esc_html($member->email); ?></div></div>
                </div>

                <?php
                $univs = SM_Settings::get_universities();
                $facs = SM_Settings::get_faculties();
                $depts = SM_Settings::get_departments();
                $degrees = SM_Settings::get_academic_degrees();
                ?>
                <h4 style="margin: 20px 0 10px 0; color: var(--sm-primary-color);">المؤهلات العلمية</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div><label class="sm-label">الجامعة:</label> <div class="sm-value"><?php echo esc_html($univs[$member->university] ?? $member->university); ?></div></div>
                    <div><label class="sm-label">الكلية:</label> <div class="sm-value"><?php echo esc_html($facs[$member->faculty] ?? $member->faculty); ?></div></div>
                    <div><label class="sm-label">القسم:</label> <div class="sm-value"><?php echo esc_html($depts[$member->department] ?? $member->department); ?></div></div>
                    <div><label class="sm-label">تاريخ التخرج:</label> <div class="sm-value"><?php echo esc_html($member->graduation_date); ?></div></div>
                    <div><label class="sm-label">التخصص:</label> <div class="sm-value"><?php echo esc_html($specs[$member->specialization] ?? $member->specialization); ?></div></div>
                    <div><label class="sm-label">الدرجة العلمية:</label> <div class="sm-value"><?php echo esc_html($degrees[$member->academic_degree] ?? $member->academic_degree); ?></div></div>
                </div>

                <h4 style="margin: 20px 0 10px 0; color: var(--sm-primary-color);">بيانات السكن والاتصال</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div><label class="sm-label">فرع الميلاد:</label> <div class="sm-value"><?php echo esc_html($member->province_of_birth ?: '---'); ?></div></div>
                    <div><label class="sm-label">فرع السكن:</label> <div class="sm-value"><?php echo esc_html($govs[$member->residence_governorate] ?? $member->residence_governorate); ?></div></div>
                    <div><label class="sm-label">المدينة / المركز:</label> <div class="sm-value"><?php echo esc_html($member->residence_city); ?></div></div>
                    <div style="grid-column: span 2;"><label class="sm-label">العنوان (الشارع / القرية):</label> <div class="sm-value"><?php echo esc_html($member->residence_street); ?></div></div>
                    <div><label class="sm-label">الفرع النقابي التابع له:</label> <div class="sm-value"><?php echo esc_html(SM_Settings::get_branch_name($member->governorate)); ?></div></div>
                </div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 30px;">
            <!-- Account Status -->
            <div style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">حالة الحساب</h3>
                <div style="text-align: center; padding: 20px 0;">
                    <?php
                        $u = new WP_User($member->wp_user_id);
                        $has_pass = !empty($u->user_pass);
                    ?>
                    <div style="font-size: 0.9em; color: #718096;">حالة تفعيل الدخول</div>
                    <div style="font-size: 1.5em; font-weight: 900; color: <?php echo $has_pass ? '#38a169' : '#e53e3e'; ?>;">
                        <?php echo $has_pass ? 'مفعل' : 'غير مفعل'; ?>
                    </div>
                </div>
            </div>

            <!-- Financial Status -->
            <div style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">الوضع المالي</h3>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 0.9em; color: #718096;">إجمالي المستحق</div>
                    <div style="font-size: 2.2em; font-weight: 900; color: <?php echo $finance['balance'] > 0 ? '#e53e3e' : '#38a169'; ?>;">
                        <?php echo number_format($finance['balance'], 2); ?> ج.م
                    </div>
                </div>
                <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; justify-content: space-between;"><span>المبلغ المطلوب سداده:</span> <strong><?php echo number_format($finance['total_owed'], 2); ?></strong></div>
                    <div style="display: flex; justify-content: space-between;"><span>إجمالي ما تم سداده:</span> <strong style="color:#38a169;"><?php echo number_format($finance['total_paid'], 2); ?></strong></div>
                </div>
                    <button onclick="smOpenFinanceModal(<?php echo $member->id; ?>)" class="sm-btn" style="margin-top: 20px; background: var(--sm-dark-color);">
                        <?php echo (current_user_can('sm_manage_finance')) ? 'إدارة المدفوعات والفواتير' : 'عرض كشف الحساب'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Requests Tab -->
    <div id="professional-requests-tab" class="sm-internal-tab" style="display: none;">
        <?php
            $_GET['member_id'] = $member->id;
            include SM_PLUGIN_DIR . 'templates/admin-professional-requests.php';
        ?>
    </div>

    <!-- License Status Tab (Merged View) -->
    <div id="license-status-tab" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/public-member-licenses.php'; ?>
    </div>

    <!-- Finance Management Tab -->
    <div id="finance-management" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/member-finance-tab.php'; ?>
    </div>

    <!-- Document Vault Tab -->
    <div id="document-vault" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/member-document-vault.php'; ?>
    </div>

    <!-- Messaging Hub Tab (Consolidated) -->
    <div id="messaging-hub-tab" class="sm-internal-tab" style="display: none;">
        <div style="min-height: 600px; border: 1px solid #eee; border-radius: 12px; overflow: hidden; background: #fff;">
            <?php include SM_PLUGIN_DIR . 'templates/messaging-center.php'; ?>
        </div>
    </div>

    <!-- Digital Services Tab -->
    <div id="digital-services-tab" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/admin-services.php'; ?>
    </div>

    <!-- Exams Tab -->
    <div id="exams-tab" class="sm-internal-tab" style="display: none;">
        <div style="background:#fff; padding: 30px; border-radius:12px; border:1px solid #e2e8f0; min-height:400px;">
            <h3 style="margin:0 0 10px 0; font-weight:800; color:var(--sm-dark-color);">اختبارات الممارسة المهنية</h3>
            <p style="color:#64748b; margin-bottom: 25px; font-size:14px;">يرجى التقدم للاختبارات المقررة للحصول على أو تجديد تراخيص مزاولة المهنة.</p>
            <?php include SM_PLUGIN_DIR . 'templates/public-dashboard-summary.php'; ?>
        </div>
    </div>

    <?php if ($is_restricted): ?>
        </div> <!-- End sm-portal-content -->
    </div> <!-- End sm-portal-grid -->
    <?php endif; ?>

    <!-- Edit Member Modal -->
    <div id="edit-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 900px;">
            <div class="sm-modal-header"><h3>تعديل بيانات العضو</h3><button class="sm-modal-close" onclick="document.getElementById('edit-member-modal').style.display='none'">&times;</button></div>
            <form id="edit-member-form">
                <?php wp_nonce_field('sm_add_member', 'sm_nonce'); ?>
                <input type="hidden" name="member_id" id="edit_member_id_hidden">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; padding: 20px;">
                    <div class="sm-form-group"><label class="sm-label">الاسم الكامل:</label><input name="name" id="edit_name" type="text" class="sm-input" required></div>
                    <div class="sm-form-group"><label class="sm-label">الرقم القومي:</label><input name="national_id" id="edit_national_id" type="text" class="sm-input" required maxlength="14"></div>
                    <div class="sm-form-group"><label class="sm-label">الدرجة الوظيفية:</label><select name="professional_grade" id="edit_grade" class="sm-select"><?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>

                    <div class="sm-form-group"><label class="sm-label">الجامعة:</label><select name="university" id="edit_university" class="sm-select edit-cascading"><?php foreach (SM_Settings::get_universities() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">الكلية:</label><select name="faculty" id="edit_faculty" class="sm-select edit-cascading"><?php foreach (SM_Settings::get_faculties() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">القسم:</label><select name="department" id="edit_department" class="sm-select edit-cascading"><?php foreach (SM_Settings::get_departments() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">تاريخ التخرج:</label><input name="graduation_date" id="edit_grad_date" type="date" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">الدرجة العلمية:</label>
                        <select name="academic_degree" id="edit_degree" class="sm-select">
                            <?php foreach(SM_Settings::get_academic_degrees() as $k=>$v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group"><label class="sm-label">التخصص:</label><select name="specialization" id="edit_spec" class="sm-select edit-cascading"><?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>

                    <div class="sm-form-group"><label class="sm-label">فرع السكن:</label><select name="residence_governorate" id="edit_res_gov" class="sm-select"><?php foreach (SM_Settings::get_governorates() as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">المدينة / المركز:</label><input name="residence_city" id="edit_res_city" type="text" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">فرع الفرع:</label><select name="governorate" id="edit_gov" class="sm-select"><?php
                        if (!empty($db_branches)) {
                            foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."'>".esc_html($db->name)."</option>";
                        } else {
                            foreach (SM_Settings::get_governorates() as $k => $v) echo "<option value='$k'>$v</option>";
                        }
                    ?></select></div>

                    <div class="sm-form-group" style="grid-column: span 3;"><label class="sm-label">العنوان (الشارع / القرية):</label><input name="residence_street" id="edit_res_street" type="text" class="sm-input"></div>

                    <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input name="phone" id="edit_phone" type="text" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input name="email" id="edit_email" type="email" class="sm-input"></div>
                    <div class="sm-form-group" style="grid-column: span 3;"><label class="sm-label">ملاحظات:</label><textarea name="notes" id="edit_notes" class="sm-input" rows="2"></textarea></div>
                </div>
                <button type="submit" class="sm-btn">تحديث البيانات الآن</button>
            </form>
        </div>
    </div>

    <!-- Member Update Request Modal -->
    <div id="member-update-request-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 800px;">
            <div class="sm-modal-header">
                <h3>طلب تحديث بيانات العضوية</h3>
                <button class="sm-modal-close" onclick="document.getElementById('member-update-request-modal').style.display='none'">&times;</button>
            </div>
            <div style="padding: 30px; background: #fffaf0; border-bottom: 1px solid #feebc8; font-size: 13px; color: #744210;">
                <span class="dashicons dashicons-info" style="font-size: 16px;"></span> سيتم إرسال طلبك للمراجعة من قبل إدارة النقابة قبل اعتماده رسمياً في النظام.
            </div>
            <form id="member-update-request-form">
                <input type="hidden" name="member_id" value="<?php echo $member->id; ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 30px;">
                    <div class="sm-form-group"><label class="sm-label">الاسم الكامل:</label><input type="text" name="name" class="sm-input" value="<?php echo esc_attr($member->name); ?>" required></div>
                    <div class="sm-form-group"><label class="sm-label">الرقم القومي:</label><input type="text" name="national_id" class="sm-input" value="<?php echo esc_attr($member->national_id); ?>" required maxlength="14"></div>

                    <div class="sm-form-group"><label class="sm-label">الجامعة:</label><select name="university" class="sm-select academic-cascading"><?php foreach(SM_Settings::get_universities() as $k=>$v) echo "<option value='$k' ".selected($member->university, $k, false).">$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">الكلية:</label><select name="faculty" class="sm-select academic-cascading"><?php foreach(SM_Settings::get_faculties() as $k=>$v) echo "<option value='$k' ".selected($member->faculty, $k, false).">$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">القسم:</label><select name="department" class="sm-select academic-cascading"><?php foreach(SM_Settings::get_departments() as $k=>$v) echo "<option value='$k' ".selected($member->department, $k, false).">$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">تاريخ التخرج:</label><input name="graduation_date" type="date" class="sm-input" value="<?php echo esc_attr($member->graduation_date); ?>"></div>
                    <div class="sm-form-group"><label class="sm-label">الدرجة العلمية:</label>
                        <select name="academic_degree" class="sm-select">
                            <?php foreach(SM_Settings::get_academic_degrees() as $k=>$v) echo "<option value='$k' ".selected($member->academic_degree, $k, false).">$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group"><label class="sm-label">التخصص:</label><select name="specialization" class="sm-select academic-cascading"><?php foreach ($specs as $k => $v) echo "<option value='$k' ".selected($member->specialization, $k, false).">$v</option>"; ?></select></div>

                    <div class="sm-form-group"><label class="sm-label">فرع السكن:</label><select name="residence_governorate" class="sm-select"><?php foreach ($govs as $k => $v) echo "<option value='$k' ".selected($member->residence_governorate, $k, false).">$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">المدينة / المركز:</label><input name="residence_city" type="text" class="sm-input" value="<?php echo esc_attr($member->residence_city); ?>"></div>
                    <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">العنوان (الشارع / القرية):</label><input name="residence_street" type="text" class="sm-input" value="<?php echo esc_attr($member->residence_street); ?>"></div>

                    <div class="sm-form-group"><label class="sm-label">فرع الفرع:</label><select name="governorate" class="sm-select">
                        <?php
                        if (!empty($db_branches)) {
                            foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."' ".selected($member->governorate, $db->slug, false).">".esc_html($db->name)."</option>";
                        } else {
                            foreach ($govs as $k => $v) echo "<option value='$k' ".selected($member->governorate, $k, false).">$v</option>";
                        }
                        ?>
                    </select></div>
                    <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input type="text" name="phone" class="sm-input" value="<?php echo esc_attr($member->phone); ?>"></div>
                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input type="email" name="email" class="sm-input" value="<?php echo esc_attr($member->email); ?>"></div>
                    <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">سبب التحديث / ملاحظات إضافية:</label><textarea name="notes" class="sm-input" rows="2"></textarea></div>
                </div>
                <div style="padding: 0 25px 25px;">
                    <button type="submit" class="sm-btn" style="width: 100%; height: 45px; font-weight: 700;">إرسال طلب التحديث للمراجعة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function smToggleFinanceDropdown() {
    const el = document.getElementById('sm-finance-dropdown');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function smToggleCardOptions(id) {
    const el = document.getElementById(id);
    const all = document.querySelectorAll('.sm-dropdown-menu');
    all.forEach(m => { if(m.id !== id) m.style.display = 'none'; });
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

window.addEventListener('load', function() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('profile_tab');
    if (tab) {
        const tabMap = {
            'info': 'profile-info',
            'requests': 'professional-requests-tab',
            'licenses': 'license-status-tab',
            'finance': 'finance-management',
            'archive': 'document-vault',
            'correspondence': 'messaging-hub-tab',
            'complaints': 'messaging-hub-tab',
            'services': 'digital-services-tab',
            'exams': 'exams-tab'
        };
        const targetId = tabMap[tab];
        if (targetId) {
            const btn = document.querySelector(`[onclick*="'${targetId}'"]`);
            if (btn) btn.click();
        }
    }
});

function smRequestPermitTest(mid) { smSubmitProfRequest('permit_test', mid); }
function smRequestPermitRenewal(mid) { smSubmitProfRequest('permit_renewal', mid); }
function smRequestFacilityLicense(mid) { smSubmitProfRequest('facility_new', mid); }
function smRequestFacilityRenewal(mid) { smSubmitProfRequest('facility_renewal', mid); }

function smTriggerPhotoUpload() {
    document.getElementById('member-photo-input').click();
}

function smUploadMemberPhoto(memberId) {
    const file = document.getElementById('member-photo-input').files[0];
    if (!file) return;

    const action = 'sm_update_member_photo';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('member_id', memberId);
    formData.append('member_photo', file);
    formData.append('sm_photo_nonce', '<?php echo wp_create_nonce("sm_photo_action"); ?>');

    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data && res.data.photo_url) {
            document.getElementById('member-photo-container').innerHTML = `<img src="${res.data.photo_url}" style="width:100%; height:100%; object-fit:cover;">`;
            smShowNotification('تم تحديث الصورة الشخصية');
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smOpenUpdateMemberRequestModal() {
    document.getElementById('member-update-request-modal').style.display = 'flex';
}

document.getElementById('member-update-request-form').onsubmit = function(e) {
    e.preventDefault();
    const action = 'sm_submit_update_request_ajax';
    const formData = new FormData(this);
    formData.append('action', action);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_update_request"); ?>');

    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم إرسال طلب التحديث بنجاح. سنقوم بمراجعته قريباً.');
            document.getElementById('member-update-request-modal').style.display = 'none';
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
};

function deleteMember(id, name) {
    if (!confirm('هل أنت متأكد من حذف العضو: ' + name + ' نهائياً من النظام؟ لا يمكن التراجع عن هذا الإجراء.')) return;
    const action = 'sm_delete_member_ajax';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('member_id', id);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_delete_member"); ?>');

    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم حذف العضو بنجاح');
            setTimeout(() => {
                window.location.href = '<?php echo add_query_arg('sm_tab', 'members'); ?>';
            }, 1000);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

window.editSmMember = function(s) {
    document.getElementById('edit_member_id_hidden').value = s.id;
    document.getElementById('edit_name').value = s.name;
    document.getElementById('edit_national_id').value = s.national_id;
    document.getElementById('edit_grade').value = s.professional_grade;
    document.getElementById('edit_university').value = s.university || '';
    document.getElementById('edit_faculty').value = s.faculty || '';
    document.getElementById('edit_department').value = s.department || '';
    document.getElementById('edit_grad_date').value = s.graduation_date || '';
    document.getElementById('edit_degree').value = s.academic_degree || '';
    document.getElementById('edit_spec').value = s.specialization || '';
    document.getElementById('edit_res_gov').value = s.residence_governorate || '';
    document.getElementById('edit_res_city').value = s.residence_city || '';
    document.getElementById('edit_res_street').value = s.residence_street || '';
    document.getElementById('edit_gov').value = s.governorate;
    document.getElementById('edit_phone').value = s.phone;
    document.getElementById('edit_email').value = s.email;
    document.getElementById('edit_notes').value = s.notes || '';

    // Enable cascading fields if values exist
    const fac = document.getElementById('edit_faculty');
    const dept = document.getElementById('edit_department');
    const spec = document.getElementById('edit_spec');
    if (s.university) fac.disabled = false;
    if (s.faculty) dept.disabled = false;
    if (s.department) spec.disabled = false;

    document.getElementById('edit-member-modal').style.display = 'flex';
};

const applyCascading = (selector) => {
    const elements = document.querySelectorAll(selector);
    elements.forEach((el, idx) => {
        el.addEventListener("change", function() {
            if (this.value && idx < elements.length - 1) {
                elements[idx + 1].disabled = false;
            } else if (!this.value) {
                for (let i = idx + 1; i < elements.length; i++) {
                    elements[i].value = "";
                    elements[i].disabled = true;
                }
            }
        });
    });
};
applyCascading("#edit-member-form .edit-cascading");
applyCascading("#member-update-request-form .academic-cascading");

document.getElementById('edit-member-form').onsubmit = function(e) {
    e.preventDefault();
    const action = 'sm_update_member_ajax';
    const formData = new FormData(this);
    if (!formData.has('action')) formData.append('action', action);
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم تحديث البيانات بنجاح');
            setTimeout(() => location.reload(), 1000);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
};

document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('sm-finance-dropdown');
    const btn = document.querySelector('[onclick="smToggleFinanceDropdown()"]');
    if (dropdown && !dropdown.contains(e.target) && btn && !btn.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});
</script>
