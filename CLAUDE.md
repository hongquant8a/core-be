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
