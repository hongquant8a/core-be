# CLAUDE.md

Hướng dẫn hành vi chung, giảm các lỗi thường gặp khi LLM viết code.

**Thứ tự ưu tiên:** phần này là nền chung. Khi mâu thuẫn với quy ước Danatec bên dưới hoặc với tài liệu trong `docs/system/`, **quy ước dự án thắng**.

**Đánh đổi:** các hướng dẫn này thiên về cẩn trọng hơn là nhanh. Việc nhỏ thì tự cân nhắc.

## 1. Nghĩ trước khi viết code

**Đừng suy đoán. Đừng giấu chỗ đang phân vân. Nói rõ đánh đổi.**

Trước khi bắt tay làm:
- Nêu giả định của mình một cách tường minh. Không chắc thì hỏi.
- Có nhiều cách hiểu thì trình bày ra — đừng tự chọn một cách im lặng.
- Có cách đơn giản hơn thì nói. Phản biện khi thấy cần.
- Chỗ nào không rõ thì dừng lại, gọi tên chỗ khó hiểu, và hỏi.

## 2. Đơn giản trước đã

**Viết lượng code tối thiểu giải quyết được vấn đề. Không làm gì mang tính phòng xa.**

- Không thêm chức năng ngoài yêu cầu.
- Không trừu tượng hoá cho đoạn code chỉ dùng một lần.
- Không thêm "linh hoạt" hay "cấu hình được" khi không ai yêu cầu.
- Không bắt lỗi cho tình huống không thể xảy ra.

Tự hỏi: "một senior engineer có nói cái này phức tạp quá mức không?" Nếu có thì làm gọn lại.

## 3. Sửa đúng chỗ cần sửa

**Chỉ động vào phần bắt buộc phải động. Chỉ dọn phần mình bày ra.**

Khi sửa code có sẵn:
- Không "cải thiện" code, comment hay format ở xung quanh.
- Không tái cấu trúc thứ đang chạy tốt.
- Bám theo style hiện có, kể cả khi mình muốn làm khác.
- Thấy code chết không liên quan thì báo lại — đừng tự xoá.

Khi thay đổi của mình để lại phần thừa:
- Xoá import/biến/hàm mà **chính thay đổi của mình** làm cho không còn ai dùng.
- Không xoá code chết có sẵn từ trước, trừ khi được yêu cầu.

Phép thử: mọi dòng bị đổi đều phải truy ngược được về yêu cầu của người dùng.

## 4. Làm việc theo mục tiêu kiểm chứng được

**Định nghĩa tiêu chí hoàn thành. Lặp cho tới khi kiểm chứng được.**

Chuyển yêu cầu thành mục tiêu kiểm chứng được:
- "Thêm validation" → "viết test cho input không hợp lệ, rồi làm cho test pass"
- "Sửa bug" → "viết test tái hiện bug, rồi làm cho test pass"
- "Refactor X" → "test pass trước và sau đều như nhau"

Việc nhiều bước thì nêu kế hoạch ngắn:
```
1. [Bước] → kiểm chứng: [cách kiểm]
2. [Bước] → kiểm chứng: [cách kiểm]
3. [Bước] → kiểm chứng: [cách kiểm]
```

Tiêu chí rõ thì tự chạy độc lập được. Tiêu chí mơ hồ ("làm cho nó chạy") thì phải hỏi lại liên tục.

---

**Các hướng dẫn này đang phát huy tác dụng nếu:** diff ít thay đổi thừa hơn, ít phải viết lại do làm phức tạp quá mức, và câu hỏi làm rõ đến **trước** khi triển khai chứ không phải sau khi đã sai.

---

# Laravel Modular — Quy ước Danatec

## 1. Môi trường & Ngôn ngữ

- **Ngôn ngữ**: Tiếng Việt cho toàn bộ tài liệu, phản hồi và comment giải thích logic.
- **Lệnh**: Luôn dùng `sail` thay `php`. Ví dụ: `sail artisan migrate`, `sail artisan scribe:generate`.

## 2. Cấu trúc Thư mục

Làm việc trong `/app/Modules/{Module}/`. Namespace phải khớp thư mục: `App\Modules\{Module}\Controllers`, ...

**Cấu trúc chuẩn mỗi module:**
```
app/Modules/{Module}/
  Controllers/
  Services/
  Models/
  Requests/
  Resources/
  Enums/
  Events/          ← khi có Event-Driven (xem phần EDA)
  Listeners/       ← khi có Event-Driven
  Observers/       ← khi có Event-Driven
  Jobs/            ← khi có Event-Driven
  Notifications/   ← khi có Event-Driven
  Console/Commands/
  Concerns/        ← tùy chọn (trait nội bộ module)
  Middleware/      ← tùy chọn
  Policies/        ← tùy chọn
```

**Enum** — mỗi module có `Enums/`, enum phải có `values()` và `rule()`:
```php
enum MeetingStatusEnum: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}
// Dùng trong FormRequest: 'status' => ['required', MeetingStatusEnum::rule()]
```

