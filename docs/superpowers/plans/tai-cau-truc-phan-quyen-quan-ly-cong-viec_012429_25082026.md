# Tái cấu trúc phân quyền & dữ liệu mẫu — phân hệ Quản lý công việc

> Ngày tạo: 01:24:29 25/08/2026  
> Cập nhật lần cuối: 03:32:13 25/08/2026

**Nhánh:** `quanlycongviec` trên cả ba repo — `core-be`, `core-fe`, `core-miniapp`. Đã push,
**chưa merge vào `main`**, chưa tạo PR.
**Quy mô:** core-be 11 commit / core-fe 7 commit / core-miniapp 1 commit
**Trạng thái:** phần đã làm chạy được và đã kiểm chứng; mục 4.2 đã xong ngày 25/08,
còn 3 hạng mục dở (xem mục 4)

---

## 1. Mục tiêu

Bắt đầu từ một câu hỏi rà soát ("kiểm tra danh sách quyền hạn, chính sách và logic phân quyền
trong module Quản lý công việc"), việc mở rộng dần thành tái cấu trúc:

1. Chuẩn hoá cây permission cho 4 danh mục về đủ 11 quyền, mỗi endpoint một quyền.
2. Làm lại quan hệ Nhân viên ↔ Phòng ban cho đúng (khoá, ràng buộc, phạm vi).
3. Cắt phụ thuộc ngược từ `Core` sang phân hệ.
4. Bỏ hoàn toàn việc kiểm tra **tên vai trò** trong code, chuyển sang kiểm **quyền**.
5. Xoá phân hệ Lịch công tác (code chết).
6. Dựng lại toàn bộ dữ liệu mẫu quanh 2 vai trò nghiệp vụ.
7. Đổi thương hiệu mặc định sang Danatec.

---

## 2. File đã sửa và lý do

Liệt kê theo nhóm; danh sách đầy đủ xem `git log main..quanlycongviec`.

> Đường dẫn không ghi tiền tố repo là thuộc `core-be/`; phần frontend ghi rõ `core-fe`.

### 2.1 Cây permission (4 danh mục về 11 quyền)

| File | Lý do |
|---|---|
| `database/seeders/PermissionSeeder.php` | Nguồn khai báo duy nhất của cây quyền. Thêm action cho `types`/`item-types`/`departments`/`employees`; gỡ khỏi `$REMOVED_PERMISSIONS`; thêm `viewAll`/`manageAll`; rút vai trò từ 10 xuống 3 |
| `app/Console/Commands/MigrateTaskPermissionTreeCommand.php` | Gỡ bảng ánh xạ gộp ngược, nếu không chạy lại lệnh sẽ gộp quyền vừa tách về lại |
| `Routes/task_assignment_{type,item_type,department,employee}.php` | Mỗi endpoint gác một quyền riêng thay vì dùng chung `index`/`update`/`destroy` |
| 3 migration `2026_08_24_*` | Tạo quyền mới + cấp bù cho vai trò đang có trên server |
| `docs/api/task-assignment-{type,item-type,department,employee}.md` | Ghi permission cho từng endpoint |

### 2.2 Quan hệ Nhân viên ↔ Phòng ban

| File | Lý do |
|---|---|
| `migrations/2026_08_24_120000_restructure_...` | Đổi tên `task_assignment_users` → `task_assignment_employee_department`, khoá theo `employee_id`, bỏ `status` + `is_primary`, đổi CASCADE → RESTRICT |
| `Models/TaskAssignmentEmployeeDepartment.php` (mới) | Thay `TaskAssignmentUser.php` (đã xoá). Có scope `forUser()` và `activeEmployee()` |
| `Models/TaskAssignmentEmployee.php`, `TaskAssignmentDepartment.php` | Quan hệ khoá theo `employee_id`; đổi `taskAssignmentUsers()` → `employeeMemberships()` |
| `Services/TaskAssignmentDepartmentService.php` | Gộp gán nhân viên vào `store`/`update`; xoá 4 method quan hệ; thêm guard chặn xoá phòng ban còn dữ liệu |
| `Services/TaskAssignmentEmployeeService.php` | Chiều ngược lại: `department_ids` trong `store`/`update` |
| `Requests/Store|UpdateDepartmentRequest.php` | Nhận `employee_ids` + `representative_employee_id` |
| `Requests/Store|UpdateEmployeeRequest.php` | Nhận `department_ids` |
| `Controllers/TaskAssignmentDepartmentController.php` | Xoá 3 action `users`/`syncUsers`/`removeUser` |
| `Requests/SyncDepartmentUsersRequest.php` | **Xoá** — không còn endpoint quan hệ |

### 2.3 Cắt phụ thuộc Core → phân hệ

| File | Lý do |
|---|---|
| `Core/Models/User.php` | Gỡ 2 quan hệ và 2 filter trỏ vào bảng của phân hệ |
| `Core/Resources/UserResource.php` | Gỡ field `task_assignment_department_id` |
| `Core/Services/UserService.php` | Gỡ 2 chỗ tự xoá membership (FK CASCADE lo); guard xoá user → event |
| `Core/Events/UsersDeleting.php` (mới) | Điểm mở rộng để phân hệ tự đăng ký luật chặn xoá |
| `TaskAssignment/Listeners/BlockUserDeletionWithActiveTasks.php` (mới) | Luật chặn nay thuộc về phân hệ |

### 2.4 Kiểm quyền thay tên vai trò

| File | Lý do |
|---|---|
| `TaskAssignmentItemPolicy`, `TaskAssignmentPetitionPolicy` | `before()` dùng `can('task-overview.manageAll')` |
| `TaskAssignmentItemService` | Bỏ hằng `ADMIN_ROLES`; dùng `viewAll` / `manageAll` |
| `TaskAssignmentNoteService` | `author_role` xác định bằng quyền giao việc |
| `Meeting/Concerns/HasMeetingRole.php`, `MeetingVoteResponseService` | Dùng `meetings.viewAll` |

### 2.5 Đơn thư

| File | Lý do |
|---|---|
| `TaskAssignmentPetitionPolicy.php` | Viết lại: quyền × phạm vi tách bạch, bỏ 6 chỗ gọi `isUserInOverviewDepartment()` |
| `TaskAssignmentPetitionService.php` | Phạm vi theo quyền; `bulkDestroy`/`bulkUpdateStatus` lọc từng dòng |
| `Requests/Store|UpdatePetitionRequest.php` | Chặn lập đơn cho phòng ban không thuộc về mình (trước chỉ FE giới hạn) |
| `migrations/2026_08_25_090000_drop_is_petition_overview...` | Bỏ cờ, thay bằng quyền |

### 2.6 Xoá Lịch công tác · Seed · Thương hiệu

| File | Lý do |
|---|---|
| `app/Modules/Scheduling/` + 22 migration + notification bits | Module chết |
| `routes/api.php`, `bootstrap/app.php`, 2 ServiceProvider, `LogActivity`, `OrganizationService`, 2 enum | Gỡ tham chiếu |
| `database/seeders/TaskAssignmentDemoSeeder.php` (mới) | Dữ liệu mẫu theo đúng trình tự nghiệp vụ |
| `DatabaseSeeder.php` | Quyền → cấu hình → dữ liệu mẫu |
| `SettingSeeder.php`, `config/scribe.php` | Mặc định Danatec |

**core-fe:** `TaskDepartmentList.vue` (xoá drawer quản lý, gộp vào form), `TaskAssignmentEmployeeList.vue`
(thêm ô chọn phòng ban), 2 store (chặn `fetchStats` khi thiếu quyền), `PetitionList.vue`, i18n,
xoá `src/modules/scheduling/`, `tests/task-dept-employee.spec.js` (mới).

---

## 3. Quyết định kiến trúc

### 3.1 Giữ n-n với 3 bảng

**Chốt:** `employees` + bảng nối + `departments`.

| Phương án loại | Lý do loại |
|---|---|
| Gộp còn 1 bảng (1 người = 1 phòng) | Dữ liệu thực tế lúc đó cho thấy 0 người thuộc 2 phòng, nhưng người dùng xác nhận nghiệp vụ **có** kiêm nhiệm |
| Chỉ bảng nối, bỏ bảng hồ sơ | `status`/`note` là thuộc tính **cấp người**. Đặt trên từng dòng phòng ban thì vô hiệu hoá một người thuộc 3 phòng phải sửa 3 dòng — chính căn bệnh cũ |

### 3.2 Bảng nối khoá theo `task_assignment_employee_id`

**Chốt:** khoá ngoại đơn trỏ `task_assignment_employees.id`, theo mẫu `meeting_participants` /
`meeting_attendee_group_members` của phân hệ Phòng họp.

| Phương án loại | Lý do loại |
|---|---|
| Giữ `user_id` + khoá ngoại **ghép** `(user_id, organization_id)` | Đã dựng xong rồi bỏ. Là thủ thuật bù cho việc khoá sai; Meeting chứng minh khoá theo "công dân của phân hệ" mới đúng |

**Chi phí dịch khoá thấp hơn dự đoán ban đầu:** chỉ một `whereHas` một tầng, đóng gói thành scope
`forUser()`. Ước lượng ban đầu của tôi ("tầng chuyển đổi rải khắp code") là **sai** — Meeting đã
chứng minh ngược lại.

### 3.3 `task_assignment_item_user` vẫn khoá theo `user_id`

**Chốt:** giữ nguyên (bước B, chưa làm).
**Lý do:** `auth()->id()` trả user id; đổi khoá đồng nghĩa thêm bước dịch ở mọi policy, export và
notification. Dữ liệu hiện sạch (0/51 dòng trỏ người không phải nhân viên).

### 3.4 Quan hệ là **trường của form**, không phải resource riêng

**Chốt:** `employee_ids` trong form phòng ban, `department_ids` trong form nhân viên.

| Phương án loại | Lý do loại |
|---|---|
| REST đa chiều: `/departments/{id}/employees` + `/employees/{id}/departments` | Chiều thứ hai **không có người dùng** — form nhân viên không có ô chọn phòng ban, cột phòng ban chỉ hiển thị. Dựng 4 endpoint cho màn không có nút bấm nào gọi tới |
| Giữ 3 endpoint cũ và chỉ đổi tên | Đổi tên thứ sắp xoá; FE phải sửa hai lần |

Kết quả: phòng ban còn **12 action thay vì 16**, và cái bẫy pivot-id-vs-user-id biến mất vì không
còn ai trả pivot id ra ngoài.

### 3.5 Không kiểm tên vai trò trong code

**Chốt:** 20 chỗ → 1 (`HorizonServiceProvider`, hạ tầng, chấp nhận).

Bằng chứng thiết kế cũ đã hỏng: `SchedulePolicy` từng kiểm **cả** `'Lái xe'` lẫn
`'scheduling-lai-xe'` — dấu vết một lần đổi tên mà cách chữa là thêm tên mới cạnh tên cũ.

Ba quyền mới thay cho ngữ nghĩa từng bị nhét vào tên vai trò:

| Quyền | Nghĩa |
|---|---|
| `task-overview.viewAll` | Xem toàn tổ chức, bỏ giới hạn phòng ban |
| `task-overview.manageAll` | Thao tác trên công việc người khác (bỏ kiểm tra sở hữu) |
| `meetings.viewAll` | Xem chi tiết điểm danh/biểu quyết theo từng người |

### 3.6 Đơn thư: bỏ `is_petition_overview`, dùng `petitions.viewAll`

**Chốt:** quyền quyết định *làm gì*, phạm vi quyết định *làm trên đơn nào*.

| Phương án loại | Lý do loại |
|---|---|
| Giữ cờ, chỉ ghi tài liệu về phép AND ẩn | Màn phân quyền vẫn nói dối: cấp `destroy` mà người dùng vẫn 403 |
| Bỏ luôn quyền `destroy`/`bulkDestroy`/`manage`, chỉ để cờ quyết định | Mất khả năng phân biệt "được xem nhưng không được xoá" |

**Hệ quả nghiệp vụ:** nhân viên thuộc phòng từng gắn cờ tổng hợp **không còn thấy toàn bộ đơn thư**.
Muốn vậy phải cấp `petitions.viewAll` cho vai trò của họ.

### 3.7 Vai trò: 10 → 3

`Super Admin`, `Quản lý công việc` (đổi tên từ `Trưởng phòng`), `Nhân viên`.

| Quyết định | Lý do |
|---|---|
| Đổi tên `Trưởng phòng` thay vì tạo vai trò mới | Nó vốn đã là vai trò quản lý của phân hệ; tạo thêm sẽ có hai vai trò trùng vai |
| Xoá `Admin` | 0 user; 10 chỗ code dùng nó đều đi cặp với `Super Admin`, sau khi chuyển sang kiểm quyền thì không còn code nào gọi tên |
| Xoá `Quản trị`, `Đại biểu`, `Tổng hợp lịch`, `Thư ký`, `Văn phòng`, `Lái xe` | 0 user; rác hoặc trùng vai; `Lái xe` đi theo module Lịch công tác |

### 3.8 Giữ 4 seeder cấu hình hệ thống

Yêu cầu là "chỉ giữ seed về quyền hạn", nhưng `SettingSeeder`,
`NotificationEventConfigSeeder`, `NotificationScheduleSeeder` và cấu hình tổ chức là **cấu hình nền**
chứ không phải dữ liệu mẫu — xoá thì app thiếu logo, định dạng thời gian, cấu hình sự kiện thông báo.
Đã nêu rõ khi bàn giao, người dùng không phản đối.

---

## 4. Việc còn dở

Theo thứ tự ưu tiên tôi đề nghị:

### 4.1 Phạm vi dữ liệu công việc chưa được chặn ở server *(mức độ: cao)*

`GET /task-assignment-items` chỉ gác `can:viewAny`, mà `viewAny` pass nếu có **bất kỳ** 1 trong 5
quyền index. Việc tách "đang giao / được giao" nằm hoàn toàn ở FE qua query param `assignee_id`.
**Kiểm chứng:** cả `admin`, `quanly1` lẫn `nhanvien1` đều nhận về 6/6 công việc. Bỏ param là nhân
viên thấy toàn bộ công việc của tổ chức. `GET /stats` cũng vậy.

### 4.2 ~~Điều chuyển / ghi chú / báo cáo thiếu kiểm tra liên quan~~ — **ĐÃ XONG 25/08/2026**

Đã gác bằng policy: quyền mở cửa, phạm vi quyết định bản ghi nào. Xem commit
`refactor(task): gác báo cáo, ghi chú, điều chuyển bằng policy` và
`docs/changelogs/2026-08-25-bao-cao-cong-viec-phan-quyen-fe.md`.

- `TaskAssignmentItemReportPolicy` (mới): `update`/`delete` = người nộp hoặc người giao việc;
  `view` = ai liên quan tới công việc.
- `TaskAssignmentItemPolicy`: thêm `transfer` / `note` / `report` / `viewReports`.
- `index` và `store` của báo cáo nhận id công việc từ query/body nên `can:` không bind được model —
  hai action đó gọi `Gate::authorize()` trong controller.
- Phạm vi **đọc** rộng hơn phạm vi **ghi** một bậc: thêm `task-overview.index` /
  `presentation.index`, vì cây quyền của BE vốn đã dùng đúng hai quyền này để gác 7 route thống kê.

**Còn lại trong nhóm này:** `update` báo cáo vẫn ghi đè `completion_percent` (chưa xử lý), và 3
route đọc báo cáo mới chỉ giới hạn theo công việc chứ chưa theo phòng ban.

### 4.3 `applyDepartmentRestriction()` chưa phủ hết *(trung bình)*

Có gọi ở `statsByUser`, `statsByTime`, `statsByDocument`, `overdue`, `upcomingDeadline`.
**Không** gọi ở `stats()`, `statsByDepartment()`, `statsByItemType()`, `index()`, `export()`,
`exportMonthlyReport()`.

### 4.4 Bước B — đổi `task_assignment_item_user.user_id` → `employee_id` *(thấp)*

Xem 3.3. Nên là một đợt riêng, có test kèm.

### Việc nhỏ còn lại

- `Core\ChatController` / `ChatService` / `ChatMessageSent` / `ChatConversationTypeEnum` vẫn biết về
  Meeting — rò rỉ ngược cùng loại vừa gỡ cho TaskAssignment, chưa xử lý.
- `notification-template/router/routes.js` import view không tồn tại — **lỗi có sẵn**, đã đối chiếu
  `git cat-file`, không phải do đợt này.
- Docblock của `TaskAssignmentItemService::reject()` ghi "pending_approval → in_progress", nhưng
  code đặt `Todo` và reset tiến độ về 0. Sai tài liệu, không sai code.
- Chưa tạo PR / merge vào `main` ở cả ba repo.

---

## 5. Cạm bẫy đã gặp — đừng lặp lại

### Về seeder / migration

1. **`$REMOVED_PERMISSIONS` sẽ xoá lại thứ bạn vừa thêm.** Thêm action vào `$PERMISSIONS` mà quên gỡ
   khỏi mảng này thì mỗi lần seed sẽ *tạo rồi xoá ngay*, quyền biến mất khỏi DB mà không báo gì.
   Cùng bẫy với `$REMOVED_ROLES` và bảng `MAP` trong `MigrateTaskPermissionTreeCommand`.

2. **Tên khoá ngoại tự sinh của Laravel vượt 64 ký tự.**
   `task_assignment_employee_department_task_assignment_department_id_foreign` = 73 ký tự → MySQL
   lỗi 1059. Bảng tên dài phải đặt tên FK thủ công: `$table->foreign('col', 'ten_ngan')`.

3. **DDL của MySQL không rollback.** Migration lỗi giữa chừng để lại bảng dựng dở; lần chạy sau báo
   "table already exists" che mất lỗi thật. Phải `dropIfExists` thủ công rồi chạy lại mới thấy lỗi gốc.

4. **Xoá module thì migration của module khác có thể phụ thuộc.** `add_moment_to_reminders` chạm
   `schedule_reminders` và `reminder_presets` — sau khi xoá migration tạo bảng, nó fail. Với migration
   chỉ phục vụ module đã xoá thì xoá luôn; với migration dùng chung thì bọc `Schema::hasTable()`.

5. Dự án có sẵn `php artisan migrate:sync-deleted` để drop bảng theo migration đã xoá — dùng nó thay
   vì viết migration drop thủ công.

### Về công cụ

6. **`git rm` với nhiều glob: một glob không khớp là cả lệnh fail.**
   `git rm database/migrations/*schedul* database/migrations/*Schedul*` — glob thứ hai không khớp file
   nào → lệnh fail, **không xoá gì**. Có `2>/dev/null` nên im lặng hoàn toàn. Đã tưởng xoá xong 26
   migration mà thực tế còn nguyên. Luôn kiểm lại bằng `ls | grep -c` sau khi xoá.

7. **Xoá hàm PHP bằng regex "lùi về `/**` gần nhất" sẽ nuốt hàm liền trước** nếu hàm cần xoá không có
   docblock riêng. Mắc **hai lần**: mất `getAllPermissionNames()`, rồi mất `getNhanVienPermissionNames()`.
   Cả hai chỉ lộ ra khi seed chạy và ném "Call to undefined method". Sau mỗi lần xoá hàm, chạy
   `grep -n "function"` đối chiếu danh sách.

8. **Pint: phân biệt lỗi của mình với lỗi có sẵn trước khi sửa.** File này chưa Pint-clean; chạy
   `pint` cả file sẽ format lại hàng chục dòng không liên quan. Luôn `pint --test <file> -v` xem diff,
   chỉ sửa hunk thuộc vùng mình đụng.

### Về phân quyền

9. **`hasPermissionTo()` ném `PermissionDoesNotExist` nếu quyền chưa có trong DB.** Trong policy/service
   nên dùng `$user->can('...')` — trả `false` an toàn khi DB chưa seed quyền mới.

10. **Quyền không tồn tại trong `$PERMISSIONS` thì cấp cũng vô nghĩa.** Đã có lúc `Quản lý công việc`
    mất sạch quyền đơn thư vì viết lại hàm sinh danh sách mà quên nhánh petitions — không lỗi gì, chỉ
    là 403 lúc chạy.

### Về frontend

11. **`can()` của `@layouts/plugins/casl` trả `false` khi gọi ngoài context component.** Nó dựa vào
    `getCurrentInstance()`. Trong Pinia store phải dùng `authStore.permissions.includes('...')`, nếu
    không thì người **có** quyền vẫn bị ẩn chức năng.

12. **MSW bật mặc định** (`VITE_USE_MSW` không set = bật) → mock handler ném lỗi, trang trắng, console
    đầy "[MSW] Uncaught exception". `.env` phải có `VITE_USE_MSW=false`.

13. **Route FE có tiền tố `/dashboard`** do `_loader.js` tự thêm. Route khai `/task/departments` thì
    URL thật là `/dashboard/task/departments`. Vào thẳng đường dẫn khai báo sẽ ra trang 404.

14. **Vite dev chạy trong container thì `localhost` trong proxy trỏ vào chính container.** Phải dùng
    hostname của compose network (`laravel.test`) — nhưng nếu FE gọi API bằng URL tuyệt đối thì
    **trình duyệt người dùng** mới là bên phân giải, nên `laravel.test` sẽ hỏng. Giải pháp đã dùng:
    `VITE_API_BASE_URL=http://localhost:8001` cho người dùng, kèm một cầu nối TCP trong container để
    trình duyệt headless gọi được đúng URL đó.

15. **Vuetify không gắn `for` cho label của `AppSelect`** → `getByLabel()` của Playwright không tìm
    thấy dù label hiển thị. Bám vào kết quả nghiệp vụ (chip, số dòng trong bảng) thay vì selector nhãn.

16. **Vite dev lần tải đầu rất lâu** (template Vuexy, hàng trăm module rời) — timeout mặc định 30s của
    Playwright không đủ, phải đặt `setDefaultNavigationTimeout(180000)` và `waitUntil:'domcontentloaded'`.

17. **Đăng nhập nhiều lần liên tiếp bị `ThrottleRequestsException`.** Khi test bằng curl phải
    `artisan cache:clear` hoặc dùng lại token.

### Về nghiệp vụ

18. **`current_organization_id` lấy từ `user_preferences`, không suy ra tự động.** Seeder tạo user mà
    không đặt preference thì đăng nhập xong `roles` và `permissions` đều rỗng — trông như phân quyền
    hỏng, thực ra chỉ là chưa chọn tổ chức.

19. **422 khác 403.** Khi kiểm ranh giới quyền: 403 = bị chặn ở cửa quyền; 422 = **qua được** cửa quyền
    và bị chặn ở luật nghiệp vụ. Có lúc tưởng `changeStatus` bị chặn vì phạm vi, hoá ra đơn thư đó ở
    trạng thái `completed` — đúng thiết kế.

---

## 6. Tài khoản mẫu

| Tài khoản | Tên hiển thị | Mật khẩu | Vai trò | Quyền |
|---|---|---|---|---|
| `admin` | Quản trị hệ thống | `quandcore**11` | Super Admin | 267 |
| `quanly1` | Quản lý 1 | `123123` | Quản lý công việc | 83 |
| `nhanvien1`…`nhanvien10` | Nhân viên 1…10 | `123123` | Nhân viên | 10 |

Tên hiển thị đặt theo số thứ tự để khi kiểm thử nhìn là biết ngay ai là ai.

Dữ liệu: 3 phòng ban (mỗi phòng có người đại diện), **11 bản ghi nhân viên** (Quản lý 1 + 10 nhân
viên), 2 văn bản đã ban hành, 6 công việc phủ đủ trạng thái, 2 báo cáo, 3 đơn thư.

Phân bổ nhân viên theo phòng ban — mỗi phòng ít nhất 2 người:

| Phòng ban | Người đại diện | Nhân viên |
|---|---|---|
| Hành chính - Tổng hợp | Quản lý 1 | Nhân viên 1, 2, 7, 8 |
| Kế hoạch - Tài chính | Nhân viên 3 | Nhân viên 4, 9 |
| Kỹ thuật - Công nghệ | Nhân viên 5 | Nhân viên 6, 10 |

> Số quyền của `quanly1` và `nhanvien*` là **83 / 10**, không phải 84 / 11 như bản trước: hai quyền
> `dashboard.systemOverview` và `my-received-tasks.transfer` đã được gỡ có chủ đích, và
> `PermissionSeeder` đã sửa cho khớp.

Dựng lại: `sail artisan migrate:fresh --seed`.

---

## 7. Lưu ý khi merge

1. **Chạy `migrate` trước, build FE sau.** FE mới dựa vào quyền và cột chỉ có sau migration.
2. **Vai trò cũ bị xoá kèm quan hệ gán** — tài khoản đang mang `Admin`, `Quản trị`, `Trưởng phòng`,
   `Tổng hợp lịch`, `Thư ký`, `Văn phòng`, `Lái xe`, `Đại biểu` sẽ mất sạch quyền. Gán lại trước hoặc
   ngay sau khi seed.
3. **11 bảng Lịch công tác + cột `is_petition_overview` bị drop.** Sao lưu nếu còn cần.
4. ~~**3 endpoint `/task-assignment-departments/{id}/users` trả 404** — client ngoài core-fe
   phải sửa theo.~~ **Đã xử lý:** `core-miniapp` chuyển sang
   `/task-assignment-employees/options?department_id=` (nhánh `quanlycongviec`, commit `e56b4e2`).
5. `core-fe/.env` không nằm trong git: cần `VITE_API_BASE_URL` và `VITE_USE_MSW=false`.
