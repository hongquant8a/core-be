# Hướng dẫn & Tài liệu Tích hợp API & CASL - Phân hệ Lịch công tác (Scheduling Module)

> Cập nhật lần cuối: 16:02:45 15/07/2026

Tài liệu này cung cấp mô tả chi tiết, cấu trúc dữ liệu gửi lên (Request Payload), phản hồi trả về (Response Body), và đặc tả phân quyền **CASL (Client-side Authorization)** của tất cả các API thuộc module **Lịch công tác (Scheduling)** để phục vụ việc phát triển giao diện người dùng (Frontend).

---

## 🔐 1. THIẾT LẬP CHUNG & XÁC THỰC
Mọi yêu cầu gửi tới các API dưới đây đều cần các headers bắt buộc sau:
* **`Authorization`**: `Bearer <token_xác_thực>`
* **`X-Organization-Id`**: `<ID_tổ_chức>` (Bắt buộc để cô lập dữ liệu theo từng tenant/tổ chức).
* **`Accept`**: `application/json`
* **`Content-Type`**: `application/json` (Hoặc `multipart/form-data` khi upload file).

---

## 🛡️ 2. PHÂN QUYỀN CASL TRÊN FRONTEND (CASL Authorization)

Hệ thống sử dụng thư viện `@casl/ability` ở Frontend để kiểm soát việc hiển thị UI (nút bấm, menu, form hành động) dựa trên danh sách quyền được trả về từ Backend.

### 2.1. Danh sách Permission tương ứng với CASL Abilities

> ⚠️ Từ **2026-06-05**, hệ thống KHÔNG còn dùng permission phẳng `schedules.*` cho API `/api/schedules/*`.
> Middleware `App\Modules\Scheduling\Middleware\CheckScheduleModulePermission` (alias route `schedule.module`) đọc
> `module_type` trên request (`EXECUTIVE` hoặc `OFFICE`) và map động sang resource permission theo phân hệ,
> qua bảng `MODULE_MAP`:
> ```php
> protected const MODULE_MAP = [
>     'EXECUTIVE' => 'schedules-executive',
>     'OFFICE'    => 'schedules-office',
> ];
> ```
> Tức là mỗi action thực chất kiểm tra quyền `schedules-executive.{action}` hoặc `schedules-office.{action}`
> tùy `module_type` gửi lên (fallback về `schedules.{action}` cũ nếu permission đó còn tồn tại trong DB, chỉ
> để tương thích ngược dữ liệu cũ — **không dùng để cấp quyền mới**). Xem chi tiết danh sách action hợp lệ
> trong `database/seeders/PermissionSeeder.php` (nhóm `Scheduling`).

Các quyền Spatie ở Backend được map trực tiếp sang CASL `action` và `subject` theo quy tắc sau:

