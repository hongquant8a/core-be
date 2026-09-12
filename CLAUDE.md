# CLAUDE.md

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

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
| Đổi trạng thái đơn (không có nghiệp vụ riêng) | `PATCH` | `/{id}/status` |
| Thao tác nghiệp vụ có quyền riêng | `PATCH` | `/{id}/{ten-nghiep-vu}` — vd `/mark-done`, `/approve`, `/reject`, `/pause` |
| Sắp xếp lại | `PATCH` | `/reorder` |

> Laravel tự parse JSON body cho DELETE — không dùng POST thay thế.

**Khi nào `/status`, khi nào endpoint riêng.** `/{id}/status` dành cho đổi trạng thái
**không** có nghiệp vụ riêng — bật/tắt `active`–`inactive`, chuyển qua lại giữa hai
trạng thái tiến độ. Thao tác có **quyền riêng** thì phải có **endpoint riêng đặt theo
tên nghiệp vụ** (kebab-case), vì gộp vào `/status` thì không gác được quyền riêng cho
từng thao tác — xem mục 12.1. Đây là lý do module TaskAssignment tách `/mark-done`,
`/reject`, `/reopen`, `/pause`, `/cancel` ngày 11/09/2026.

## 4. Controller & Service Layer

**Controller** chỉ làm: nhận request → validate (FormRequest) → gọi Service → trả response chuẩn.  
Không đặt query phức tạp, sync quan hệ, xử lý trạng thái, import/export trong Controller.

**Service:**
- Namespace: `App\Modules\{Module}\Services`, tên class: `{Resource}Service` (vd: `MeetingService`, `TaskAssignmentItemService`).
- Giữ bộ method chuẩn tương ứng các action ở mục 3.
- Dùng `DB::transaction()` khi ghi nhiều bước có phụ thuộc. Không dùng transaction cho read hoặc single-write đơn lẻ.
- Nếu transaction có thao tác file: `try/catch` cleanup file khi lỗi (tránh lệch DB vs storage).
- Mọi upload/xóa media đi qua `App\Modules\Core\Services\MediaService` — không gọi `addMedia()` hay `Storage::put/delete` trực tiếp.
- **Service không bao giờ gọi trực tiếp Notification/Mail/Broadcast — chỉ `event(new XxxEvent($model))`.**  (Chi tiết xem phần EDA.)

**Tenant (đa tổ chức):**
- Resource thuộc tổ chức phải có `organization_id`; mọi query scope theo tổ chức hiện tại (middleware `set.permissions.team` — header `X-Organization-Id`).
- Thao tác theo ID (`show`, `update`, `destroy`, `changeStatus`) và bulk phải chặn cross-tenant.
- `store`/`import` gán `organization_id` từ ngữ cảnh hiện tại, không nhận từ client.

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

**Import:**
- FormRequest validate: `required|file|mimes:xlsx,xls,csv|max:10240`.
- Cột file khớp chuẩn Export; trường bắt buộc = required trong StoreRequest, trường không bắt buộc có default.

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
| `docs/system/` | ARCHITECTURE, AUTH_TENANT, DOMAIN_GLOSSARY, INFRASTRUCTURE | Khi kiến trúc/convention thay đổi |
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
- [ ] Upload media đi qua `Core\Services\MediaService`.
- [ ] Resource thuộc tenant scope đúng `organization_id`, không cho cross-tenant.
- [ ] Response format và HTTP status code đúng chuẩn (`RespondsWithJson`).

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


## 12. Thao tác nghiệp vụ, phân quyền & trạng thái

> Rút từ đợt rà soát phân hệ Quản lý công việc 10–11/09/2026. Mỗi quy tắc dưới đây
> đến từ một lỗ hổng có thật, không phải nguyên tắc lý thuyết.

### 12.1. Một thao tác nghiệp vụ = một quyền + một route + một policy method

Thao tác có **người quyết định khác**, **điều kiện tiền đề khác**, hoặc **hệ quả
khác** thì là nghiệp vụ riêng. Phải có đủ ba thứ, không dùng ké:

```
permission  my-assigned-tasks.reject
route       PATCH /{id}/reject  →  ->can('reject', 'item')
policy      TaskAssignmentItemPolicy::reject()
```

**Phép thử trước khi gộp:** tắt quyền đó trên màn Vai trò — có **đúng một** thao tác
biến mất khỏi UI và trả 403 không? Không đổi gì nghĩa là quyền chết.

| Lỗi | Đúng |
|---|---|
| `can('a') \|\| can('b') \|\| can('c')` trong một policy method | Mỗi quyền một method |
| Policy đọc `request(...)` để đoán người dùng định làm gì | Luật nằm trong chính tên method |
| Nhân bản điều kiện "quản lý được vượt cấp" ở từng method | Một quyền bao trùm tường minh, kiểm ở `before()` |

*Đã xảy ra:* `reject`/`reopen`/`pause`/`cancel` dùng chung `changeStatus` → `pause`
và `cancel` thành **quyền chết** (policy OR ba quyền, có một là làm được cả ba);
`reject` chỉ đòi "người liên quan" nên **người thực hiện tự trả lại báo cáo của
mình được**.

