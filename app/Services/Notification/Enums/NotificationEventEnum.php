<?php

namespace App\Services\Notification\Enums;

enum NotificationEventEnum: string
{
    case DocumentIssued = 'document_issued';
    case TaskAssigned = 'task_assigned';
    case TaskCompleted = 'task_completed';
    case TaskConfirmed = 'task_confirmed';
    case ReportSubmitted = 'report_submitted';
    case TaskRejected = 'task_rejected';
    case DeadlineExtensionRequested = 'deadline_extension_requested';
    case DeadlineExtensionReviewed = 'deadline_extension_reviewed';
    case TaskDeadlineChanged = 'task_deadline_changed';
    case TaskStatusChanged = 'task_status_changed';
    case NoteAdded = 'note_added';
    case PetitionCreated = 'petition_created';
    case PetitionStatusChanged = 'petition_status_changed';
    case DocumentUpdated = 'document_updated';
    case ReminderBefore = 'reminder_before';
    case ReminderOn = 'reminder_on';
    case ReminderAfter = 'reminder_after';
    case MeetingPublished = 'meeting_published';
    case MeetingUpdated = 'meeting_updated';
    case MeetingCancelled = 'meeting_cancelled';
    case MeetingReminderBefore = 'meeting_reminder_before';
    case MeetingReminderOn = 'meeting_reminder_on';
    case MeetingReminderAfter = 'meeting_reminder_after';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /**
     * Module sở hữu event này — dùng để filter config theo module cho FE.
     */
    public function module(): NotificationModuleEnum
    {
        return match ($this) {
            self::DocumentIssued,
            self::TaskAssigned,
            self::TaskCompleted,
            self::TaskConfirmed,
            self::ReportSubmitted,
            self::TaskRejected,
            self::DeadlineExtensionRequested,
            self::DeadlineExtensionReviewed,
            self::TaskDeadlineChanged,
            self::TaskStatusChanged,
            self::NoteAdded,
            self::PetitionCreated,
            self::PetitionStatusChanged,
            self::DocumentUpdated,
            self::ReminderBefore,
            self::ReminderOn,
            self::ReminderAfter => NotificationModuleEnum::TaskAssignment,
            self::MeetingPublished,
            self::MeetingUpdated,
            self::MeetingCancelled,
            self::MeetingReminderBefore,
            self::MeetingReminderOn,
            self::MeetingReminderAfter => NotificationModuleEnum::Meeting,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DocumentIssued => 'Văn bản được ban hành',
            self::TaskAssigned => 'Được giao việc mới',
            self::TaskCompleted => 'Công việc hoàn thành 100% (chờ duyệt)',
            self::TaskConfirmed => 'Công việc được xác nhận',
            self::ReportSubmitted => 'Có báo cáo công việc mới',
            self::TaskRejected => 'Công việc bị trả lại',
            self::DeadlineExtensionRequested => 'Có yêu cầu gia hạn thời hạn',
            self::DeadlineExtensionReviewed => 'Yêu cầu gia hạn đã được duyệt hoặc từ chối',
            self::TaskDeadlineChanged => 'Thời hạn công việc bị thay đổi',
            self::TaskStatusChanged => 'Công việc bị tạm dừng, huỷ hoặc mở lại',
            self::NoteAdded => 'Có trao đổi mới trên công việc',
            self::PetitionCreated => 'Có đơn thư mới',
            self::PetitionStatusChanged => 'Đơn thư đổi trạng thái',
            self::DocumentUpdated => 'Văn bản giao việc được sửa sau khi ban hành',
            self::ReminderBefore => 'Nhắc trước hạn',
            self::ReminderOn => 'Nhắc đến hạn',
            self::ReminderAfter => 'Nhắc quá hạn',
            self::MeetingPublished => 'Cuộc họp đã được phát hành',
            self::MeetingUpdated => 'Cuộc họp đã cập nhật thông tin',
            self::MeetingCancelled => 'Cuộc họp đã bị hủy',
            self::MeetingReminderBefore => 'Nhắc trước cuộc họp',
            self::MeetingReminderOn => 'Nhắc đến giờ họp',
            self::MeetingReminderAfter => 'Nhắc sau cuộc họp',
        };
    }
}