| Backend Spatie Permission | CASL Subject | CASL Action | Ý nghĩa / UI áp dụng |
| :--- | :--- | :--- | :--- |
| `schedules-executive.index` / `schedules-office.index` | `Schedule` | `read` | Xem danh sách lịch tuần/ngày, ma trận tuần (theo từng phân hệ). |
| `schedules-executive.show` / `schedules-office.show` | `Schedule` | `read` | Xem chi tiết một lịch công tác cụ thể. |
| `schedules-executive.store` / `schedules-office.store` | `Schedule` | `create` | Hiển thị nút "Tạo lịch", form thêm mới. |
| `schedules-executive.update` / `schedules-office.update` | `Schedule` | `update` | Hiển thị nút "Sửa"; cũng là quyền dùng cho kéo thả sắp xếp (`PATCH /reorder`, xem [schedule-reorder.md](schedule-reorder.md)). |
| `schedules-executive.destroy` / `schedules-office.destroy` | `Schedule` | `delete` | Hiển thị nút "Xóa" lịch đơn lẻ hoặc xóa hàng loạt (`bulk-delete`). |
| `schedules-executive.bulkUpdateStatus` / `schedules-office.bulkUpdateStatus` | `Schedule` | `update` | Cập nhật trạng thái hàng loạt (`bulk-status`). |
| `schedules-executive.changeStatus` / `schedules-office.changeStatus` | `Schedule` | `update` | Đổi trạng thái một lịch (`{id}/status`). |
| `schedules-executive.approve` / `schedules-office.approve` | `Schedule` | `approve` | Hiển thị nút "Duyệt" (Publish) hoặc "Từ chối" (Reject). |
| `schedules-executive.duplicate` / `schedules-office.duplicate` | `Schedule` | `duplicate` | Hiển thị nút "Sao chép lịch" (Duplicate). |
| `schedules-executive.export` / `schedules-office.export` | `Schedule` | `export` | Hiển thị nút xuất Excel/PDF/Word. |
| `schedules-executive.stats` / `schedules-office.stats` | `Schedule` | `read` | Xem số liệu thống kê (`/stats`). |
| `schedules-executive.home` / `schedules-office.home` | `Schedule` | `read` | Truy cập trang tổng quan lịch của phân hệ. |
| `schedules-executive.driver-view` / `schedules-office.driver-view` | `Schedule` | `driver-view` | Dành riêng cho tài xế xem danh sách/chi tiết chuyến đi của mình. |
| `scheduling-employees.index` | `SchedulingEmployee` | `read` | Xem danh sách cán bộ tham gia module lịch. |
| `scheduling-employees.store` | `SchedulingEmployee` | `create` | Thêm cán bộ mới vào danh sách. |
| `scheduling-employees.update` | `SchedulingEmployee` | `update` | Cập nhật ghi chú/trạng thái cán bộ, đồng bộ nhóm (`sync-groups`). |
| `scheduling-employees.destroy` | `SchedulingEmployee` | `delete` | Xóa cán bộ khỏi danh sách (đơn/hàng loạt). |
| `scheduling-employees.stats` | `SchedulingEmployee` | `read` | Xem thống kê cán bộ (`/stats`). |
| `scheduling-employees.export` | `SchedulingEmployee` | `export` | Xuất Excel danh sách cán bộ. |
| `scheduling-employees.import` | `SchedulingEmployee` | `import` | Nhập Excel danh sách cán bộ. |
| `scheduling-employee-groups.index` | `SchedulingEmployeeGroup` | `read` | Xem danh sách nhóm cán bộ lịch. |
| `scheduling-employee-groups.store` | `SchedulingEmployeeGroup` | `create` | Tạo nhóm cán bộ mới. |
| `scheduling-employee-groups.update` | `SchedulingEmployeeGroup` | `update` | Cập nhật thông tin/thành viên nhóm, đồng bộ thành viên (`sync-members`). |
| `scheduling-employee-groups.destroy` | `SchedulingEmployeeGroup` | `delete` | Xóa nhóm cán bộ (đơn/hàng loạt). |
| `scheduling-employee-groups.stats` | `SchedulingEmployeeGroup` | `read` | Xem thống kê nhóm cán bộ (`/stats`). |
| `scheduling-settings.show`* | `SchedulingSetting` | `read` | Xem cấu hình phê duyệt và khung giờ làm việc của đơn vị. |
| `scheduling-settings.update` | `SchedulingSetting` | `update` | Cập nhật cấu hình phê duyệt/khung giờ. |

\* `scheduling-settings.show` tồn tại trong danh sách permission (Spatie) nhưng route `GET /api/scheduling-settings`
hiện **không** gắn middleware `permission:` — mọi user đã đăng nhập và có `X-Organization-Id` hợp lệ đều xem được
cấu hình của tổ chức mình. Chỉ `POST /api/scheduling-settings` (cập nhật) bị chặn bởi `scheduling-settings.update`.

**Endpoint `/options` của `scheduling-employees` và `scheduling-employee-groups` không gắn permission** (chỉ cần
đã qua middleware `ensure.route.org` ở nhóm route cha) — dùng cho dropdown, không cần CASL ability riêng.

### 2.2. Cách định nghĩa CASL Ability trong Vue/React
Khi người dùng đăng nhập thành công, API Auth sẽ trả về danh sách `permissions`. Frontend khởi tạo CASL Ability như sau:

```javascript
import { AbilityBuilder, createMongoAbility } from '@casl/ability';

export function defineAbilitiesFor(permissions) {
  const { can, cannot, build } = new AbilityBuilder(createMongoAbility);

  if (permissions.includes('Super Admin') || permissions.includes('Admin')) {
    can('manage', 'all');
  } else {
    // Duyệt qua danh sách permission thô từ Backend và map sang CASL
    permissions.forEach((perm) => {
      const [resource, action] = perm.split('.'); // ví dụ: "schedules-executive.store"

      let caslAction = action;
      let caslSubject = resource;

      // Chuẩn hóa tên subject — cả 2 phân hệ đều map về cùng subject "Schedule",
      // FE tự phân biệt UI theo module_type đang active (không tách subject riêng theo phân hệ)
      if (resource === 'schedules-executive' || resource === 'schedules-office') caslSubject = 'Schedule';
      if (resource === 'scheduling-employees') caslSubject = 'SchedulingEmployee';
      if (resource === 'scheduling-employee-groups') caslSubject = 'SchedulingEmployeeGroup';
      if (resource === 'scheduling-settings') caslSubject = 'SchedulingSetting';

      // Chuẩn hóa tên action
      if (action === 'index' || action === 'show') caslAction = 'read';
      if (action === 'store') caslAction = 'create';
      if (action === 'destroy') caslAction = 'delete';

      can(caslAction, caslSubject);
    });
  }

  return build();
}
```