Quyền vẫn cấp **qua vai trò**, không cấp thẳng cho user. Cờ kiểu `is_representative`
là gợi ý UI, không phải phân cấp quyền.

### 12.2. Không có cửa sau

Khi một trạng thái đã có thao tác riêng, mọi endpoint chung phải **từ chối** giá trị
đó, trả 422 kèm thông báo tiếng Việt **chỉ đúng nút cần bấm**:

```php
'processing_status' => ['sometimes', 'in:todo,in_progress'],
// messages()
'processing_status.in' => 'Trạng thái này có thao tác riêng: hoàn thành dùng Xác nhận hoàn thành, tạm dừng dùng Tạm dừng, huỷ dùng Huỷ công việc.',
```

**Phép thử:** với mỗi thao tác có điều kiện, hỏi *còn đường nào khác đạt cùng kết quả
trong DB mà bỏ qua điều kiện không?* Rà `update`, `changeStatus`, `bulkUpdateStatus`,
`import`.

*Đã xảy ra:* `PUT /task-assignment-items/{id}` nhận cả `done` — chỉ cần quyền **sửa**
là duyệt được việc, bỏ qua điều kiện chờ duyệt, bỏ qua ghi `approved_by`/`completed_at`,
bỏ qua sự kiện thông báo.

### 12.3. Trạng thái kết thúc phải khoá đủ ba lớp

| Lớp | Việc phải làm |
|---|---|
| Backend | Policy chặn `update`, `updateProgress`, **và `delete`** |
| Web | Ẩn nút **và** chặn chọn dòng ở bảng |
| Miniapp | Ẩn nút — không để bấm xong mới báo lỗi |

*Đã xảy ra:* đơn thư hoàn thành đã khoá sửa nhưng **quên khoá xoá**.

### 12.4. Thao tác hàng loạt dùng lại đúng policy của thao tác đơn

1. Service **gọi lại policy đơn cho từng dòng**, không viết lại điều kiện.
2. **Toàn có hoặc toàn không** — thông báo nêu số dòng vướng và cách xử lý.
3. Ghi bằng **Eloquent từng dòng**, không `->update()` / `->delete()` hàng loạt.

```php
private function pullAuthorized(array $ids, string $ability, string $refusal)
{
    $items = Model::whereIn('id', $ids)->with('users')->get();
    [$allowed, $denied] = $items->partition(fn ($i) => (bool) auth()->user()?->can($ability, $i));

    if ($denied->isNotEmpty()) {
        throw new \RuntimeException(sprintf(
            '%s — %d/%d bản ghi được chọn không đạt điều kiện. Bỏ chọn những dòng đó rồi thử lại.',
            $refusal, $denied->count(), count($ids)));
    }

    return $allowed;
}

$allowed->each(fn ($item) => $item->update($data)); // từng dòng → observer chạy
```

Endpoint bulk phục vụ nhiều thao tác thì **ánh xạ trạng thái → quyền**:

```php
[$ability, $refusal] = match ($status) {
    Status::Paused->value    => ['pause', '...'],
    Status::Cancelled->value => ['cancel', '...'],
    default                  => ['changeStatus', '...'],
};
```

*Vì sao:* điều kiện nằm ở Service sẽ **trôi khỏi** policy — sửa policy mà quên service
là thủng. Mass `update()` **không kích hoạt observer** → lịch nhắc (`ReminderScheduler`)
không được ghi lại, để lại nhắc mồ côi.

### 12.5. Đổi hợp đồng API là đổi cả ba repo, trong cùng một đợt

Sửa `core-fe` **và** `core-miniapp` cùng đợt, kèm `docs/changelogs/YYYY-MM-DD-topic-fe.md`
có **bảng thao tác → endpoint → quyền → policy**, kèm rà **tích hợp ngoài** (n8n,
script, webhook).

Chiều sửa luôn là **FE theo BE**. Nếu buộc phải nới luật BE, căn cứ phải là **cây
quyền**, không phải "siết thì màn hình gãy" — và ghi căn cứ đó vào comment của policy.

### 12.6. Kiểu cột theo dữ liệu thật

Trước khi chọn `varchar(n)`, **đo dữ liệu thật**:

```sql
SELECT MAX(CHAR_LENGTH(name)), SUM(CHAR_LENGTH(name) > 255) FROM ...;
```

Trường người dùng nhập tự do và có thể dài (tên văn bản, tên công việc, tiêu đề) dùng
`TEXT`. FormRequest vẫn phải có chốt chặn để tràn trả 422 thay vì 500:

```php
// max ĐẾM KÝ TỰ, tiếng Việt tới 3 byte/ký tự → chừa biên so với 65535 byte của TEXT
'name' => 'required|string|max:20000',
```

Kèm theo **mọi** migration: cập nhật `docs/database/{Module}.md`, kể cả khi phát hiện
tài liệu cũ đã ghi sai từ trước.

### 12.7. Xoá mềm cho dữ liệu nghiệp vụ