**Enum Lookup Endpoint** — nếu module có ≥1 Enum dùng trong FormRequest mà FE cần dựng dropdown, thêm 1 endpoint gộp toàn bộ Enum của module để FE không phải hardcode `value`/`label`:
```php
// app/Modules/{Module}/Controllers/EnumController.php — không cần Service, logic thuần map cases
public function index()
{
    return $this->success([
        'xxx_status' => $this->mapEnum(XxxStatusEnum::cases()),
        // 1 key snake_case (bỏ hậu tố Enum) cho MỖI Enum của module
    ]);
}

private function mapEnum(array $cases): array
{
    return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
}
```
```php
// app/Modules/{Module}/Routes/enum.php — 1 action duy nhất
Route::get('/', [EnumController::class, 'index']);
```
Đăng ký `Route::prefix('{module}-enums')->group(...)` trong `routes/api.php`, vẫn trong nhóm `auth:sanctum` nhưng:
- **Không** `ensure.route.org` — dữ liệu Enum không tenant-scoped (vẫn cần header `X-Organization-Id` vì middleware `set.permissions.team` bắt buộc cho toàn nhóm auth).
- **Không** `permission:` — dữ liệu tra cứu dùng chung cho nhiều form/permission khác nhau trong module, không phải resource CRUD của 1 quyền cụ thể (giống "general views" của `app/Modules/Scheduling/Routes/schedule.php`).

Response: `{ "success": true, "data": { "xxx_status": [{"value": "active", "label": "Đang hưởng"}, ...], "yyy_type": [...] } }`.
Tham khảo: `app/Modules/Beneficiary/Controllers/EnumController.php`.

**Tên bảng** — bảng danh mục và pivot phải có tiền tố module:
- Đúng: `meeting_rooms`, `meeting_agendas`, `task_assignment_priorities`, `meeting_meeting_room`
- Sai: `rooms`, `priorities` (xung đột giữa module)

## 3. Bộ chức năng chuẩn & HTTP Convention

**Mỗi module mới phải có đủ:** `stats`, `index`, `show`, `store`, `update`, `destroy`, `bulkDestroy`, `bulkUpdateStatus`, `changeStatus`, `export`, `import`.

**Bộ lọc `index`** phải có: tìm kiếm theo tên/trường chính, `status`, khoảng `created_at` (from/to), sắp xếp theo `id`, `created_at`, `updated_at` và các trường phù hợp.

**HTTP Method chuẩn:**

| Action | Method | Route |
|---|---|---|
| Xóa hàng loạt | `DELETE` | `/bulk-delete` — body `{"ids":[...]}` |
| Cập nhật trạng thái hàng loạt | `PATCH` | `/bulk-status` |
| Đổi trạng thái đơn | `PATCH` | `/{id}/status` |
| Sắp xếp lại | `PATCH` | `/reorder` |

> Laravel tự parse JSON body cho DELETE — không dùng POST thay thế.

## 4. Controller & Service Layer

**Controller** chỉ làm: nhận request → validate (FormRequest) → gọi Service → trả response chuẩn.  
Không đặt query phức tạp, sync quan hệ, xử lý trạng thái, import/export trong Controller.

**Service:**
- Namespace: `App\Modules\{Module}\Services`, tên class: `{Resource}Service` (vd: `MeetingService`, `TaskAssignmentItemService`).
- Giữ bộ method chuẩn tương ứng các action ở mục 3.
- Dùng `DB::transaction()` khi ghi nhiều bước có phụ thuộc. Không dùng transaction cho read hoặc single-write đơn lẻ.
- Nếu transaction có thao tác file: `try/catch` cleanup file khi lỗi (tránh lệch DB vs storage).
- Media: **module hiện có** đi qua `App\Modules\Core\Services\MediaService` — không gọi `addMedia()` hay `Storage::put/delete` trực tiếp. **Module mới có quan hệ cha — con** gọi spatie thẳng trong Service theo [docs/system/QUAN_HE_CHA_CON.md](docs/system/QUAN_HE_CHA_CON.md) §0; tệp nhạy cảm bắt buộc `->useDisk('private')`.
- **Service không bao giờ gọi trực tiếp Notification/Mail/Broadcast — chỉ `event(new XxxEvent($model))`.**  (Chi tiết xem phần EDA.)

**Tenant (đa tổ chức):**
- Resource thuộc tổ chức phải có `organization_id`; mọi query scope theo tổ chức hiện tại (middleware `set.permissions.team` — header `X-Organization-Id`).
- Thao tác theo ID (`show`, `update`, `destroy`, `changeStatus`) và bulk phải chặn cross-tenant.
- `store`/`import` gán `organization_id` từ ngữ cảnh hiện tại, không nhận từ client.

### Quan hệ cha — con (module MỚI)

> **Phạm vi:** chỉ áp cho **module mới** và **quan hệ mới**. Module đã làm (`Auth`, `Core`, `Meeting`, `Scheduling`, `TaskAssignment`, `Beneficiary`) **giữ nguyên** — chỉ tái cấu trúc khi có yêu cầu rõ ràng, không refactor kèm PR khác.

Hai tài liệu, đọc theo thứ tự:

| Tài liệu | Là gì | Đọc khi nào |
|---|---|---|
| [docs/system/QUAN_HE_CHA_CON.md](docs/system/QUAN_HE_CHA_CON.md) | **Quy tắc và lý do** — bảng quyết định 5 dạng, 12 bẫy đã gặp, bảng Cấm, checklist | Trước khi bắt đầu; khi phân vân "ca này thuộc dạng nào" |
| [docs/system/QUAN_HE_CHA_CON_VIDU.md](docs/system/QUAN_HE_CHA_CON_VIDU.md) | **Mã tham chiếu** — 44 tập tin trọn vẹn của module mẫu `Employee`, copy chạy được | Lúc đang gõ, cần khuôn cụ thể |