---

## 📅 3. API TRUNG TÂM - LỊCH CÔNG TÁC (`/api/schedules`)

> Sắp xếp lại thứ tự lịch (`PATCH /api/schedules/reorder`) không được mô tả lại ở đây — xem chi tiết đầy đủ
> (query params, request body, permission, ví dụ) tại [schedule-reorder.md](schedule-reorder.md).

### 3.1. Danh sách các Trường dữ liệu gửi lên (POST / PUT Payload)
FormRequest của hệ thống tự động chuẩn hóa (normalization) một số trường alias/legacy từ Frontend để đảm bảo tương thích ngược:

| Trường Payload (FE gửi) | Kiểu dữ liệu | Bắt buộc | Mô tả & Luật Validate / Chuẩn hóa từ Backend |
| :--- | :--- | :--- | :--- |
| `module_type` | `string` | **Có** | `EXECUTIVE` (Lịch thường trực) hoặc `OFFICE` (Lịch văn phòng). |
| `content` | `string` | **Có** | Nội dung chính của lịch công tác. (*Frontend có thể gửi `title`, hệ thống sẽ tự động map sang `content`*). |
| `location` | `string` | Không | Địa điểm họp/công tác (Độ dài tối đa 500 ký tự). |
| `session` | `string` | Không | Buổi diễn ra: `S` (Sáng), `C` (Chiều), `T` (Tối). Nếu gửi `MORNING`/`AFTERNOON`/`EVENING`, Backend tự chuẩn hóa tương ứng thành `S`/`C`/`T`. |
| `date_time` | `string` | **Có** | Thời gian cụ thể diễn ra lịch. Định dạng: `YYYY-MM-DD HH:mm:ss`. (*Nếu Frontend gửi `date` chuẩn `YYYY-MM-DD`, Backend tự map sang `date_time` với giờ mặc định `08:00:00`*). |
| `status` | `integer`/`string`| Không | `0` (DRAFT), `1` (PENDING), `2` (PUBLISHED), `3` (CANCELLED). Có thể gửi chuỗi chữ hoa (`"DRAFT"`, `"PENDING"`, `"PUBLISHED"`, `"APPROVED"`, `"CANCELLED"`) và Backend tự chuyển sang dạng số. |
| `host_id` | `integer` | Không | ID User chủ trì (Kiểm tra `exists:users,id`). |
| `host_text` | `string` | Không | Tên hiển thị người chủ trì dạng nhập tự do (tối đa 255 ký tự). |
| `driver_id` | `integer` | Không | ID Tài xế phục vụ (Kiểm tra `exists:users,id`). |
| `driver_text` | `string` | Không | Tên hiển thị tài xế dạng nhập tự do (tối đa 255 ký tự). |
| `preparation_unit`| `string` | Không | Đơn vị chuẩn bị nội dung (tối đa 500 ký tự). (*Frontend có thể gửi `preparation_location`, Backend tự map*). |
| `departments_text` | `string` | Không | Ban ngành tham gia (dạng text tự do hiển thị). |
| `participants_text`| `string` | Không | Thành phần tham gia (dạng text tự do hiển thị). |
| `participant_count`| `string` | Không | Số lượng người tham dự (dạng chuỗi tự do, tối đa 50 ký tự). |
| `nature` | `string` | Không | Tính chất: `HOST` (Chủ trì) hoặc `ATTEND` (Tham gia). |
| `is_important` | `boolean` | Không | Đánh dấu lịch quan trọng (hiển thị nổi bật). Mặc định `false`. |
| `participants` | `array` | Không | Mảng người nhận thông báo trực tiếp. Chi tiết cấu trúc phần tử bên dưới. |
| `reminders` | `array` | Không | Mảng mốc nhắc lịch. Chi tiết cấu trúc phần tử bên dưới. |
| `files` | `array` | Không | Mảng các file đính kèm (`UploadFile[]`). |
| `remove_media_ids`| `array` | Không | *(Chỉ dùng cho PUT/cập nhật)* Mảng ID các file đính kèm cần xóa khỏi lịch. |

