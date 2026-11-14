<?php
require_once __DIR__ . '/api/config.php';
requireAdmin($isAdmin);

/* Load users list (including the current admin) straight from MySQL.
   Self-protection (can't delete/demote yourself) is enforced server-side
   in user_ajax.php, and the UI marks your own row so it's clear which one it is. */
$usersList = [];
$conn = getMysqli();
if (!$conn->connect_error) {
    $res = $conn->query(
        "SELECT id, username, is_admin FROM users
         ORDER BY is_admin DESC, id DESC"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $usersList[] = [
                'id'       => (int)$r['id'],
                'username' => $r['username'],
                'is_admin' => (bool)$r['is_admin'],
            ];
        }
    }
}

$activePage = 'users';
$pageTitle  = 'المستخدمون - الضيافة';
include __DIR__ . '/includes/header.php';
?>

<div class="user-mgmt-page">
  <div class="card">
    <div class="card-title">
      <span class="dot" style="background:var(--accent)"></span>
      <span id="users-page-title">إدارة المستخدمين</span>
      <span class="badge-count" id="users-count-badge">0</span>
    </div>

    <div id="user-alert-box" style="display:none;margin-bottom:14px;"></div>

    <div class="section-micro-label" id="add-user-label"><i class="fa-solid fa-user-plus" style="color: rgb(255, 212, 59);"></i> إضافة مستخدم جديد</div>
     <form>
      <div class="add-user-form-row">
      <input type="text" id="new-username-input" placeholder="اسم المستخدم" autocomplete="username" />
      <div class="pw-wrap">
        <input type="password" id="new-password-input" placeholder="كلمة المرور (6+ أرقام)" autocomplete="new-password" />
        <button type="button" class="eye-btn" onclick="toggleEye('new-password-input','eye-new')" tabindex="-1">
          <span id="eye-new"><i class="fa-regular fa-eye"></i></span>
        </button>
           </form>
      </div>
      <div class="user-role-select-wrap">
        <div class="custom-select-wrap" id="new-role-wrap">
          <select id="new-role-select" class="custom-select-native" onchange="onRoleSelectChange(this)">
            <option value="0">مستخدم</option>
            <option value="1">مشرف</option>
          </select>
          <button type="button" class="custom-select-trigger" onclick="toggleCustomSelect('new-role-wrap',event)">
            <span class="custom-select-label"><i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i> <span id="new-role-label-text">مستخدم</span></span>
            <i class="fa-solid fa-chevron-down custom-select-caret"></i>
          </button>
          <div class="custom-select-options">
            <div class="custom-select-option selected" data-value="0" onclick="selectCustomOption('new-role-wrap','0',event)"><i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i> <span class="opt-text">مستخدم</span></div>
            <div class="custom-select-option" data-value="1" onclick="selectCustomOption('new-role-wrap','1',event)"><i class="fa-brands fa-amilia" style="color: rgb(255, 199, 0);"></i> <span class="opt-text">مشرف</span></div>
          </div>
        </div>
      </div>
      <button type="button" class="btn btn-warning btn-sm" onclick="ajaxAddUser()" style="flex-shrink:0;white-space:nowrap;align-self:center;">
        <i class="fas fa-plus"></i> <span id="add-user-btn-text">إضافة</span>
      </button>
    </div>
    <div style="font-size:11px;color:var(--text2);margin-top:6px;margin-bottom:4px;" id="pass-hint-text">
      كلمة المرور: أرقام فقط (6 أو أكثر) · المشرف يملك صلاحيات كاملة بما فيها إدارة المستخدمين
    </div>

    <div class="divider"></div>

    <div id="users-cards-container"></div>
  </div>
</div>