**Nhận dạng quan hệ rồi copy đúng khuôn:**

| Dạng | Nhận biết | Bộ action |
|---|---|---|
| **A.** 1–n có tệp | `hasMany`, dòng con có tài liệu đính kèm | 6: `index, show, store, update, destroy, bulkDestroy` |
| **B.** 1–n không tệp | `hasMany`, chỉ có cột dữ liệu | 6 (như A) |
| **C.** 1–1 | `hasOne`, `UNIQUE(parent_id)` | **2**: `show`, `update` (upsert) — POST/DELETE vô nghĩa |
| **D.** n–n có thuộc tính | Bảng nối mang cột nghiệp vụ | 6 — xử lý **y hệt A**, **cấm** `sync()` |
| **E.** Danh mục dùng chung | `organization_id = NULL` | 1: `index` (CRUD quản trị ở module hệ thống) |

Bộ 11 action ở mục 3 áp cho **bảng chính của module**; bảng con dùng bộ rút gọn trên (không `stats/export/import/changeStatus` — dữ liệu con đã đi kèm export của bản chính, và import file phẳng không nhận mảng lồng nhau).

**Sáu điều bắt buộc, vi phạm là mất dữ liệu chứ không phải lỗi style:**

1. **Bảng cha bắt buộc `SoftDeletes`** khi bảng con dùng `onDelete('cascade')` — thiếu nó thì xoá cha làm MySQL xoá cứng toàn bộ dòng con, bỏ qua SoftDeletes của chúng và để lại tệp media mồ côi.
2. **`$touches = ['parent']` ở mọi model con.** Đây là cơ chế duy nhất bắt được xung đột giữa màn hình sub-resource và màn hình gộp. `whereNotIn(...)->delete()` và `bulkDestroy` chạy qua Query Builder nên **không** nổ `$touches` → phải `$parent->touch()` tay.
3. **Thứ tự media không được đổi:** snapshot `getMedia()` → commit → `addMedia()` → mới xoá tệp cũ. Snapshot chụp sau khi upload thì tệp vừa tải lên bị xoá ngay; xoá tệp trong transaction thì rollback không cứu được.
4. **`UNIQUE` + `SoftDeletes`**: dòng đã xoá mềm vẫn chiếm chỗ trong unique index (đưa `deleted_at` vào unique không cứu được — MySQL coi mọi `NULL` là khác nhau). Bảng có unique phải `withTrashed()` → `restore()` thay vì `create()`. Bảng **không** có unique thì **không** thêm nhánh này.
5. **Optimistic lock** cho bảng chính có form trọn gói: Resource trả thêm `lock_version` (ISO8601, tách khỏi `updated_at` hiển thị `H:i:s d/m/Y`), service đọc lại kèm `lockForUpdate()` **bên trong** transaction rồi so bằng `->timestamp`.
6. **Danh sách con gửi dưới dạng chuỗi JSON** (`educations_json`), không phải mảng lồng FormData — `max_input_vars` cắt phần đuôi payload **im lặng**, và mảng lồng không phân biệt được `"[]"` (xoá hết) với vắng mặt (không quản lý).

**`save-full` — endpoint gộp bản chính + toàn bộ danh sách con:**

- Bắt buộc có cho bảng chính nào có màn hình form trọn gói; dùng chung permission `.store`/`.update`, không tạo permission riêng.
- **Không tự ghi bản chính** — gọi lại `Service::update()` của resource bản chính, để optimistic lock chỉ nằm đúng một chỗ.
- **Cấm gọi từ màn hình có phân trang**: `whereNotIn` xoá mềm sạch phần chưa load và vẫn trả 200. Backend không chặn được điều này.
- Route tĩnh (`/save-full`, `/bulk-delete`, `/stats`) phải khai báo **trước** `/{id}`, và `{id}` có `->whereNumber()` — đặt sau thì Laravel nuốt segment vào model binding và trả 404 khó hiểu.

## 5. API Response & Resource

**Trait `App\Modules\Core\Traits\RespondsWithJson`** — dùng qua Controller base:

| Method | Dùng cho |
|---|---|
| `$this->success($data, $message)` | stats, destroy, bulk, import |
| `$this->successResource(JsonResource, $message)` | show, store, update, changeStatus |
| `$this->successCollection(ResourceCollection, $message)` | index, tree |
| `$this->error($message, $code, $errors, $errorCode)` | lỗi chung |
| `$this->unauthorized()` / `forbidden()` / `notFound()` / `conflict()` | lỗi HTTP chuẩn |

Luôn dùng Resource để trả dữ liệu. Định dạng thời gian trong Resource:
- Chỉ ngày: `$this->birthday->format('d/m/Y')`
- Có giờ: `$this->created_at->format('H:i:s d/m/Y')`

## 6. Export & Import