#### Phân tích chi tiết mảng cấu trúc phức tạp:
*   **`participants`**: Mảng các đối tượng chứa `user_id` (chọn user lẻ nhận thông báo) hoặc `group_id` (chọn nhóm cán bộ nhận thông báo), đồng thời hỗ trợ truyền `display_name` để linh hoạt thay đổi cách hiển thị tên nếu là khách ngoại bộ hoặc chức danh khác biệt:
    ```json
    [
      { "user_id": 12, "display_name": "Nguyễn Văn A (Khách mời)" },
      { "group_id": 3 }
    ]
    ```
*   **`reminders`**: Mảng cấu hình các mốc thời gian nhắc nhở trước giờ họp:
    ```json
    [
      {
        "minutes_before": 15, // Số phút nhắc trước giờ bắt đầu
        "channels": ["fcm", "mail"] // Các kênh nhận: fcm, mail, zalo, sms
      }
    ]
    ```

---

### 3.2. Danh sách các Trường dữ liệu trả về (Response ScheduleResource)
Khi lấy thông tin chi tiết hoặc danh sách lịch, Backend trả về đối tượng có cấu trúc chuẩn như sau:

| Trường Response | Kiểu dữ liệu | Có thể Null | Mô tả chi tiết |
| :--- | :--- | :--- | :--- |
| `id` | `integer` | Không | ID duy nhất của lịch công tác. |
| `organization_id` | `integer` | Không | ID Đơn vị sở hữu bản ghi. |
| `module_type` | `string` | Không | `EXECUTIVE` hoặc `OFFICE`. |
| `content` | `string` | Không | Nội dung lịch làm việc. |
| `location` | `string` | Có | Địa điểm tổ chức. |
| `session` | `string` | Không | Buổi diễn ra (`S`, `C`, `T`). |
| `date_time` | `string (ISO)`| Không | Thời điểm bắt đầu lịch công tác (`YYYY-MM-DDTHH:mm:ssZ`). |
| `host_id` | `integer` | Có | ID User chủ trì chính thức. |
| `host_text` | `string` | Có | Tên text tự do của người chủ trì. |
| `host` | `object` | Có | Thông tin cơ bản của User chủ trì (nếu được load). |
| `driver_id` | `integer` | Có | ID User tài xế chính thức. |
| `driver_text` | `string` | Có | Tên text tự do của tài xế. |
| `driver` | `object` | Có | Thông tin cơ bản của User tài xế (nếu được load). |
| `preparation_unit`| `string` | Có | Đơn vị chuẩn bị tài liệu. |
| `departments_text` | `string` | Có | Chuỗi hiển thị ban ngành phối hợp. |
| `participants_text`| `string` | Có | Chuỗi hiển thị thành phần tham dự. |
| `participant_count`| `string` | Có | Số lượng người tham dự. |
| `nature` | `string` | Không | `HOST` hoặc `ATTEND`. |
| `is_important` | `boolean` | Không | Cờ đánh dấu lịch quan trọng. |
| `status` | `integer` | Không | Trạng thái hiện tại của lịch (`0`, `1`, `2`, `3`). |
| `approved_by` | `integer` | Có | ID User phê duyệt lịch. |
| `approved_at` | `string (ISO)` | Có | Thời điểm duyệt. |
| `approver` | `object` | Có | Thông tin cơ bản của User duyệt lịch. |
| `sort_order` | `integer` | Không | Thứ tự sắp xếp thủ công (kéo thả). |
| `week_number` | `integer` | Không | Số tuần trong năm (1 - 53). |
| `year` | `integer` | Không | Năm diễn ra lịch. |
| `created_by` | `integer` | Không | ID User tạo lịch. |
| `creator` | `object` | Có | Thông tin User tạo lịch. |
| `updated_by` | `integer` | Có | ID User chỉnh sửa lịch gần nhất. |
| `editor` | `object` | Có | Thông tin User sửa đổi lịch gần nhất. |
| `recipients` | `array` | Có | Mảng chi tiết quan hệ nhận thông báo trung gian (User & Group), tích hợp sẵn `display_name`. |
| `participants` | `array` | Có | Mảng định dạng phẳng chứa thông tin User tham dự (dạng rút gọn). |
| `reminders` | `array` | Có | Mảng các mốc nhắc lịch đã cấu hình. |
| `attachments` | `array` | Có | Danh sách tài liệu đính kèm (có ID, Tên hiển thị, Dung lượng, Mime, URL). |
| `created_at` | `string (ISO)` | Không | Thời điểm tạo bản ghi. |
| `updated_at` | `string (ISO)` | Không | Thời điểm cập nhật bản ghi gần nhất. |

