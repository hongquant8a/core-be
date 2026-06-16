# DATABASE DESIGN — Module Meeting

Cuộc họp nội bộ. Module đa tổ chức — tất cả bảng nghiệp vụ có `organization_id` và scope theo tenant hiện tại.

---

### Bảng danh mục (catalog)

Các bảng `meeting_types`, `meeting_locations`, `meeting_document_types`, `meeting_attendee_groups` có cùng cấu trúc cơ bản:

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, nullOnDelete |
| name | varchar(255) | No | — | |
| description | text | Yes | null | |
| status | varchar(255) | No | 'active' | active, inactive |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

Riêng `meeting_locations` có thêm: `address` (varchar, nullable), `google_maps_url` (varchar, nullable).

### `meeting_minutes_templates`
Template biên bản họp — **không scope theo organization** (dùng chung toàn hệ thống).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| name | varchar(255) | No | — | |
| description | varchar(500) | Yes | null | |
| media_id | bigint unsigned | Yes | null | FK → media.id (file template) |
| is_default | boolean | No | false | |
| status | varchar(20) | No | 'active' | active, inactive |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_settings`
Cấu hình cuộc họp — singleton 1 row / 1 organization. Lưu ảnh màn chiếu, chữ ký chủ tọa, icon QR.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | UNIQUE FK → organizations.id CASCADE |
| projector_image_media_id | bigint unsigned | Yes | null | FK → media.id |
| chairperson_signature_media_id | bigint unsigned | Yes | null | FK → media.id |
| qr_icon_media_id | bigint unsigned | Yes | null | FK → media.id |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_attendees`
Danh sách đại biểu của tổ chức (catalog cố định, không gắn với 1 cuộc họp cụ thể). Mỗi user chỉ có 1 row / 1 org.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| user_id | bigint unsigned | No | — | FK → users.id CASCADE |
| position_name | varchar(255) | Yes | null | Chức vụ |
| department_name | varchar(255) | Yes | null | Phòng ban |
| status | varchar(255) | No | 'active' | active, inactive |
| note | text | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(organization_id, user_id) `meeting_attendees_org_user_unique`.

### `meeting_attendee_group_members`
Pivot: đại biểu ↔ nhóm (n-n).

| Cột | Kiểu | Ràng buộc / Ghi chú |
|-----|------|---------------------|
| meeting_attendee_id | bigint unsigned | FK → meeting_attendees.id CASCADE |
| meeting_attendee_group_id | bigint unsigned | FK → meeting_attendee_groups.id CASCADE |

### `meetings`
Cuộc họp chính.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_type_id | bigint unsigned | Yes | null | FK → meeting_types.id nullOnDelete |
| meeting_location_id | bigint unsigned | Yes | null | FK → meeting_locations.id nullOnDelete |
| chairperson_meeting_attendee_id | bigint unsigned | Yes | null | FK → meeting_attendees.id nullOnDelete (chủ tọa) |
| operator_meeting_attendee_id | bigint unsigned | Yes | null | FK → meeting_attendees.id nullOnDelete (thư ký) |
| qr_manager_user_id | bigint unsigned | Yes | null | FK → users.id nullOnDelete (quản lý QR check-in) |
| title | varchar(255) | No | — | |
| is_public | boolean | No | false | |
| content | text | Yes | null | |
| start_time | datetime | Yes | null | |
| attendance_open_at | datetime | Yes | null | Mở cửa điểm danh |
| attendance_close_at | datetime | Yes | null | Đóng cửa điểm danh |
| end_time | datetime | Yes | null | |
| status | varchar(255) | No | 'draft' | draft, published, cancelled, completed |
| view_count | unsigned int | No | 0 | |
| published_at | datetime | Yes | null | |
| attendance_locked | boolean | No | false | Khóa điểm danh thủ công |
| checkin_token | uuid | Yes | null | UNIQUE — FE gen QR cho check-in |
| projector_image_media_id | bigint unsigned | Yes | null | Ảnh hiển thị Tab màn chiếu |
| current_meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete (highlight điều hành) |
| current_meeting_discussion_registration_id | bigint unsigned | Yes | null | FK → meeting_discussion_registrations.id nullOnDelete |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |
| deleted_at | timestamp | Yes | null | Soft delete |

INDEX: (organization_id, status), (organization_id, is_public), (start_time).