**Export:** Xuất đầy đủ các trường như index (Resource), bao gồm quan hệ, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`.
- **Xuất kèm các quan hệ xung quanh bảng chính:**
  - Quan hệ **1-1 / N-1** (belongsTo, danh mục): xuất **tên** (hoặc mã định danh) của bản ghi liên quan — vd hộ ("CCCD chủ hộ"), tổ dân phố ("Tổ dân phố"). Không xuất `*_id` thô.
  - Quan hệ **1-N / N-N** (hasMany, belongsToMany): **liệt kê** các bản ghi con thành 1 ô, **ngăn cách bởi `; ` (dấu chấm phẩy + space)** — vd cột "Thân nhân" = `Con 1; Con 2`, "Loại đối tượng" = `Thương binh; Bệnh binh`. Với quan hệ N-N có thuộc tính pivot, kèm nhãn trong ngoặc: `Tên (Quan hệ)`.
  - Các cột liệt kê 1-N/N-N chỉ mang tính **tham chiếu để đọc** — **import bỏ qua** (không parse ngược); đặt tên header khác với cột nhập liệu để tránh nhầm.

**Import:**
- FormRequest validate: `required|file|mimes:xlsx,xls,csv|max:10240`.
- **Đầy đủ trường:** import phải nhận **mọi trường** như Export/StoreRequest — **chỉ bỏ qua trường dạng mảng lồng nhau** (vd `classifications`, `dependents`) vì không phù hợp file phẳng. Không tự cắt bớt cột "cho gọn"; thiếu cột nào cán bộ mất đường nhập cột đó.
  - **Ràng buộc tối thiểu:** chỉ bắt buộc **vài trường chính** thực sự cần (vd `tên`, hoặc `trạng thái`); mọi trường khác `nullable`. Không dồn nhiều rule bắt buộc khiến cán bộ khó nhập hàng loạt — dữ liệu thiếu bổ sung sau qua CRUD, đừng chặn cả dòng vì thiếu trường phụ.
  - **Liên kết danh mục quan hệ 1-1 bằng TÊN:** cho phép nhập **tên** (hoặc mã) của danh mục liên quan (vd tổ dân phố, hộ), `model()` tra ngược về `*_id`; không khớp thì để trống, **không chặn dòng**. Không bắt cán bộ nhập `*_id` thô.
  - Rule validate mỗi cột mirror StoreRequest (required cho cột bắt buộc tối thiểu, `nullable` + default cho cột còn lại).
  - Enum (giới tính/trạng thái…): chấp nhận cả value gốc lẫn nhãn tiếng Việt (chuẩn hóa trong `prepareForValidation`) để round-trip Export→Import.
- **Tổng hợp lỗi ra Excel (bắt buộc):** import nhiều dòng phải **trả về file Excel tổng hợp lỗi** để cán bộ tải về đối chiếu — không bắt đọc JSON thủ công. Đã chuẩn hóa sẵn ở base `Controller::importResult()`: khi có lỗi, response `success` kèm `data.error_file = { name, mime, base64 }` (null khi 0 lỗi) — 1 file `.xlsx` cột **STT | Hàng số | Cột | Lỗi | Giá trị**, mỗi lỗi 1 dòng. Controller import chỉ cần gọi `$this->importResult($failures, '<thực thể>', XxxImport::FIELD_LABELS)` (truyền `FIELD_LABELS` để cột "Cột" hiện nhãn tiếng Việt thay vì key). File sinh từ `App\Modules\Core\Exports\ImportErrorsExport` — **không tự implement lại**. Dùng base64 trong cùng response (không tách endpoint) để import chỉ chạy 1 lần, tránh import trùng dòng hợp lệ.
- Import class khai báo:
  - `FIELD_LABELS` (map `field_key => 'Nhãn tiếng Việt'`) — **đủ mọi cột**; header file dịch ngược về key qua trait `TranslatesExcelHeadings`.
  - `TEMPLATE_LABELS = self::FIELD_LABELS` (file mẫu hiện đủ cột).
  - `TEMPLATE_EXAMPLES` (map `field_key => 'giá trị ví dụ'`, để trống nếu không cần).
  - `REQUIRED_KEYS` (mảng `field_key` bắt buộc) — **phải khớp** các field `required` trong `rules()`.
  - `templateNotes()` (static, trả `[field_key => 'ghi chú']`) — **bắt buộc cho MỌI cột enum/boolean/giá trị giới hạn**: liệt kê **đầy đủ** giá trị hợp lệ để cán bộ có cơ sở điền (vd giới tính: `male (Nam), female (Nữ), other (Khác)`). Dùng helper `NormalizesImportValues::enumHint(XxxEnum::cases())` để sinh chuỗi từ enum (không hardcode, tránh lệch khi enum đổi); boolean/giá trị đặc biệt ghi literal.
  - `templateOptions()` (static, trả `[field_key => [giá trị thô]]`) — giá trị thô của enum để dựng **dropdown** chọn nhanh (vd `GenderEnum::values()`).
- **Đánh dấu cột bắt buộc:** file mẫu gắn **dấu `*`** ở cuối header cột bắt buộc; cột không bắt buộc để **trần** (không thêm gì). `ImportTemplateExport` tự gắn `*` từ `REQUIRED_KEYS`; trait `TranslatesExcelHeadings` tự **bỏ dấu `*`** khi upload nên file mẫu vẫn import lại được.
- **Gợi ý giá trị enum (hiển thị rõ, không ẩn):** `ImportTemplateExport` nhận `columnNotes` (tham số 4 = `templateNotes()`) và `columnOptions` (tham số 5 = `templateOptions()`):
  - Cột enum có CSV giá trị ≤ 255 ký tự → tạo **dropdown** (data validation LIST) trên ô data + **prompt** hiện khi bấm ô (liệt kê `value (Nhãn)`). `showErrorMessage=false` để không chặn — import vẫn nhận cả nhãn tiếng Việt.
  - **Mọi** cột enum còn được gắn thêm **comment ở ô header** (liệt kê đầy đủ giá trị): cột có dropdown để comment hover (bổ trợ, prompt đã hiện sẵn); cột enum dài (> 255, không dựng được dropdown) để comment **visible** (hiện sẵn).
  - Cán bộ mở file phải THẤY ngay giá trị hợp lệ (dropdown + prompt + comment), không chỉ dựa comment ẩn.

**Bắt buộc: mọi resource có `import` phải có kèm endpoint tải file mẫu** — không để cán bộ tự đoán cột file:
```php
// Route — đặt ngay sau route import
Route::get('/import-template', [XxxController::class, 'importTemplate'])
    ->middleware('permission:xxx.import,web'); // dùng chung permission .import, không tạo permission riêng