#### Cấu trúc mảng `recipients` trả về:
```json
[
  {
    "id": 145,
    "user_id": 10,
    "group_id": null,
    "display_name": "Ông Nguyễn Văn A (Đại biểu)",
    "user": {
      "id": 10,
      "name": "Nguyễn Văn A",
      "email": "nva@snvdn.gov.vn"
    },
    "group": null
  },
  {
    "id": 146,
    "user_id": null,
    "group_id": 2,
    "display_name": "Ban Thư ký",
    "user": null,
    "group": {
      "id": 2,
      "name": "Nhóm Thư ký"
    }
  }
]
```

#### Cấu trúc mảng `participants` trả về (chuẩn phẳng tương thích ngược):
```json
[
  {
    "id": 145,
    "user_id": 10,
    "group_id": null,
    "display_name": "Ông Nguyễn Văn A (Đại biểu)",
    "position_name": "Chuyên viên",
    "is_external": false
  }
]
```

#### Cấu trúc mảng `attachments` trả về:
```json
[
  {
    "id": 34,
    "title": "Báo cáo công tác tuần",
    "file_name": "report_t23.pdf",
    "file_size": 1048576,
    "mime_type": "application/pdf",
    "url": "http://localhost:8000/storage/schedules/2026/06/uuid.pdf"
  }
]
```

---

### 3.3. Ví dụ luồng CRUD của Schedules

