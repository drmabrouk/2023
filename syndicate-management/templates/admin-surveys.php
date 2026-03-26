<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-surveys-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin:0;">إدارة اختبارات الممارسة المهنية</h3>
        <div style="display: flex; gap: 10px;">
            <button onclick="smOpenPrintCustomizer('surveys')" class="sm-btn" style="background: #4a5568; width: auto;"><span class="dashicons dashicons-printer"></span> طباعة مخصصة</button>
            <button class="sm-btn" onclick="smOpenNewSurveyModal()" style="width: auto;">+ إنشاء اختبار جديد</button>
        </div>
    </div>

    <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
        <button class="sm-tab-btn sm-active" onclick="smOpenInternalTab('tests-list', this)">الاختبارات المتاحة</button>
        <button class="sm-tab-btn" onclick="smOpenInternalTab('active-sessions', this)">المراقبة المباشرة</button>
    </div>

    <?php
    global $wpdb;
    $user = wp_get_current_user();
    $roles = (array)$user->roles;
    $is_sys_admin = in_array('administrator', $roles) || current_user_can('manage_options');
    $is_general_officer = in_array('sm_general_officer', $roles);
    $is_branch_officer = in_array('sm_branch_officer', $roles);
    $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
    $test_type_map = ['practice' => 'مزاولة مهنة', 'promotion' => 'ترقية درجة', 'training' => 'دورة تدريبية'];
    $db_branches = SM_DB::get_branches_data();
    if (!is_array($db_branches)) $db_branches = [];
    ?>

    <!-- TAB: Tests List -->
    <div id="tests-list" class="sm-internal-tab">
        <!-- Advanced Filter & Search Engine -->
        <div style="background: #f8fafc; padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
        <div style="flex: 2; min-width: 250px;">
            <label class="sm-label" style="font-size: 12px; margin-bottom: 8px; display: block; color: #64748b;">ابحث باسم الاختبار:</label>
            <div style="position: relative;">
                <input type="text" id="test_search_input" class="sm-input" placeholder="اكتب اسم الاختبار للبحث..." oninput="smApplyTestFilters()">
                <span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 12px; color: #94a3b8;"></span>
            </div>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label class="sm-label" style="font-size: 12px; margin-bottom: 8px; display: block; color: #64748b;">تصفية بالنوع:</label>
            <select id="test_type_filter" class="sm-select" onchange="smApplyTestFilters()">
                <option value="all">كل الأنواع</option>
                <?php foreach($test_type_map as $k => $v) echo "<option value='$k'>$v</option>"; ?>
            </select>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label class="sm-label" style="font-size: 12px; margin-bottom: 8px; display: block; color: #64748b;">تصفية بالفرع:</label>
            <select id="test_branch_filter" class="sm-select" onchange="smApplyTestFilters()">
                <option value="all">كل الفروع</option>
                <option value="all_branches">عام (لكل الفروع)</option>
                <?php
                foreach($db_branches as $b) echo "<option value='".esc_attr($b->slug)."'>".esc_html($b->name)."</option>";
                ?>
            </select>
        </div>
        <button class="sm-btn sm-btn-outline" onclick="smResetTestFilters()" style="height: 45px; width: auto; padding: 0 20px;">إعادة تعيين</button>
    </div>

        <div class="sm-table-container" style="border-radius: 15px; overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
            <table class="sm-table" id="tests-admin-table">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th>بيانات الاختبار</th>
                        <th>الإعدادات والوقت</th>
                        <th>الفرع / التخصص</th>
                        <th>تاريخ البدء</th>
                        <th>الحالة</th>
                        <th>المشاركات</th>
                        <th style="text-align: left;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $surveys = SM_DB::get_surveys_admin();
                    if (!is_array($surveys)) $surveys = [];

                    $specs_labels = SM_Settings::get_specializations();
                    foreach ($surveys as $s):
                        $responses = SM_DB::get_survey_responses($s->id);
                        $responses_count = is_array($responses) ? count($responses) : 0;
                        $questions = SM_DB::get_test_questions($s->id);
                        $questions_count = is_array($questions) ? count($questions) : 0;
                        $branch_label = ($s->branch === 'all') ? 'كل الفروع' : (SM_Settings::get_branch_name($s->branch) ?: $s->branch);
                    ?>
                    <tr class="sm-test-row"
                        data-title="<?php echo esc_attr($s->title); ?>"
                        data-type="<?php echo esc_attr($s->test_type); ?>"
                        data-branch="<?php echo esc_attr($s->branch); ?>">
                        <td>
                            <div style="font-weight: 800; color:var(--sm-dark-color); font-size: 1.1em;"><?php echo esc_html($s->title); ?></div>
                            <div style="font-size: 11px; color:#64748b; margin-top:5px; display: flex; align-items: center; gap: 5px;">
                                <span class="dashicons dashicons-editor-help" style="font-size:14px; width:14px; height:14px; color: var(--sm-primary-color);"></span> <?php echo $questions_count; ?> سؤال مدرج
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12px; margin-bottom: 3px;">⏰ <span style="font-weight: 700;"><?php echo $s->time_limit; ?></span> دقيقة</div>
                            <div style="font-size: 12px; color:#38a169; font-weight:700;">🎯 نجاح: <?php echo $s->pass_score; ?>%</div>
                        </td>
                        <td>
                            <div style="font-size: 12px; font-weight:800; color:var(--sm-primary-color);"><?php echo $branch_label; ?></div>
                            <div style="font-size: 11px; color:#64748b; margin-top: 3px;"><?php echo !empty($s->specialty) ? ($specs_labels[$s->specialty] ?? $s->specialty) : 'تخصص عام'; ?></div>
                        </td>
                        <td style="font-size: 12px; color: #4a5568;"><?php echo date('Y-m-d', strtotime($s->created_at)); ?></td>
                        <td>
                            <?php if ($s->status === 'active'): ?>
                                <span class="sm-badge sm-badge-high" style="font-size: 10px; padding: 4px 12px;">نشط</span>
                            <?php else: ?>
                                <span class="sm-badge sm-badge-urgent" style="font-size: 10px; padding: 4px 12px;">ملغى</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="sm-btn sm-btn-outline" onclick="smViewSurveyResults(<?php echo $s->id; ?>, '<?php echo esc_js($s->title); ?>')" style="padding: 4px 12px; font-size: 11px; font-weight: 700; border-radius: 8px;">
                                <?php echo $responses_count; ?> نتيجة
                            </button>
                        </td>
                        <td>
                            <div style="display:flex; gap:8px; justify-content: flex-end;">
                                <button class="sm-btn" style="padding:6px 12px; font-size:11px; background:var(--sm-dark-color); border-radius: 8px;" onclick='smOpenQuestionBank(<?php echo esc_attr(json_encode($s)); ?>)'>الأسئلة</button>
                                <button class="sm-btn sm-btn-outline" onclick="smOpenEditSurveyModal(<?php echo esc_attr(json_encode($s)); ?>)" style="padding: 6px 10px; font-size: 11px; border-radius: 8px;" title="تعديل"><span class="dashicons dashicons-edit"></span></button>
                                <?php if ($s->status === 'active'): ?>
                                    <button class="sm-btn" style="padding: 6px 15px; font-size: 11px; border-radius: 8px; background: #3182ce;" onclick="smOpenAssignModal(<?php echo $s->id; ?>, '<?php echo esc_js($s->title); ?>')">تعيين للعضو</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Live Monitoring -->
    <div id="active-sessions" class="sm-internal-tab" style="display:none;">
        <div style="display:flex; flex-wrap:wrap; gap:25px;">
            <div class="sm-table-container" style="flex: 1; min-width: 500px; overflow-x: auto;">
                <h4 style="margin-bottom:15px; font-weight:800; color:var(--sm-dark-color);">الجلسات النشطة حالياً</h4>
                <table class="sm-table">
                    <thead>
                        <tr>
                            <th>المختبر</th>
                            <th>الاختبار</th>
                            <th>بدأ في</th>
                            <th>آخر نبض</th>
                            <th>الحالة</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="live-sessions-body">
                        <?php
                        $active_sessions = $wpdb->get_results("
                            SELECT a.*, s.title, m.name as member_name
                            FROM {$wpdb->prefix}sm_test_assignments a
                            JOIN {$wpdb->prefix}sm_surveys s ON a.test_id = s.id
                            JOIN {$wpdb->prefix}sm_members m ON a.user_id = m.wp_user_id
                            WHERE a.status = 'active'
                            ORDER BY a.last_heartbeat DESC
                        ");
                        if(empty($active_sessions) || !is_array($active_sessions)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">لا توجد جلسات نشطة حالياً.</td></tr>
                        <?php else: foreach($active_sessions as $as):
                            $diff = time() - strtotime($as->last_heartbeat);
                            $pulse_color = ($diff < 60) ? '#38a169' : '#e53e3e';
                        ?>
                            <tr>
                                <td><strong><?php echo $as->member_name; ?></strong></td>
                                <td><?php echo $as->title; ?></td>
                                <td><?php echo date('H:i:s', strtotime($as->started_at)); ?></td>
                                <td style="color:<?php echo $pulse_color; ?>; font-weight:700;"><?php echo $diff; ?> ثانية</td>
                                <td><span class="sm-badge sm-badge-high">نشط</span></td>
                                <td><button onclick="smTerminateSession(<?php echo $as->id; ?>)" class="sm-btn sm-btn-outline" style="color:#e53e3e; border-color:#e53e3e; padding:4px 10px; font-size:10px;">إنهاء بقوة</button></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:15px; padding:20px; width: 350px; flex-shrink: 0; max-width: 100%;">
                <h4 style="margin:0 0 20px 0; font-weight:800; color:var(--sm-dark-color);">سجل التنبيهات الأمنية (Live)</h4>
                <div id="live-security-logs" style="max-height:500px; overflow-y:auto; display:grid; gap:10px;">
                    <?php
                    $logs = $wpdb->get_results("
                        SELECT l.*, m.name as member_name
                        FROM {$wpdb->prefix}sm_test_logs l
                        JOIN {$wpdb->prefix}sm_members m ON l.user_id = m.wp_user_id
                        ORDER BY l.created_at DESC LIMIT 20
                    ");
                    if (!is_array($logs)) $logs = [];
                    foreach($logs as $log):
                        $color = ($log->action_type === 'start' || $log->action_type === 'submit') ? '#38a169' : '#e53e3e';
                    ?>
                        <div style="background:#fff; border:1px solid #eee; border-right:4px solid <?php echo $color; ?>; padding:12px; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                <span style="font-weight:800; font-size:11px;"><?php echo $log->member_name; ?></span>
                                <span style="font-size:10px; color:#94a3b8;"><?php echo date('H:i:s', strtotime($log->created_at)); ?></span>
                            </div>
                            <div style="font-size:12px; font-weight:600;"><?php echo $log->details; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

<div id="new-survey-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 750px;">
        <div class="sm-modal-header">
            <h3 id="survey-modal-title">إعداد اختبار ممارسة مهنية جديد</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 30px;">
            <input type="hidden" id="survey_id">
            <div class="sm-form-group">
                <label class="sm-label">عنوان الاختبار / المسابقة:</label>
                <input type="text" id="survey_title" class="sm-input" placeholder="مثال: اختبار الحصول على درجة أخصائي" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; background: #f8fafc; padding: 30px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #e2e8f0;">
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:12px;">مدة الاختبار (دقيقة):</label>
                    <input type="number" id="survey_time_limit" class="sm-input" value="30">
                </div>
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:12px;">أقصى عدد محاولات:</label>
                    <input type="number" id="survey_max_attempts" class="sm-input" value="1">
                </div>
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:12px;">درجة النجاح (%):</label>
                    <input type="number" id="survey_pass_score" class="sm-input" value="50">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group">
                    <label class="sm-label">التخصص المرتبط:</label>
                    <select id="survey_specialty" class="sm-select">
                        <option value="">-- كافة التخصصات (عام) --</option>
                        <?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">نوع الاختبار:</label>
                    <select id="survey_test_type" class="sm-select">
                        <option value="practice">اختبار مزاولة مهنة</option>
                        <option value="promotion">اختبار ترقية درجة</option>
                        <option value="training">دورة تدريبية</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group">
                    <label class="sm-label">الفئة المستهدفة بالظهور التلقائي:</label>
                    <select id="survey_recipients" class="sm-select">
                        <option value="all">الجميع</option>
                        <option value="sm_member">أعضاء النقابة</option>
                        <option value="sm_general_officer">مسؤولو النقابة العامة</option>
                        <option value="sm_branch_officer">مسؤولو الفروع</option>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">متاح لفرع محدد:</label>
                    <select id="survey_branch" class="sm-select">
                        <option value="all">متاح لكافة الفروع</option>
                        <?php foreach($db_branches as $b) echo "<option value='".esc_attr($b->slug)."'>".esc_html($b->name)."</option>"; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top: 30px; display:flex; gap:10px;">
                <button class="sm-btn" id="survey_submit_btn" onclick="smSaveSurvey()" style="flex:2; height:50px; font-weight:800;">حفظ ونشر الاختبار</button>
                <button class="sm-btn sm-btn-outline" onclick="this.closest('.sm-modal-overlay').style.display='none'" style="flex:1;">إلغاء</button>
            </div>
        </div>
    </div>
</div>

<!-- QUESTION BANK MODAL -->
<div id="question-bank-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 900px; width: 95%;">
        <div class="sm-modal-header">
            <h3>بنك أسئلة الاختبار: <span id="bank-test-title"></span></h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 0;">
            <div style="display: flex; flex-wrap: wrap; min-height: 400px; max-height: 80vh;">
                <!-- Add Question Form -->
                <div style="background: #f8fafc; border-left: 1px solid #e2e8f0; padding: 25px; overflow-y: auto; width: 350px; flex-shrink: 0; max-width: 100%;">
                    <h4 style="margin-top:0;">إضافة سؤال جديد</h4>
                    <form id="add-question-form">
                        <input type="hidden" id="q_test_id">
                        <div class="sm-form-group">
                            <label class="sm-label">نص السؤال:</label>
                            <textarea id="q_text" class="sm-textarea" rows="3" required></textarea>
                        </div>
                        <div class="sm-form-group">
                            <label class="sm-label">نوع السؤال:</label>
                            <select id="q_type" class="sm-select" onchange="smToggleQuestionOptions(this.value)">
                                <option value="mcq">اختيار من متعدد (MCQ)</option>
                                <option value="true_false">صح أو خطأ</option>
                                <option value="short_answer">إجابة قصيرة</option>
                            </select>
                        </div>

                        <div id="mcq-options-container">
                            <label class="sm-label">الخيارات المتاحة:</label>
                            <div style="display:grid; gap:8px; margin-bottom:15px;">
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="0" checked><input type="text" class="sm-input q-opt" placeholder="الخيار الأول"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="1"><input type="text" class="sm-input q-opt" placeholder="الخيار الثاني"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="2"><input type="text" class="sm-input q-opt" placeholder="الخيار الثالث"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="3"><input type="text" class="sm-input q-opt" placeholder="الخيار الرابع"></div>
                            </div>
                        </div>

                        <div id="tf-options-container" style="display:none;">
                            <label class="sm-label">الإجابة الصحيحة:</label>
                            <select id="q_correct_tf" class="sm-select">
                                <option value="true">صح</option>
                                <option value="false">خطأ</option>
                            </select>
                        </div>

                        <div id="short-options-container" style="display:none;">
                            <label class="sm-label">الإجابة النموذجية:</label>
                            <input type="text" id="q_correct_short" class="sm-input">
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:15px;">
                            <div class="sm-form-group"><label class="sm-label">النقاط:</label><input type="number" id="q_points" class="sm-input" value="1"></div>
                            <div class="sm-form-group"><label class="sm-label">الصعوبة:</label><select id="q_difficulty" class="sm-select"><option value="easy">سهل</option><option value="medium" selected>متوسط</option><option value="hard">صعب</option></select></div>
                        </div>
                        <div class="sm-form-group"><label class="sm-label">الموضوع / التصنيف:</label><input type="text" id="q_topic" class="sm-input" placeholder="مثال: قوانين النقابة"></div>

                        <button type="submit" class="sm-btn" style="width:100%; margin-top:10px;">إضافة السؤال للبنك</button>
                    </form>
                </div>
                <!-- Questions List -->
                <div style="padding: 25px; overflow-y: auto; flex: 1; min-width: 300px;">
                    <div id="bank-questions-list">
                        <!-- Questions load here via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ASSIGN TEST MODAL -->
<div id="assign-test-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 500px;">
        <div class="sm-modal-header">
            <h3 id="assign-modal-title">تعيين الاختبار لمستخدمين</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body">
            <input type="hidden" id="assign_survey_id">
            <div class="sm-form-group">
                <label class="sm-label">اختر المستخدمين (يمكنك اختيار أكثر من واحد):</label>
                <select id="assign_user_ids" class="sm-select" multiple style="height: 200px;">
                    <?php
                    $all_users = get_users(['role__in' => ['sm_member', 'sm_branch_officer', 'sm_general_officer']]);
                    foreach($all_users as $u) {
                        echo "<option value='{$u->ID}'>{$u->display_name} ({$u->user_login})</option>";
                    }
                    ?>
                </select>
            </div>
            <button class="sm-btn" onclick="smSubmitAssignment()" style="width: 100%; margin-top: 20px;">تأكيد التعيين</button>
        </div>
    </div>
</div>

<!-- RESULTS MODAL -->
<div id="survey-results-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 800px;">
        <div class="sm-modal-header">
            <h3 id="res-modal-title">نتائج الاستطلاع</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div id="survey-results-body" style="max-height: 500px; overflow-y: auto; padding: 30px;">
            <!-- Results will be loaded here -->
        </div>
    </div>
</div>

</div> <!-- End .sm-surveys-container -->

<script>
function smOpenNewSurveyModal() {
    document.getElementById('survey_id').value = '';
    document.getElementById('survey-modal-title').innerText = 'إعداد اختبار ممارسة مهنية جديد';
    document.getElementById('survey_submit_btn').innerText = 'حفظ ونشر الاختبار';
    document.getElementById('survey_title').value = '';
    document.getElementById('survey_time_limit').value = '30';
    document.getElementById('survey_max_attempts').value = '1';
    document.getElementById('survey_pass_score').value = '50';
    document.getElementById('new-survey-modal').style.display = 'flex';
}

function smOpenEditSurveyModal(s) {
    document.getElementById('survey_id').value = s.id;
    document.getElementById('survey-modal-title').innerText = 'تعديل إعدادات الاختبار: ' + s.title;
    document.getElementById('survey_submit_btn').innerText = 'تحديث إعدادات الاختبار';
    document.getElementById('survey_title').value = s.title;
    document.getElementById('survey_time_limit').value = s.time_limit;
    document.getElementById('survey_max_attempts').value = s.max_attempts;
    document.getElementById('survey_pass_score').value = s.pass_score;
    document.getElementById('survey_specialty').value = s.specialty;
    document.getElementById('survey_test_type').value = s.test_type;
    document.getElementById('survey_recipients').value = s.recipients;
    document.getElementById('survey_branch').value = s.branch || 'all';
    document.getElementById('new-survey-modal').style.display = 'flex';
}

function smSaveSurvey() {
    const id = document.getElementById('survey_id').value;
    const title = document.getElementById('survey_title').value;
    if (!title) {
        smShowNotification('يرجى إدخال عنوان الاختبار', true);
        return;
    }

    const fd = new FormData();
    fd.append('action', id ? 'sm_update_survey' : 'sm_add_survey');
    if (id) fd.append('id', id);
    fd.append('title', title);
    fd.append('time_limit', document.getElementById('survey_time_limit').value);
    fd.append('max_attempts', document.getElementById('survey_max_attempts').value);
    fd.append('pass_score', document.getElementById('survey_pass_score').value);
    fd.append('specialty', document.getElementById('survey_specialty').value);
    fd.append('test_type', document.getElementById('survey_test_type').value);
    fd.append('recipients', document.getElementById('survey_recipients').value);
    fd.append('branch', document.getElementById('survey_branch').value);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    const action = document.getElementById('survey_id').value ? 'sm_update_survey' : 'sm_add_survey';
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.success) {
            smShowNotification('تم حفظ بيانات الاختبار');
            setTimeout(() => location.reload(), 1000);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smToggleQuestionOptions(type) {
    document.getElementById('mcq-options-container').style.display = (type === 'mcq') ? 'block' : 'none';
    document.getElementById('tf-options-container').style.display = (type === 'true_false') ? 'block' : 'none';
    document.getElementById('short-options-container').style.display = (type === 'short_answer') ? 'block' : 'none';
}

window.smOpenQuestionBank = function(s) {
    document.getElementById('q_test_id').value = s.id;
    document.getElementById('bank-test-title').innerText = s.title;
    smLoadBankQuestions(s.id);
    document.getElementById('question-bank-modal').style.display = 'flex';
};

function smLoadBankQuestions(testId) {
    const list = document.getElementById('bank-questions-list');
    if (!list) return;
    list.innerHTML = '<p>جاري تحميل الأسئلة...</p>';

    fetch(ajaxurl + '?action=sm_get_test_questions&test_id=' + testId + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
    .then(r=>r.json()).then(res => {
        if (!res.success) {
            smHandleAjaxError(res);
            list.innerHTML = '';
            return;
        }
        if (!res.data || res.data.length === 0) {
            list.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8;"><span class="dashicons dashicons-warning" style="font-size:40px; width:40px; height:40px;"></span><p>لا توجد أسئلة مضافة لهذا الاختبار بعد.</p></div>';
            return;
        }
        let html = '<div style="display:grid; gap:15px;">';
        res.data.forEach((q, idx) => {
            html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 30px; position:relative; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div style="position:absolute; left:20px; top:20px; display:flex; gap:10px;">
                        <span class="sm-badge sm-badge-low" style="font-size:10px;">${q.difficulty}</span>
                        <button onclick="smDeleteQuestion(${q.id}, ${testId})" style="border:none; background:none; color:#e53e3e; cursor:pointer;"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                    <div style="font-weight:800; color:var(--sm-dark-color); margin-bottom:10px;">س${idx+1}: ${q.question_text}</div>
                    <div style="font-size:12px; color:#64748b;">النوع: ${q.question_type} | النقاط: ${q.points}</div>
                    <div style="margin-top:10px; padding:10px; background:#f0fff4; border-radius:8px; border:1px solid #c6f6d5; font-size:12px; color:#22543d;">
                        <strong>الإجابة الصحيحة:</strong> ${q.correct_answer}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        list.innerHTML = html;
    });
}

document.getElementById('add-question-form').onsubmit = function(e) {
    e.preventDefault();
    const testId = document.getElementById('q_test_id').value;
    const type = document.getElementById('q_type').value;
    const fd = new FormData();
    fd.append('action', 'sm_add_test_question');
    fd.append('test_id', testId);
    fd.append('question_text', document.getElementById('q_text').value);
    fd.append('question_type', type);
    fd.append('points', document.getElementById('q_points').value);
    fd.append('difficulty', document.getElementById('q_difficulty').value);
    fd.append('topic', document.getElementById('q_topic').value);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    if (type === 'mcq') {
        const opts = Array.from(document.querySelectorAll('.q-opt')).map(i => i.value);
        const correctIdx = document.querySelector('input[name="correct_mcq"]:checked').value;
        fd.append('options', JSON.stringify(opts));
        fd.append('correct_answer', opts[correctIdx]);
    } else if (type === 'true_false') {
        fd.append('correct_answer', document.getElementById('q_correct_tf').value);
    } else {
        fd.append('correct_answer', document.getElementById('q_correct_short').value);
    }

    const action = 'sm_add_test_question';
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.success) {
            smShowNotification('تم إضافة السؤال');
            this.reset();
            smLoadBankQuestions(testId);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
};

function smDeleteQuestion(id, testId) {
    if (!confirm('حذف هذا السؤال نهائياً؟')) return;
    const action = 'sm_delete_test_question';
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=' + action, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم حذف السؤال');
            smLoadBankQuestions(testId);
        } else {
            smHandleAjaxError(res.data);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smOpenAssignModal(id, title) {
    document.getElementById('assign_survey_id').value = id;
    document.getElementById('assign-modal-title').innerText = 'تعيين الاختبار: ' + title;
    document.getElementById('assign-test-modal').style.display = 'flex';
}

function smSubmitAssignment() {
    const survey_id = document.getElementById('assign_survey_id').value;
    const select = document.getElementById('assign_user_ids');
    const user_ids = Array.from(select.selectedOptions).map(option => option.value);

    if (user_ids.length === 0) {
        smShowNotification('يرجى اختيار مستخدم واحد على الأقل', true);
        return;
    }

    const fd = new FormData();
    fd.append('action', 'sm_assign_test');
    fd.append('survey_id', survey_id);
    user_ids.forEach(id => fd.append('user_ids[]', id));
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    const action = 'sm_assign_test';
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم تعيين الاختبار بنجاح');
            document.getElementById('assign-test-modal').style.display = 'none';
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smCancelSurvey(id) {
    if (!confirm('هل أنت متأكد من إلغاء هذا الاختبار؟ لن يتمكن أحد من التقديم عليه بعد الآن.')) return;

    const action = 'sm_cancel_survey';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم إلغاء الاستطلاع');
            setTimeout(() => location.reload(), 1000);
        } else {
            smHandleAjaxError(res.data);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smTerminateSession(aid) {
    if(!confirm('هل أنت متأكد من إنهاء جلسة هذا المختبر؟ سيتم منعه من الاستمرار وإغلاق الاختبار عليه.')) return;
    const action = 'sm_terminate_test_admin';
    const fd = new FormData();
    fd.append('action', action);
    fd.append('assignment_id', aid);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=' + action, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم إنهاء الجلسة بنجاح');
            setTimeout(() => location.reload(), 1000);
        }
    });
}

function smApplyTestFilters() {
    const search = document.getElementById('test_search_input').value.toLowerCase();
    const type = document.getElementById('test_type_filter').value;
    const branch = document.getElementById('test_branch_filter').value;

    document.querySelectorAll('.sm-test-row').forEach(row => {
        const matchesSearch = row.dataset.title.toLowerCase().includes(search);
        const matchesType = (type === 'all' || row.dataset.type === type);
        const matchesBranch = (branch === 'all' || row.dataset.branch === branch || (branch === 'all_branches' && row.dataset.branch === 'all'));

        if (matchesSearch && matchesType && matchesBranch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function smResetTestFilters() {
    document.getElementById('test_search_input').value = '';
    document.getElementById('test_type_filter').value = 'all';
    document.getElementById('test_branch_filter').value = 'all';
    smApplyTestFilters();
}

function smViewSurveyResults(id, title) {
    document.getElementById('res-modal-title').innerText = 'نتائج: ' + title;
    const body = document.getElementById('survey-results-body');
    body.innerHTML = '<p style="text-align:center;">جاري تحميل النتائج...</p>';
    document.getElementById('survey-results-modal').style.display = 'flex';

    const action = 'sm_get_survey_results';
    fetch(ajaxurl + '?action=' + action + '&id=' + id)
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data) {
            const d = res.data;
            let html = `
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-bottom: 30px;">
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">إجمالي المشاركات</div>
                        <div style="font-size:24px; font-weight:900;">${d.stats.total_responses}</div>
                    </div>
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">متوسط الدرجات</div>
                        <div style="font-size:24px; font-weight:900; color:var(--sm-primary-color);">${Math.round(d.stats.avg_score)}%</div>
                    </div>
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">عدد الناجحين</div>
                        <div style="font-size:24px; font-weight:900; color:#38a169;">${d.stats.pass_count}</div>
                    </div>
                </div>
            `;

            d.questions.forEach(item => {
                html += `<div style="margin-bottom: 30px; padding: 30px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="font-weight: 800; margin-bottom: 30px; color: var(--sm-dark-color);">${item.question}</div>
                    <div style="display: grid; gap: 10px;">`;

                for (const [ans, count] of Object.entries(item.answers)) {
                    html += `<div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 8px 15px; border-radius: 5px; border: 1px solid #edf2f7;">
                        <span>${ans}</span>
                        <span style="font-weight: 700; color: var(--sm-primary-color);">${count}</span>
                    </div>`;
                }
                html += `</div></div>`;
            });
            body.innerHTML = html;
        } else {
            smHandleAjaxError(res);
            body.innerHTML = '<p style="color:red;">فشل تحميل النتائج</p>';
        }
    }).catch(err => {
        smHandleAjaxError(err);
        body.innerHTML = '<p style="color:red;">حدث خطأ أثناء تحميل البيانات</p>';
    });
}
</script>