<!-- USER EDIT MODAL -->
<div class="modal-overlay" id="user-edit-modal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-title" style="color:var(--accent);">✏️ <span id="user-edit-modal-title">تعديل المستخدم</span></div>
    <div id="user-edit-alert" style="display:none;margin-bottom:12px;"></div>
    <div class="form-group">
      <label id="edit-user-name-label">اسم المستخدم</label>
      <input type="text" id="edit-user-name-input" autocomplete="off" />
    </div>
    <div class="form-group">
      <label id="edit-user-pass-label">كلمة المرور الجديدة (6+ أرقام)</label>
      <div class="pw-wrap">
          <form>
        <input type="password" id="edit-user-pass-input" autocomplete="new-password" />
          </form>
        <button type="button" class="eye-btn" onclick="toggleEye('edit-user-pass-input','eye-edit')" tabindex="-1">
          <span id="eye-edit"><i class="fa-regular fa-eye"></i></span>
        </button>
      </div>
    </div>
    <div class="form-group">
      <label id="edit-user-role-label">الصلاحية / Role</label>
      <div class="custom-select-wrap" id="edit-role-wrap">
        <select id="edit-user-role-select" class="custom-select-native" onchange="onRoleSelectChange(this)">
          <option value="0">مستخدم (User) — صلاحيات محدودة</option>
          <option value="1">مشرف (Admin) — صلاحيات كاملة</option>
        </select>
        <button type="button" class="custom-select-trigger" style="width:100%;" onclick="toggleCustomSelect('edit-role-wrap',event)">
          <span class="custom-select-label"><i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i> <span id="edit-role-label-text">مستخدم — صلاحيات محدودة</span></span>
          <i class="fa-solid fa-chevron-down custom-select-caret"></i>
        </button>
        <div class="custom-select-options">
          <div class="custom-select-option selected" data-value="0" onclick="selectCustomOption('edit-role-wrap','0',event)"><i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i> <span class="opt-text">مستخدم (User) — صلاحيات محدودة</span></div>
          <div class="custom-select-option" data-value="1" onclick="selectCustomOption('edit-role-wrap','1',event)"><i class="fa-brands fa-amilia" style="color: rgb(255, 199, 0);"></i> <span class="opt-text">مشرف (Admin) — صلاحيات كاملة</span></div>
        </div>
      </div>
      <div class="role-admin-warning" id="role-admin-warning">
        <i class="fas fa-exclamation-triangle"></i>
        سيحصل هذا المستخدم على صلاحيات كاملة بما فيها إدارة المستخدمين!
      </div>
    </div>
    <div class="row" style="margin-top:18px;">
      <button type="button" class="btn btn-primary" onclick="ajaxEditUser()">💾 <span id="save-user-edit-text">حفظ التغييرات</span></button>
      <button type="button" class="btn btn-secondary" onclick="closeUserEditModal()"><span id="cancel-user-edit-text">إلغاء</span></button>
    </div>
  </div>
</div>

<!-- USER DELETE POPUP -->
<div id="user-delete-popup" class="delete-popup-overlay" style="display:none;" onclick="if(event.target===this)closeUserDeletePopup()">
  <div class="delete-popup-card">
    <span class="delete-popup-icon">🗑️</span>
    <div class="delete-popup-title" id="udel-title">تأكيد الحذف</div>
    <div class="delete-popup-msg" id="udel-msg">هل تريد حذف هذا المستخدم؟</div>
    <span class="delete-popup-name" id="udel-name"></span>
    <div class="delete-popup-btns">
      <button class="btn btn-danger" onclick="confirmDeleteUser()" style="flex:none;min-width:130px;">🗑️ <span id="udel-confirm-text">نعم، احذف</span></button>
      <button class="btn btn-secondary" onclick="closeUserDeletePopup()" style="flex:none;min-width:110px;">❌ <span id="udel-cancel-text">إلغاء</span></button>
    </div>
  </div>
</div>

<!-- This is the only inline script left in the whole app: it bridges the
     server-rendered user list (a live DB query result) into JS. It can't be
     moved to common.js since it's per-request data, not static code - the
     actual pageInit logic that consumes it lives in common.js. -->
<script>window.__usersDataFromServer = <?php echo json_encode($usersList); ?>;</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