#### 3.3.1. Lấy danh sách (GET /api/schedules)
* **Query Params:** `module_type=EXECUTIVE&week_number=23&year=2026`
* **Response mẫu (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "organization_id": 1,
      "module_type": "EXECUTIVE",
      "content": "Giao ban Thường trực Thành ủy",
      "location": "Phòng họp số 1",
      "session": "S",
      "date_time": "2026-06-01T08:30:00.000000Z",
      "host_id": 12,
      "host_text": "Đ/c Nguyễn Văn Hùng - Bí thư",
      "driver_id": 5,
      "driver_text": "Tài xế Trần Văn Lái",
      "departments_text": "Sở Nội vụ, Sở Kế hoạch Đầu tư",
      "participants_text": "Ông Trần Văn B, Đại diện Sở Nội vụ",
      "nature": "HOST",
      "is_important": false,
      "status": 2,
      "sort_order": 10,
      "week_number": 23,
      "year": 2026
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

#### 3.3.2. Thống kê (GET /api/schedules/stats)
* **Endpoint:** `GET /api/schedules/stats`
* **Permission:** `schedules-executive.stats` hoặc `schedules-office.stats` (theo `module_type` gửi lên).
* **Query Params:** `module_type` (required), `search`, `from_date`, `to_date`, `session`.
* Trả về object thống kê số lượng lịch theo trạng thái (cấu trúc do `ScheduleService::stats()` build).

---

## 👥 4. QUẢN LÝ CÁN BỘ LỊCH CÔNG TÁC (`/api/scheduling-employees`)

Nghiệp vụ quản lý danh sách cán bộ được phép làm người chủ trì hoặc liên kết tài xế trong phân hệ lịch.

### 4.1. Cấu trúc dữ liệu gửi lên (POST / PUT Payload)

| Trường Payload | Kiểu dữ liệu | Bắt buộc | Mô tả & Luật Validate |
| :--- | :--- | :--- | :--- |
| `user_id` | `integer` | Không | ID tài khoản người dùng tương ứng từ CSDL (Kiểm tra `exists:users,id`). Phải duy nhất trong đơn vị. |
| `name` | `string` | **Có** (nếu `user_id` null) | Tên cán bộ (tự nhập nếu không liên kết tài khoản). |
| `position_name` | `string` | Không | Chức vụ hiển thị. |
| `department` | `string` | Không | Phòng ban công tác. |
| `phone` | `string` | Không | Số điện thoại liên hệ. |
| `email` | `string` | Không | Địa chỉ email (Định dạng email). |
| `priority_weight`| `integer` | Không | Trọng số ưu tiên (phục vụ tự động sắp xếp theo thứ tự chức vụ). |
| `status` | `string` | Không | Trạng thái hoạt động: `"active"` hoặc `"inactive"`. |
| `sort_order` | `integer` | Không | Thứ tự hiển thị thủ công. |

*   **POST (Đăng ký mới)**: Yêu cầu ít nhất `user_id` hoặc nhập tay trường `name`.
*   **PUT (Cập nhật)**: Cho phép sửa đổi tất cả các trường trên của cán bộ nghiệp vụ.

---

### 4.2. Cấu trúc dữ liệu trả về (Response SchedulingEmployeeResource)

| Trường Response | Kiểu dữ liệu | Có thể Null | Mô tả |
| :--- | :--- | :--- | :--- |
| `id` | `integer` | Không | ID bản ghi cán bộ nghiệp vụ. |
| `organization_id` | `integer` | Không | ID Đơn vị quản lý cán bộ. |
| `user_id` | `integer` | Có | ID User liên kết. |
| `user` | `object` | Có | Chi tiết User liên kết từ CSDL (nếu được load). |
| `name` | `string` | Không | Tên hiển thị của cán bộ. |
| `position_name` | `string` | Có | Chức vụ. |
| `department` | `string` | Có | Phòng ban. |
| `phone` | `string` | Có | Điện thoại. |
| `email` | `string` | Có | Email. |
| `priority_weight`| `integer` | Không | Trọng số ưu tiên sắp xếp chức vụ. |
| `status` | `string` | Không | Trạng thái: `"active"` hoặc `"inactive"`. |
| `sort_order` | `integer` | Không | Thứ tự hiển thị. |
| `groups` | `array` | Có | Mảng các nhóm cán bộ mà cán bộ này trực thuộc. |
| `created_at` | `string (ISO)` | Không | Thời điểm thêm vào nghiệp vụ lịch. |
| `updated_at` | `string (ISO)` | Không | Thời điểm cập nhật gần nhất. |

* **Ví dụ Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "organization_id": 1,
    "user_id": 14,
    "name": "Nguyễn Thị Thư Ký",
    "position_name": "Thư ký văn phòng",
    "department": "Phòng Hành chính - Tổng hợp",
    "phone": "0987654321",
    "email": "nttk@snvdn.gov.vn",
    "priority_weight": 5,
    "status": "active",
    "sort_order": 0,
    "created_at": "2026-06-01T15:30:00Z",
    "updated_at": "2026-06-01T15:30:00Z"
  }
}
```

---

### 4.3. Các endpoint bổ sung

| Endpoint | Method | Permission | Mô tả |
| :--- | :--- | :--- | :--- |
| `/api/scheduling-employees/stats` | `GET` | `scheduling-employees.stats` | Thống kê cán bộ (`search`, `status`). |
| `/api/scheduling-employees/options` | `GET` | Không yêu cầu permission riêng | Danh sách rút gọn `{id, user_id, name}` các cán bộ `status=active`, dùng cho dropdown chọn chủ trì/lái xe. |
| `/api/scheduling-employees/export` | `GET` | `scheduling-employees.export` | Xuất danh sách cán bộ ra Excel. |
| `/api/scheduling-employees/import` | `POST` | `scheduling-employees.import` | Nhập danh sách cán bộ từ file Excel (`.xlsx`, `.xls`, `.csv`). Body: `file` (required). |
| `/api/scheduling-employees/{id}/sync-groups` | `POST` | `scheduling-employees.update` | Đồng bộ danh sách nhóm mà cán bộ trực thuộc. Body: `group_ids` (array, required). |

* **Ví dụ Request `sync-groups`:**
```json
{
  "group_ids": [1, 2]
}
```

---

## 👥 5. QUẢN LÝ NHÓM CÁN BỘ LỊCH (`/api/scheduling-employee-groups`)

Hỗ trợ nhóm nhiều cán bộ nhận tin lại với nhau (như nhóm "Ban Thường Vụ", "Văn phòng").

### 5.1. Cấu trúc dữ liệu gửi lên (POST / PUT Payload)

| Trường Payload | Kiểu dữ liệu | Bắt buộc | Mô tả & Luật Validate |
| :--- | :--- | :--- | :--- |
| `name` | `string` | **Có** | Tên nhóm cán bộ (tối đa 255 ký tự). |
| `description` | `string` | Không | Mô tả ngắn gọn của nhóm. |
| `status` | `string` | Không | Trạng thái nhóm: `"active"` hoặc `"inactive"`. |
| `sort_order` | `integer` | Không | Thứ tự hiển thị. |
| `employee_ids` | `array` | Không | Mảng ID các cán bộ thuộc nhóm (kiểm tra `exists:scheduling_employees,id`). |

* **Ví dụ Payload (Tạo nhóm mới kèm thành viên):**
```json
{
  "name": "Nhóm Thường Trực Thành Ủy",
  "description": "Các đồng chí lãnh đạo chủ trì Thường trực",
  "status": "active",
  "employee_ids": [1, 2, 5]
}
```

---

### 5.2. Cấu trúc dữ liệu trả về (Response SchedulingEmployeeGroupResource)

| Trường Response | Kiểu dữ liệu | Có thể Null | Mô tả |
| :--- | :--- | :--- | :--- |
| `id` | `integer` | Không | ID duy nhất của nhóm. |
| `organization_id` | `integer` | Không | ID Đơn vị sở hữu nhóm. |
| `name` | `string` | Không | Tên nhóm. |
| `description` | `string` | Có | Mô tả nhóm. |
| `status` | `string` | Không | Trạng thái hoạt động (`"active"` hoặc `"inactive"`). |
| `sort_order` | `integer` | Không | Thứ tự hiển thị. |
| `members_count` | `integer` | Có | Tổng số thành viên trong nhóm (nhiều cán bộ). |
| `members` | `array` | Có | Mảng chi tiết cán bộ trong nhóm (nạp cấu trúc `SchedulingEmployeeResource`). |
| `created_at` | `string (ISO)` | Không | Ngày tạo nhóm. |
| `updated_at` | `string (ISO)` | Không | Ngày sửa đổi gần nhất. |

* **Ví dụ Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "organization_id": 1,
    "name": "Nhóm Thường Trực Thành Ủy",
    "description": "Các đồng chí lãnh đạo chủ trì Thường trực",
    "status": "active",
    "sort_order": 0,
    "members_count": 3,
    "members": [
      {
        "id": 1,
        "name": "Nguyễn Văn Hùng",
        "position_name": "Bí thư Thành ủy",
        "status": "active"
      }
    ],
    "created_at": "2026-06-01T16:00:00Z",
    "updated_at": "2026-06-01T16:05:00Z"
  }
}
```