### `meeting_agendas`
Chương trình họp (có thể phân cấp cha-con).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| parent_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| content | text | No | — | |
| person_in_charge | varchar(255) | Yes | null | |
| start_time / end_time | time | Yes | null | Giờ dự kiến |
| allow_discussion_registration | boolean | No | false | |
| discussion_duration_minutes | unsigned smallint | Yes | null | Thời lượng thảo luận (phút) |
| allow_question_registration | boolean | No | false | |
| question_duration_minutes | unsigned smallint | Yes | null | Thời lượng chất vấn (phút) |
| allow_vote_registration | boolean | No | false | |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_documents`
Tài liệu đính kèm cuộc họp / chương trình.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| meeting_document_type_id | bigint unsigned | Yes | null | FK → meeting_document_types.id nullOnDelete |
| title | varchar(255) | No | — | |
| document_number | varchar(255) | Yes | null | |
| summary | text | Yes | null | |
| media_id | bigint unsigned | Yes | null | FK → media.id nullOnDelete (file chính) |
| is_public | boolean | No | false | |
| download_count | unsigned int | No | 0 | |
| sort_order | unsigned int | No | 0 | |
| created_by / updated_by | bigint unsigned | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_participants`
Đại biểu được mời vào 1 cuộc họp cụ thể (tạo khi publish meeting từ danh sách `meeting_attendees`).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_attendee_id | bigint unsigned | No | — | FK → meeting_attendees.id CASCADE |
| display_name | varchar(255) | No | — | Snapshot tên lúc invite |
| position_name | varchar(255) | Yes | null | |
| department_name | varchar(255) | Yes | null | |
| email | varchar(255) | Yes | null | |
| phone | varchar(255) | Yes | null | |
| response_status | varchar(255) | No | 'pending' | pending, accepted, declined |
| absence_reason | text | Yes | null | |
| responded_at | datetime | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(meeting_id, meeting_attendee_id).

### `meeting_guests`
Khách mời external per-meeting (không có user account).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| name | varchar(255) | No | — | Họ tên |
| position_name | varchar(255) | Yes | null | Chức vụ |
| phone | varchar(30) | No | — | |
| email | varchar(255) | No | — | |
| zalo_user_id | varchar(100) | Yes | null | ID tài khoản Zalo OA |
| organization_name | varchar(255) | Yes | null | Đơn vị (text tự nhập) |
| invited_at | datetime | Yes | null | Lần gần nhất gửi thư mời thành công |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_attendances`
Điểm danh tham dự.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| status | varchar(255) | No | 'pending' | pending, present, absent |
| checkin_method | varchar(255) | Yes | null | qr, manual |
| checked_in_at | datetime | Yes | null | |
| checked_in_by | bigint unsigned | Yes | null | FK → users.id |
| note | text | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(meeting_id, meeting_participant_id).

### `meeting_vote_topics`
Chủ đề biểu quyết. Phase derive từ `opened_at` + `closed_at` (không có cột `status`).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| title | varchar(255) | No | — | |
| vote_type | varchar(255) | No | 'agree_disagree_abstain' | Kiểu phiếu |
| ballot_mode | varchar(255) | No | 'anonymous' | anonymous, named |
| show_result_on_projector | boolean | No | false | |
| show_result_on_personal_device | boolean | No | false | |
| sort_order | unsigned int | No | 0 | |
| opened_at | datetime | Yes | null | null = chưa mở |
| closed_at | datetime | Yes | null | null = chưa đóng |
| created_by / updated_by | bigint unsigned | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_vote_responses`
Phiếu biểu quyết của từng đại biểu.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_vote_topic_id | bigint unsigned | No | — | FK → meeting_vote_topics.id CASCADE |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| option | varchar(255) | No | — | agree, disagree, abstain |
| voted_at | datetime | No | — | |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(meeting_vote_topic_id, meeting_participant_id).