Bản ghi có giá trị pháp lý hoặc lịch sử (đơn thư, công việc, báo cáo, quyết định
duyệt) dùng `SoftDeletes` + cast `'deleted_at' => 'datetime'`. Xoá cứng chỉ dành cho
danh mục và dữ liệu kỹ thuật.

### 12.8. Nhãn tiếng Việt phải phủ hết action

`PermissionSeeder` sinh mô tả theo `$ACTION_LABELS[$action] ?? $action` — thiếu nhãn là
**rơi thẳng ra tên action tiếng Anh** trên màn Vai trò. Thêm action mới thì bổ sung nhãn
**trong cùng commit**, và nhãn phải đúng nghiệp vụ của module dùng nó (`reject` trong
module công việc là *Trả lại báo cáo*, không phải *Từ chối*).

**Phép thử sau khi seed:** quét toàn bộ action, liệt kê cái nào không có trong
`$ACTION_LABELS`.

### 12.9. Dữ liệu, môi trường, sao lưu

**Không chạm dữ liệu thật.** Mọi thử nghiệm **có ghi** chạy trên DB clone, trỏ `.env`
tạm rồi trả lại. *Đã xảy ra:* một request test sửa nhầm trạng thái bản ghi thật.

**Sao lưu phải kiểm chứng** bằng phục hồi vào schema nháp rồi đếm lại migration / quyền
/ bản ghi; kèm `CHECKSUM.md5` và hướng dẫn nêu rõ có cần `migrate`/`seed` hay không.
Dump cũ **cách ly sang thư mục riêng** kèm ghi chú cảnh báo — để cạnh nhau là sẽ có
ngày phục hồi nhầm.

| Chuyển dữ liệu từ hệ thống cũ | Quy tắc |
|---|---|
| Tên đăng nhập | tên + họ viết tắt + tên lót viết tắt (Đặng Hồng Quân → `quandh`); chuẩn hoá xong phải **bắt đầu bằng chữ cái**, nếu không thì lùi về tên đầy đủ |
| Trùng người | Đối chiếu tên đã chuẩn hoá với người đã có **trước khi** tạo mới |
| Rollback | Chỉ xoá bản ghi **do chính lệnh tạo ra** |
| Tệp đính kèm | Qua `Core\Services\MediaService`, **không** gọi `addMedia()` thẳng |
| Nghiệm thu | Đối chiếu **số lượng từng bảng** + **MD5 từng tệp**, không chỉ đếm tổng |

**Mã dùng một lần** (command import, bảng ánh xạ, schema tạm) **xoá sau khi xong**; giữ
lại thay đổi schema vì đó là thay đổi vĩnh viễn.

### 12.10. Kiểm chứng trước khi khẳng định

- **Không kẻ bảng khi chưa kiểm từng ô.** Bảng trông có thẩm quyền hơn câu văn, nên sai
  trong bảng gây hại hơn.
- **Phân biệt quyết định nghiệp vụ với lỗi của mình.** Chỉ đẩy sang người dùng những gì
  thật sự là lựa chọn nghiệp vụ; lỗi trong code của mình thì tự sửa.
- **Chứng minh lỗi có sẵn.** `git stash` rồi chạy lại để so trước/sau. Không có bước này
  thì hoặc đi sửa nhầm nợ cũ, hoặc bỏ qua lỗi mình vừa gây.
- **Không dùng regex nhiều dòng để sửa mã nguồn.** `(?:.*\n)*?` đã từng nuốt mất phần
  còn lại của một Policy, chỉ chừa 13 dòng. Dùng thay thế chuỗi chính xác.
- Cuối mỗi đợt, ghi việc còn treo ra **tài liệu trong repo** — hội thoại không sống qua
  phiên làm việc.

### 12.11. Checklist khi thêm một thao tác nghiệp vụ mới

- [ ] Có người quyết định / điều kiện / hệ quả riêng → tách quyền riêng, **không** dùng ké quyền sẵn có
- [ ] `PermissionSeeder`: thêm action **và** nhãn tiếng Việt trong `$ACTION_LABELS`
- [ ] Route riêng, gác `->can('<ability>', '<model>')`
- [ ] Policy method riêng — không OR nhiều quyền, không đọc `request()`
- [ ] Endpoint chung (`update`, `/status`, bulk, import) **từ chối** trạng thái này, kèm `messages()` chỉ đúng nút cần bấm
- [ ] Bulk dùng lại policy đơn, ghi từng dòng bằng Eloquent
- [ ] Trạng thái cuối: khoá `update` + `updateProgress` + **`delete`**
- [ ] `CaslAbilityConverter`: ánh xạ quyền mới sang `{action, subject}` cho FE
- [ ] `LogActivity`: cập nhật `resourceLabel()`, `actionLabels`, `pathActions`
- [ ] Web và miniapp sửa cùng đợt (xem CLAUDE.md của hai repo đó)
- [ ] `docs/changelogs/YYYY-MM-DD-topic-fe.md` có bảng thao tác → endpoint → quyền → policy
- [ ] `docs/database/{Module}.md` cập nhật nếu có migration
- [ ] Rà tích hợp ngoài (n8n, script) nếu hợp đồng API đổi
- [ ] `sail artisan scribe:generate`

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