---

### 5.3. Các endpoint bổ sung

| Endpoint | Method | Permission | Mô tả |
| :--- | :--- | :--- | :--- |
| `/api/scheduling-employee-groups/stats` | `GET` | `scheduling-employee-groups.stats` | Thống kê nhóm cán bộ (`search`). |
| `/api/scheduling-employee-groups/options` | `GET` | Không yêu cầu permission riêng | Danh sách rút gọn `{id, name}` các nhóm `status=active`, dùng cho dropdown. |
| `/api/scheduling-employee-groups/{id}/sync-members` | `POST` | `scheduling-employee-groups.update` | Đồng bộ toàn bộ thành viên của nhóm. Body: `employee_ids` (array, required). |

> Nhóm cán bộ **không có** endpoint `export`/`import` (chỉ `scheduling-employees` có).

* **Ví dụ Request `sync-members`:**
```json
{
  "employee_ids": [1, 2, 5]
}
```

---

## 🛠️ 6. API CẤU HÌNH HỆ THỐNG LỊCH (`/api/scheduling-settings`)

Dành riêng cho Quản trị viên điều hành cấu hình duyệt lịch của đơn vị. **Từ 2026-06-08**, cấu hình phê duyệt và
khung giờ làm việc được **tách riêng theo từng phân hệ** (`executive_*` / `office_*`) — không còn 1 cấu hình
`requires_approval`/`working_sessions` dùng chung cho cả 2 phân hệ như thiết kế cũ.

Backend lưu trên 2 bảng: `org_scheduling_settings` (các trường `*_requires_approval`, `*_working_sessions` —
Model `App\Modules\Scheduling\Models\OrgSchedulingSettings`) và `scheduling_settings` (chỉ còn `default_channels`
— Model `App\Modules\Scheduling\Models\SchedulingSetting`, các trường legacy `approval_enabled` /
`approval_module_types` / `requires_approval` đã bị xóa khỏi schema). Controller `SchedulingSettingController`
gộp cả 2 bảng lại thành một response phẳng duy nhất.

### 6.1. Chi tiết API Cập nhật & Trả về

* **Endpoint lấy cấu hình:** `GET /api/scheduling-settings` (không yêu cầu permission riêng, chỉ cần đăng nhập + `X-Organization-Id`)
* **Endpoint cập nhật:** `POST /api/scheduling-settings` (yêu cầu permission `scheduling-settings.update`)

#### Các trường cấu hình trong cấu trúc Payload và Response:

