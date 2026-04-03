# Quandh Core API

Hệ thống backend API xây dựng trên Laravel theo kiến trúc module tại `app/Modules`, phục vụ các nhóm chức năng:

- `Auth`: đăng nhập, quên mật khẩu, đặt lại mật khẩu, chuyển tổ chức làm việc.
- `Core`: người dùng, vai trò, quyền, tổ chức, cấu hình, log hoạt động.
- `TaskAssignment`: quản lý giao việc liên phòng ban (văn bản, công việc, báo cáo, thống kê).

## Yêu cầu môi trường

- Docker và Docker Compose.
- PHP 8.2+ (nếu chạy local không qua container).
- Node.js (nếu chạy frontend asset ngoài container).

Khuyến nghị sử dụng Laravel Sail cho toàn bộ thao tác chạy lệnh trong dự án.

## Cài đặt nhanh

1. Cài thư viện:

```bash
composer install
```

2. Tạo file môi trường:

```bash
cp .env.example .env
```

3. Khởi động container:

```bash
./vendor/bin/sail up -d
```

4. Tạo key ứng dụng và migrate:

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

5. Tạo symlink storage (cần cho upload ảnh/file):

```bash
./vendor/bin/sail artisan storage:link
```

6. Cài frontend dependencies:

```bash
./vendor/bin/sail npm install
```

## Chạy dự án

- API + queue + log + Vite:

```bash
./vendor/bin/sail composer dev
```

- Hoặc chạy riêng:

```bash
./vendor/bin/sail artisan serve
./vendor/bin/sail artisan queue:listen --tries=1 --timeout=0
./vendor/bin/sail npm run dev
```

## Tài liệu

- API docs: `/docs` (sau khi generate).
- Tài liệu API dạng markdown: `docs/api`.
- Thiết kế CSDL: `docs/DATABASE_DESIGN.md`.
- Thiết kế cấu trúc dự án: `docs/STRUCTURE_DESIGN.md`.
- Tài liệu phân tích/giải pháp: `docs/answer`.

Sinh lại tài liệu Scribe:

```bash
./vendor/bin/sail artisan scribe:generate
```

## Kiểm thử và chất lượng mã

- Chạy test:

```bash
./vendor/bin/sail artisan test
```

- Kiểm tra coding style:

```bash
./vendor/bin/sail pint --test
```

- Tự động format:

```bash
./vendor/bin/sail pint
```

## Ghi chú triển khai

- Tất cả endpoint API nằm dưới `routes/api.php` và route module tại `app/Modules/*/Routes`.
- Chuẩn response JSON dùng trait `App\Modules\Core\Traits\RespondsWithJson`.
- Phân quyền dùng Spatie Permission với guard `web`.