// Controller — truyền REQUIRED_KEYS (dấu *) + templateNotes() (comment giá trị enum)
public function importTemplate()
{
    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Modules\Core\Exports\ImportTemplateExport(
            XxxImport::TEMPLATE_LABELS,
            XxxImport::TEMPLATE_EXAMPLES,
            XxxImport::REQUIRED_KEYS,
            XxxImport::templateNotes(),   // bỏ 2 dòng này nếu module không có cột enum
            XxxImport::templateOptions(),
        ),
        'import-xxx-template.xlsx'
    );
}
```
Không tự implement lại việc sinh file Excel mẫu — luôn tái dùng `App\Modules\Core\Exports\ImportTemplateExport` (đã style sẵn: row 1 header — cột bắt buộc có dấu `*` + dropdown/comment giá trị enum, row 2 ví dụ in nghiêng xám để cán bộ biết xóa trước khi nhập).

> PHPDoc Scribe cho export/import xem mục 7.

## 7. Scribe (API Documentation)

> Toàn bộ quy tắc Scribe tập trung ở đây. Sau bất kỳ thay đổi API nào: `sail artisan scribe:generate`.  
> Config: `config/scribe.php` giữ `auth.enabled=true`, `auth.default=true`.

**PHPDoc Controller class:**
```php
/**
 * @group Core - User
 * Quản lý người dùng hệ thống.
 */