### `meeting_discussion_registrations`
Đăng ký phát biểu / chất vấn.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| type | varchar(255) | No | 'discussion' | discussion, question |
| content | text | No | — | Nội dung đăng ký |
| operator_note | text | Yes | null | Ghi chú của operator/chair |
| media_id | bigint unsigned | Yes | null | FK → media.id (legacy — xem attachments) |
| status | varchar(255) | No | 'registered' | registered, speaking, done, skipped |
| is_public | boolean | No | true | Hiển thị công khai |
| completed_at | datetime | Yes | null | |
| highlighted_at | datetime | Yes | null | Thời điểm operator highlight (tính speaker countdown) |
| answer_content | text | Yes | null | Nội dung trả lời chất vấn |
| answer_attachment_id | bigint unsigned | Yes | null | FK → media.id (đính kèm câu trả lời) |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_discussion_registration_attachments`
Đính kèm cho đăng ký phát biểu (multi-file, thay thế `media_id` đơn).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_discussion_registration_id | bigint unsigned | No | — | FK → meeting_discussion_registrations.id CASCADE |
| media_id | bigint unsigned | No | — | FK → media.id CASCADE |
| file_name | varchar(255) | Yes | null | Tên hiển thị |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_personal_notes`
Ghi chú cá nhân của đại biểu trong phiên họp.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| content | longtext | No | — | |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_personal_note_attachments`
Đính kèm ghi chú cá nhân.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_personal_note_id | bigint unsigned | No | — | FK → meeting_personal_notes.id CASCADE |
| media_id | bigint unsigned | No | — | FK → media.id CASCADE |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_invitations`
Giấy mời gửi cho từng đại biểu/khách mời khi publish meeting.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_participant_id | bigint unsigned | Yes | null | FK → meeting_participants.id CASCADE (1 trong 2 phải có) |
| meeting_attendee_id | bigint unsigned | Yes | null | FK → meeting_attendees.id CASCADE (chủ tọa/thư ký trực tiếp) |
| meeting_guest_id | bigint unsigned | Yes | null | FK → meeting_guests.id nullOnDelete (mời khách mời external) |
| send_type | varchar(255) | No | 'now' | now, scheduled |
| scheduled_at | datetime | Yes | null | |
| sent_at | datetime | Yes | null | |
| status | varchar(255) | No | 'pending' | pending, sent, failed |
| error_message | text | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_reminders`
Nhắc lịch họp (manual hoặc scheduled, per-record).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| reminder_type | varchar(255) | No | 'manual' | manual, scheduled |
| moment | varchar(255) | Yes | null | before, on, after |
| offset_minutes | unsigned int | No | 0 | |
| channels | json | Yes | null | Kênh gửi (system, email, zalo, sms) |
| source | varchar(255) | No | 'PRESET' | PRESET, CUSTOM |
| remind_at | datetime | Yes | null | |
| scheduled_at | datetime | Yes | null | |
| sent_at | datetime | Yes | null | |
| message | text | Yes | null | |
| status | varchar(255) | No | 'pending' | pending, sent, failed |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_invitation_templates`
Template giấy mời họp — scope theo organization.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id nullOnDelete |
| name | varchar(255) | No | — | |
| description | varchar(500) | Yes | null | |
| media_id | bigint unsigned | Yes | null | FK → media.id |
| is_default | boolean | No | false | |
| status | varchar(20) | No | 'active' | active, inactive |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

INDEX: status, media_id, (organization_id, status).

### `meeting_views`
Log lượt xem cuộc họp / tài liệu.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_document_id | bigint unsigned | Yes | null | FK → meeting_documents.id CASCADE (null = xem meeting) |
| user_id | bigint unsigned | Yes | null | FK → users.id nullOnDelete |
| ip_address | varchar(45) | Yes | null | |
| user_agent | text | Yes | null | |
| viewed_at | datetime | No | — | |

### Sơ đồ quan hệ (Module Meeting)

```
meeting_types ──────────────────────────────────────────────┐
meeting_locations ───────────────────────────────────────────┤
meeting_attendee_groups ──n-n (pivot)──► meeting_attendees ──┤
                                                             ▼
                                                         meetings ──1-n──► meeting_agendas (cây)
                                                             │                    └── 1-n ──► meeting_documents ──► media
                                                             │                    └── 1-n ──► meeting_vote_topics
                                                             │                                    └── 1-n ──► meeting_vote_responses ◄── meeting_participants
                                                             │                    └── 1-n ──► meeting_discussion_registrations
                                                             │                                    └── 1-n ──► meeting_discussion_registration_attachments ──► media
                                                             │
                                                             ├── 1-n ──► meeting_participants ──► meeting_attendees
                                                             │               ├── 1-n ──► meeting_attendances
                                                             │               └── 1-n ──► meeting_personal_notes ──► meeting_personal_note_attachments ──► media
                                                             │
                                                             ├── 1-n ──► meeting_guests
                                                             ├── 1-n ──► meeting_invitations
                                                             ├── 1-n ──► meeting_invitation_templates ──► media
                                                             ├── 1-n ──► meeting_reminders
                                                             └── 1-n ──► meeting_views
```

---

*File được cập nhật theo migration trong `database/migrations/`.*