| Khóa Cấu Hình | Kiểu dữ liệu | Bắt buộc khi cập nhật | Mô tả chi tiết |
| :--- | :--- | :--- | :--- |
| `organization_id` | `integer` | — (chỉ có ở Response) | ID tổ chức sở hữu cấu hình (lấy theo `X-Organization-Id`, không nhận từ client khi update). |
| `executive_requires_approval` | `boolean` | **Có** (theo `StoreOrgSchedulingSettingsRequest::rules()`) | Lịch phân hệ **Thường trực (EXECUTIVE)** có cần phê duyệt trước khi công bố hay không. |
| `office_requires_approval` | `boolean` | **Có** | Lịch phân hệ **Văn phòng (OFFICE)** có cần phê duyệt trước khi công bố hay không. |
| `executive_working_sessions` | `object` \| `null` | Không (chỉ cập nhật nếu request có key này — FormRequest không validate cấu trúc, BE nhận nguyên payload) | Khung giờ làm việc các buổi của phân hệ Thường trực. Cấu trúc gồm 3 khóa `MORNING`, `AFTERNOON`, `EVENING`, mỗi khóa có `start`/`end` (`HH:mm`). |
| `office_working_sessions` | `object` \| `null` | Không | Khung giờ làm việc các buổi của phân hệ Văn phòng — cùng cấu trúc như trên, độc lập với `executive_working_sessions`. |
| `default_channels` | `array` | Không | Mảng kênh thông báo mặc định, dùng chung cho cả 2 phân hệ (ví dụ: `["inapp", "email"]`). Lưu ở bảng `scheduling_settings` riêng. |

> Lưu ý: `POST /api/scheduling-settings` chỉ cập nhật những key thực sự có mặt trong request (`$request->has(...)`)
> — không gửi key nào thì giữ nguyên giá trị cũ của key đó.

* **Ví dụ Request cập nhật (POST):**
```json
{
  "executive_requires_approval": true,
  "office_requires_approval": false,
  "executive_working_sessions": {
    "MORNING": { "start": "07:00", "end": "11:30" },
    "AFTERNOON": { "start": "13:30", "end": "17:30" },
    "EVENING": { "start": "19:00", "end": "21:00" }
  },
  "office_working_sessions": {
    "MORNING": { "start": "07:30", "end": "11:30" },
    "AFTERNOON": { "start": "13:30", "end": "17:00" },
    "EVENING": { "start": "19:00", "end": "21:00" }
  },
  "default_channels": ["inapp"]
}
```

* **Phản hồi mẫu thành công (200 OK / Lấy hoặc Lưu):**
```json
{
  "success": true,
  "data": {
    "organization_id": 1,
    "default_channels": ["inapp"],
    "executive_requires_approval": true,
    "office_requires_approval": false,
    "executive_working_sessions": {
      "MORNING": { "start": "07:00", "end": "11:30" },
      "AFTERNOON": { "start": "13:30", "end": "17:30" },
      "EVENING": { "start": "19:00", "end": "21:00" }
    },
    "office_working_sessions": {
      "MORNING": { "start": "07:30", "end": "11:30" },
      "AFTERNOON": { "start": "13:30", "end": "17:00" },
      "EVENING": { "start": "19:00", "end": "21:00" }
    }
  }
}
```

---

## 🚖 7. PHÂN HỆ DÀNH CHO LÁI XE (DRIVER APIs)

Các API được kiểm soát quyền chặt chẽ chỉ dành riêng cho tài xế để phục vụ công tác điều hành xe công.

### 7.1. Danh sách lịch trình phân công tài xế
* **Endpoint:** `GET /api/schedules/driver-view`
* **Query Params:**
  - `from_date` (string, date): Định dạng `YYYY-MM-DD` để lọc từ ngày.
  - `to_date` (string, date): Định dạng `YYYY-MM-DD` để lọc đến ngày.
* **Phản hồi mẫu:** Trả về danh sách các lịch trình mà tài xế hiện tại được phân công (`driver_id === current_user.id`) và lịch **đã được duyệt công bố** (`status === 2`).

### 7.2. Xem thông tin chuyến đi rút gọn của tài xế
* **Endpoint:** `GET /api/schedules/driver-view/{id}`
* > [!IMPORTANT]
  > API này chỉ trả về thông tin bảo mật thu gọn (`DriverScheduleResource`): Giờ giấc, điểm đi/đến, lãnh đạo chủ trì, tên xe và nội dung lộ trình. Mọi thông tin nhạy cảm của buổi họp kín đều được ẩn đi.

---

## 📥 8. XUẤT FILE BÁO CÁO HÀNH CHÍNH (EXPORT APIs)
Tải trực tiếp tệp tin lịch tuần công tác.
* **Xuất Excel (.xlsx):** `GET /api/schedules/export?week_number=23&year=2026&module_type=EXECUTIVE`
* **Xuất PDF (.pdf):** `GET /api/schedules/export-pdf?week_number=23&year=2026&module_type=EXECUTIVE`
* **Xuất Word (.docx):** `GET /api/schedules/export-word?week_number=23&year=2026&module_type=EXECUTIVE`