```

**PHPDoc từng action — bắt buộc đủ các tag:**

| Tag | Khi nào |
|---|---|
| `@queryParam` | Tham số query: search, status, sort_by, sort_order, limit, from_date, to_date |
| `@urlParam` | Path param (`{id}`, `{user}`): ghi required/optional + example |
| `@bodyParam` | Request body POST/PUT/PATCH: tên, kiểu, required/optional, example |
| `@header X-Organization-Id required ...` | Mọi endpoint yêu cầu tenant |
| `@unauthenticated` | Mọi endpoint public (tránh Scribe hiển thị sai badge auth) |
| `@response` / `@responseField` | Khi cần mô tả response mẫu cụ thể |

Action **export** — ghi trong PHPDoc: `"Xuất ra các trường: id, [trường chính], status, created_by, updated_by, created_at, updated_at"`.  
Action **import** — ghi: `"Cột bắt buộc: [...]. Cột không bắt buộc: [..., mặc định ...]"`.

**FormRequest:**
- Phải có `bodyParameters()` (query-only request trả `[]`).
- Phải có `messages()` tiếng Việt bao phủ mọi rule đang dùng (required, string, integer, array, file, mimes, max, min, date, exists, unique, in, boolean...).
- Phải có `attributes()` map tên trường tiếng Việt — không để rỗng nếu `rules()` có field.
- FilterRequest nên có `queryParameters()` mô tả search/status/from_date/to_date/sort_by/sort_order/limit.

**Factory:**
- Model dùng `HasFactory` phải có factory đúng namespace để Scribe không báo lỗi `factoryCreate/factoryMake`.
- Namespace: `Database\Factories\Modules\{Module}\Models\{Model}Factory`.

**Kiểm tra sau generate:** `.scribe/endpoints/*.yaml` có `authenticated: false` với API public.

**Tham khảo style:** `app/Modules/Meeting/Controllers/` hoặc `app/Modules/Core/` controllers.

## 8. Phân quyền & LogActivity

**Permission** (`database/seeders/PermissionSeeder.php`):
- Định dạng: `{resource}.{action}` — resource trùng prefix API route (vd: `meeting-rooms`, `task-assignment-items`).
- Guard: `web` cho cả web và API Sanctum.
- Khi thêm resource/action mới: cập nhật mảng `PERMISSIONS` trong `PermissionSeeder` rồi chạy `sail artisan db:seed --class=PermissionSeeder`.

**LogActivity** (`app/Modules/Core/Middleware/LogActivity.php`):
- Khi thêm resource/action mới: cập nhật `resourceLabel()`, `actionLabels`, `pathActions`, route params.

## 9. Public Catalog APIs

Endpoint public (dropdown/chức năng công khai) đặt ngoài nhóm `auth:sanctum`:

| Endpoint | Mô tả |
|---|---|
| `GET /api/{resource}/public` | Dữ liệu công khai đầy đủ |
| `GET /api/{resource}/public-options` | Tối giản cho dropdown: `id`, `name`, `description` |

`public-options`: chỉ select cột cần thiết, lọc `status=active`, sắp xếp ổn định (`name asc` hoặc `sort_order`).  
Dùng `App\Modules\Core\Resources\PublicOptionResource` cho dropdown.  
Thêm endpoint mới thay vì đổi format endpoint cũ (giữ backward compatibility với frontend).

## 10. Tài liệu & Thiết kế

**Cấu trúc thư mục `docs/` — xem [docs/README.md](docs/README.md) để có bản đồ đầy đủ.**

| Thư mục | Lưu gì | Khi nào cập nhật |
|---|---|---|
| `docs/guide/` | GETTING_STARTED, CONTRIBUTING, TROUBLESHOOTING | Khi quy trình/setup thay đổi |
| `docs/system/` | ARCHITECTURE, AUTH_TENANT, DOMAIN_GLOSSARY, INFRASTRUCTURE, QUAN_HE_CHA_CON (+ \_VIDU) | Khi kiến trúc/convention thay đổi |
| `docs/database/` | ERD.md, Core.md, Meeting.md, TaskAssignment.md, Scheduling.md | Khi có Migration mới |
| `docs/modules/{Module}/` | README.md, models.md, services.md, events.md | Khi thêm/sửa module |
| `docs/decisions/` | ADR-NNN-ten-quyet-dinh.md | Khi có quyết định kiến trúc quan trọng |
| `docs/api/` | Chi tiết endpoint (gồm cả sso.md) | Khi tạo/cập nhật Controller |
| `docs/answer/` | Phân tích, giải pháp, hướng dẫn chuyên sâu | Theo yêu cầu |
| `docs/changelogs/` | YYYY-MM-DD-topic-fe.md | Mỗi khi BE đổi API ảnh hưởng FE |
| `docs/superpowers/` | plans/ + specs/ cho feature lớn | Khi có feature phức tạp đa bước |

**Quy tắc khi thêm module mới:**
- Copy `docs/modules/_TEMPLATE.md` → `docs/modules/{TênModule}/README.md` và điền đầy đủ.
- Thêm schema mới vào `docs/database/{Module}.md`.
- Nếu có quyết định kiến trúc quan trọng → tạo ADR từ `docs/decisions/_TEMPLATE.md`.

**Tên file tài liệu sinh ra** (`docs/answer/`, `docs/spec/`) phải có hậu tố timestamp `_HHmmss_DDMMYYYY` trước `.md`:
- Ví dụ: `meeting-flow-analysis_143022_28062026.md`, `cong-van-api_091500_01072026.md`

**Nội dung mọi file tài liệu** phải có header ngay sau tiêu đề chính:
```markdown
# Tên Tài Liệu

> Ngày tạo: HH:mm:ss DD/MM/YYYY  
> Cập nhật lần cuối: HH:mm:ss DD/MM/YYYY
```
- `Ngày tạo` giữ nguyên sau lần đầu. `Cập nhật lần cuối` cập nhật mỗi lần sửa nội dung.

## 11. Checklist review PR

**Controller & Service:**
- [ ] Controller không chứa nghiệp vụ phức tạp — chỉ validate → gọi service → trả response.
- [ ] Mỗi action có method tương ứng trong Service.
- [ ] Luồng ghi nhiều bước đã bọc `DB::transaction()`; không lạm dụng cho read/single-write.
- [ ] Luồng có thao tác file trong transaction có cleanup khi lỗi.
- [ ] Upload media: module cũ qua `Core\Services\MediaService`; module mới có quan hệ cha — con theo `QUAN_HE_CHA_CON.md` — `addMedia()` **sau** commit, snapshot **trước** upload, tệp nhạy cảm trên disk `private`.
- [ ] Resource thuộc tenant scope đúng `organization_id`, không cho cross-tenant.
- [ ] Response format và HTTP status code đúng chuẩn (`RespondsWithJson`).
- [ ] Có action `import` thì có kèm `import-template` (dùng `ImportTemplateExport`, permission dùng chung `.import`).
- [ ] Import nhận đủ trường như Export/StoreRequest (chỉ bỏ mảng lồng nhau); `REQUIRED_KEYS` khớp field `required` trong `rules()`; file mẫu gắn dấu `*` cột bắt buộc, cột không bắt buộc để trần.
- [ ] Mọi cột enum/boolean có `templateNotes()` (đủ giá trị, dùng `enumHint()`) + `templateOptions()` (giá trị thô), truyền vào `ImportTemplateExport` → file mẫu hiện dropdown/prompt (hoặc comment visible nếu enum dài), KHÔNG dùng comment ẩn.
- [ ] Module có ≥1 Enum dùng cho FE dropdown → có `{module}-enums` endpoint (`EnumController`, xem mục 2), không gắn permission riêng.

**Quan hệ cha — con (chỉ module mới, xem mục 4):**
- [ ] Đã xác định dạng A/B/C/D/E và copy đúng khuôn ở `QUAN_HE_CHA_CON_VIDU.md`.
- [ ] Bảng cha có `SoftDeletes` (bắt buộc khi con `onDelete('cascade')`); bảng con có `organization_id`, `created_by/updated_by`, `SoftDeletes`, index `(organization_id, parent_id)`.
- [ ] Model con có `$touches = ['parent']`; `parent_id` và `organization_id` **không** nằm trong `$fillable`.
- [ ] Media: snapshot trước upload, upload sau commit, xoá tệp cũ sau cùng; collection `singleFile()` **không** gọi trong transaction.
- [ ] Bảng có unique + SoftDeletes → service có nhánh `withTrashed()` → `restore()`; bảng không có unique thì **không** thêm nhánh này.
- [ ] `whereNotIn(...)->delete()` và `bulkDestroy` có `$parent->touch()` tay.
- [ ] Có `save-full` thì nó gọi lại `Service::update()` của bản chính, không tự ghi; route tĩnh khai báo trước `/{id}`; `{id}` có `whereNumber()`.
- [ ] Resource dòng con trả `parent_lock_version` và service đã eager load quan hệ cha (thiếu thì key biến mất khỏi response).
- [ ] Khoá ngoại trỏ danh mục dùng `Rule::exists` có scope tenant + `whereNull('deleted_at')`.
- [ ] Có đủ test bắt buộc (4 ca đính kèm, 3 ca `save-full`, 1 ca restore, 2 ca đa tổ chức) — xem `QUAN_HE_CHA_CON.md` §24.

**Event-Driven:**
- [ ] Service không gọi trực tiếp Notification/Mail/Broadcast — chỉ `event()`.
- [ ] Event ghi DB dùng `ShouldDispatchAfterCommit`.
- [ ] Job có `$tries`, `$backoff`, nhận `organization_id` qua constructor.
- [ ] Job/Listener nặng vào đúng queue tier (không dồn vào `default`).
- [ ] Notification dùng Resolver + Enum, không hardcode nội dung.
- [ ] Schedule command đăng ký ở `routes/console.php`, có `withoutOverlapping`.
- [ ] Broadcast Event chỉ chứa ID, channel authorization qua Policy.
- [ ] Observer chỉ xử lý data integrity (kể cả chuẩn bị/ghi reminder rows), không **gửi** Notification.
- [ ] Cross-tenant Job/Command có `withoutGlobalScope('organization')` khi loop toàn bộ tenant.

---

# Event-Driven Architecture — Danatec

> Áp dụng đồng bộ cho toàn bộ module (Modular Monolith + DDD).  
> Mục tiêu: AI/Dev biết **chọn đúng primitive** (Event, Listener, Observer, Job, Notification, Schedule) cho từng tình huống, tránh lẫn lộn trách nhiệm.

## 1. Cây quyết định nhanh

```
Có hành động nghiệp vụ xảy ra (tạo/sửa/xóa/chuyển trạng thái)?
│
├─ Cần side-effect KHÔNG đồng bộ với business logic chính (log, thông báo, sync, export)?
│   └─ YES → fire EVENT từ Service → LISTENER xử lý
│
├─ Side-effect phải chạy ở MỌI đường ghi model (API + Seeder + Console + Tinker),
│  không chỉ tại một mốc nghiệp vụ cụ thể?
│   └─ YES → dùng OBSERVER (model lifecycle: creating/updating/deleting)
│
├─ Việc cần làm tốn thời gian (gọi API ngoài, export file, gửi nhiều noti, OCR, AI)?
│   └─ YES → dispatch JOB (vào QUEUE phù hợp)
│
├─ Cần báo cho user qua nhiều channel (Zalo ZNS, FCM, Email, SMS, in-app)?
│   └─ YES → NOTIFICATION (Notification class) — KHÔNG gọi NotificationService từ Service
│
├─ Việc lặp lại theo thời gian, không do user trigger?
│   └─ YES → SCHEDULE (Console Command + routes/console.php)
│
└─ Cần realtime UI update (nhiều client cùng xem)?
    └─ YES → BROADCAST qua Reverb (channel private/presence)
```

**Nguyên tắc cốt lõi:** Service KHÔNG BAO GIỜ gọi trực tiếp NotificationService / Mail / Broadcast.  
Service chỉ `event(new XxxEvent($model))`. Mọi side-effect nằm ở Listener.

**Observer có được fire Event không?**
- ✅ ĐƯỢC: khi một chuyển trạng thái cần notify NHƯNG có thể xảy ra ngoài Service
  (Seeder / Console / Tinker / API khác) → Observer fire `event(new XxxEvent($model))`,
  Listener lo phần gửi. Observer KHÔNG tự gửi Notification.
- ❌ KHÔNG cần Observer: khi trạng thái chỉ đổi qua đúng một Service → fire event
  thẳng trong Service (kiểm soát rõ thời điểm, dễ đọc).

> Chốt: chọn nơi fire theo "có bao nhiêu đường ghi vào model", không theo "có phải Service hay không".
> 1 đường ghi duy nhất → Service. Nhiều đường ghi, đều phải notify → Observer fire event.

## 2. Event & Listener

**Dùng Event khi:**
- Hành động nghiệp vụ có ≥1 side-effect không thuộc logic chính.
- Cần mở rộng không sửa Service (Open/Closed Principle).
- Cần nhiều Listener độc lập (gửi Noti + ghi Log + đồng bộ n8n).

**Không dùng Event khi:** logic là phần bắt buộc, đồng bộ, không thể thiếu của transaction → gọi thẳng trong Service.

**Đặt tên:**
- Event: PascalCase, động từ quá khứ + domain object. Đồng nhất ngôn ngữ trong module (không trộn Việt/Anh).
- Listener: `SendXxxNotifications` (vd `SendMeetingPublishedNotifications`) — 1 Listener = 1 trách nhiệm.

**Bắt buộc:** Dùng `ShouldDispatchAfterCommit` cho Event ghi DB rồi fire Notification/Broadcast (tránh race condition khi transaction chưa commit).

## 3. Observer vs Event

| | Observer | Event trong Service |
|---|---|---|
| Trigger | Eloquent lifecycle (creating/created/updating/deleted) | Hành động nghiệp vụ tường minh |
| Dùng khi | Cần áp dụng MỌI NƠI model được tạo/sửa (kể cả Tinker, Seeder, API khác) | Cần kiểm soát rõ KHI NÀO fire |
| Rủi ro | Dễ fire ngoài ý muốn khi seed/import → cẩn thận `withoutEvents()` | Phải nhớ gọi đúng chỗ trong Service |
| Ví dụ Danatec | Tự gán `organization_id`, generate `slug`, reindex `VietnameseSort`, `ReminderScheduler->scheduleFor()` | `MeetingPublished`, `TaskAssigned`, `ScheduleUpdated` |

**Quy tắc:** Observer = data integrity (mức model). Event = business meaning (mức nghiệp vụ).  
Không dùng Observer để **gửi** Notification (khó trace, khó test).

> Lưu ý vùng xám: **ghi/huỷ bản ghi lịch nhắc** (vd `ReminderScheduler->scheduleFor()` tạo/xóa
> row bảng `reminders`) tính là **data-integrity → Observer OK**. Chỉ hành vi **gửi** (mail/SMS/Zalo/FCM/
> broadcast) mới bắt buộc qua Event → Listener. Chuẩn bị dữ liệu ≠ gửi.

## 4. Job & Queue

**Dispatch Job khi:** gọi API ngoài (Zalo, Firebase, SMS, Gemini/OCR), export file lớn, import hàng loạt, bất kỳ việc có thể fail/timeout mà không nên block response.

**Phân tầng Queue — không dồn mọi thứ vào `default`:**

| Queue | Dùng cho | Ghi chú Horizon |
|---|---|---|
| `urgent` | OTP, cảnh báo an toàn | Supervisor riêng, KHÔNG balance (luôn có worker rảnh) |
| `notifications` | Zalo ZNS/OA, FCM, SMS, Email | balance, maxProcesses cao |
| `exports` | Export Word/Excel/PDF | timeout dài |
| `ai` | Gemini API, OCR | timeout dài, retry thấp (tránh tốn token) |
| `sync` | n8n, webhook ngoài | retry trung bình, backoff |
| `default` | Việc nhẹ, không phân loại | — |

**Bắt buộc:**
- Job implement `ShouldQueue`, khai báo `$tries` và `$backoff` rõ ràng (không để default vô hạn retry).
- Job liên quan tenant nhận `organization_id` qua constructor — không dùng `auth()` trong background (không có session).
- Job thất bại → log `failed_jobs`; có Listener nghe `JobFailed` để cảnh báo qua kênh nội bộ (Telegram/Zalo Danatec).

## 5. Notification

- Chỉ gọi `Notification::send()` hoặc `$model->notify()` — KHÔNG inject `NotificationService` vào business Service.
- Mỗi loại thông báo có `XxxNotificationTypeEnum` + Resolver class riêng (quyết định nội dung/template).
- Custom Channel (`ZaloNotificationChannel`, `FcmChannel`) chỉ lo việc GỬI, không lo nội dung.
- `via()` trả channel theo cấu hình tenant (đọc từ config tổ chức, không hardcode).

## 6. Schedule (Cron)

**Dùng khi:** nhắc hạn hồ sơ, báo cáo định kỳ, dọn file tạm, đồng bộ ngoài, nhắc lịch công tác.

- Command riêng từng module: `app/Modules/{Module}/Console/Commands/`.
- Đăng ký trong `routes/console.php` (Laravel 11+) — không sửa `Kernel.php`.
- Command nặng: `->withoutOverlapping()` + dispatch Job bên trong (Command chỉ "kích hoạt", Job làm việc thật).
- Cross-tenant: loop qua từng `organization_id`, dùng `withoutGlobalScope`.
- Multi-server: thêm `->onOneServer()`.

## 7. Horizon

- Mỗi queue tier có 1 supervisor riêng trong `config/horizon.php` — không dùng 1 supervisor cho tất cả.
- Production: `balance: auto`, `maxProcesses` theo tải thực tế (`danatecsvr01`).
- Bật `horizon:snapshot` qua Schedule (mỗi 5 phút) để có metrics.

## 8. Redis

- Driver: `predis/predis` (không cài phpredis extension).
- 3 connection/database Redis riêng biệt (tránh xung đột key, dễ flush riêng từng loại):
    1. Queue — `REDIS_QUEUE_CONNECTION`
    2. Cache — `REDIS_CACHE_CONNECTION`
    3. Broadcast/Reverb — `REDIS_BROADCAST_CONNECTION`
- Lock (vd refresh token Zalo OA) dùng `Cache::lock()` — không tự implement lock tay.

## 9. Reverb & Broadcast

**Broadcast khi:** UI cần update realtime nhiều client (phòng họp, xếp hàng QR, presence "đang online").  
Không broadcast cho mọi Event — chỉ khi có nhu cầu hiển thị tức thời trên UI.

**Channel convention:**
- `private-org.{organization_id}.user.{user_id}` — thông báo cá nhân.
- `presence-org.{organization_id}.meeting.{meeting_id}` — phòng họp/presence.

**Quy tắc:**
- Ưu tiên `ShouldBroadcastAfterCommit` (nếu trong transaction).
- Authorization qua `routes/channels.php` dùng Policy — không check tay.
- Payload chỉ gồm `id` + `type`, client tự gọi API lấy full data (tránh leak dữ liệu nhạy cảm qua WebSocket).

---

*Nạp cùng quy ước TenantModel / Policy / Enum / RespondsWithJson để AI áp dụng nhất quán khi sinh code cho module mới.*
