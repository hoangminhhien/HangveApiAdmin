# Chạy dự án bằng Docker

Cấu hình dành cho môi trường local: PHP 7.4 + Apache, MySQL 8.0 và Redis 7.
Phiên bản PHP giữ theo yêu cầu `^7.4` trong `composer.json`; cần nâng cấp
PHP và dependencies trước khi dùng cấu hình này cho production.
Cần Docker Desktop hoặc Docker Engine có Docker Compose v2 trở lên.

## Docker Desktop trên macOS với XAMPP

Nếu dự án nằm trong `/Applications/XAMPP/xamppfiles/htdocs`, cần chia sẻ thư mục
dự án với máy ảo Docker trước khi khởi động:

1. Mở Docker Desktop → Settings → File sharing (hoặc Resources → File sharing
   trên giao diện cũ).
2. Trong Virtual file shares, chọn Add directory và thêm
   `/Applications/XAMPP/xamppfiles/htdocs/HangVeApiAdmin`.
3. Chọn Apply, chờ Docker áp dụng cấu hình, rồi chạy lại `docker compose up -d --wait`.

Nếu `.env` có trên máy nhưng Docker báo `bind source path does not exist`, kiểm tra
thiết lập trên. Docker Desktop mặc định không chia sẻ `/Applications`; Compose
có thể đọc được `.env` trong khi máy ảo Docker chưa truy cập được file để mount.
Không cần tạo lại hay ghi đè `.env` đang có.
Tham khảo [File sharing trên Docker Desktop](https://docs.docker.com/desktop/settings-and-maintenance/settings/#file-sharing).

## Khởi động

Chạy tại thư mục gốc dự án. Nếu chưa có `.env`:

```sh
cp .env.docker.example .env
docker compose up -d --build --wait
```

Nếu đã có `.env`, giữ lại `APP_KEY` và các cấu hình tích hợp của bạn, cập nhật
`DB_HOST=mysql`, `DB_PORT=3306`, `REDIS_HOST=redis`, `REDIS_CLIENT=predis`.
Đặt `DB_DATABASE`, `DB_USERNAME` (khác `root`), `DB_PASSWORD` và
`DB_ROOT_PASSWORD` theo file mẫu. Dùng `MAIL_MAILER=log` nếu không có SMTP local.
Chỉ dùng mật khẩu mẫu cho local. Trong file env, chú thích dùng ký tự `#`,
ví dụ `SYNC_MAX_PING=false # Ghi chú`, không dùng `//` sau giá trị.

API: <http://localhost:8000>. MySQL từ máy host: `127.0.0.1:3307`.
Các container truy cập MySQL qua `mysql:3306`, Redis qua `redis:6379`.
Đổi `APP_PORT`/`DB_FORWARD_PORT` nếu cổng bị chiếm; cập nhật `APP_URL` tương ứng.

Image tự cài các package từ `composer.lock`, không cần PHP/Composer trên máy host.
Lần đầu chạy, container tự tạo `APP_KEY` nếu trống, lưu vào `.env` trên host,
và tạo liên kết `public/storage`. Container không tự chạy migration hoặc seed.

Để dùng file env riêng mà không thay đổi `.env` đang dùng với XAMPP:

```sh
cp .env.docker.example .env.docker
DOCKER_ENV_FILE=./.env.docker docker compose --env-file .env.docker up -d --build --wait
```

Với cách này, thêm `DOCKER_ENV_FILE=./.env.docker` và `--env-file .env.docker`
vào các lệnh Compose bên dưới.

## Chuẩn bị database

MySQL khởi tạo database rỗng. Với dữ liệu dự án có sẵn, nhập bản dump local:

```sh
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' < backup.sql
docker compose exec --user www-data app php artisan migrate --force
```

Nếu tạo database mới, có thể chạy migration:

```sh
docker compose exec --user www-data app php artisan migrate --force
```

Hiện tại, chạy toàn bộ migration trên database rỗng dừng ở
`2024_11_26_105333_add_status_4_to_report_deliveries_table` do cột `status_4`
đã tồn tại. Đây là lỗi trong chuỗi migration hiện có; để có đầy đủ schema,
cần nhập bản dump phù hợp của dự án hoặc sửa chuỗi migration trước.
`DatabaseSeeder` hiện đang tắt danh sách seed,
nên `db:seed` không tự tạo tài khoản admin hay dữ liệu nghiệp vụ.
Không chạy `admin:init` trên database cần giữ: lệnh này gọi
`migrate:refresh --seed`, xóa rồi tạo lại các bảng.

## Queue và scheduler (tùy chọn)

Mặc định `QUEUE_CONNECTION=sync`. Để xử lý job qua Redis, đổi thành
`QUEUE_CONNECTION=redis` trong file env, đặt `DOCKER_QUEUES` là danh sách
queue cần chạy (mặc định `default,sent-noti-admin`), rồi:

```sh
docker compose --profile queue up -d
docker compose restart app
```

Các queue khác trong dự án gồm `sync-data-report`, `cron-data-2023`,
`sync-order-platform`, `sent-sms-customer`, `export-1800`.
Worker mẫu có timeout 60 giây, nhỏ hơn `retry_after=90` của Redis.
Job chạy lâu cần worker riêng và điều chỉnh `retry_after` phù hợp.

Bật các tác vụ theo lịch sau khi đã có database và cấu hình tích hợp phù hợp:

```sh
docker compose --profile queue --profile scheduler up -d
```

Scheduler thực thi các lệnh trong `app/Console/Kernel.php`, bao gồm đồng bộ
dữ liệu và gửi thông báo. Mặc định hai profile này đều tắt.

## Thao tác thường dùng

```sh
# Trạng thái và log
docker compose ps
docker compose logs -f app

# Chạy Artisan / kiểm tra kết nối database
docker compose exec --user www-data app php artisan migrate:status

# Build lại sau khi sửa source hoặc composer.lock
docker compose up -d --build --wait
# Nếu đang sử dụng worker/scheduler, thêm các --profile tương ứng khi build lại.

# Dừng container, giữ dữ liệu
docker compose --profile queue --profile scheduler down
```

Source được copy vào image, vì vậy thay đổi code cần build lại.
`.env` được mount từ host; MySQL, Redis, `storage` và `public/demo` dùng volume.
Giữ `APP_KEY` cũ khi dùng lại database/storage.
Không dùng `docker compose down -v` nếu cần giữ dữ liệu: `-v` xóa các volume.
Thông tin khởi tạo MySQL chỉ áp dụng khi volume database còn trống; đổi mật khẩu
trong env không tự thay mật khẩu của database đã có.

Các dịch vụ đối tác, S3 và Socket.IO không nằm trong Compose này; bổ sung cấu hình
của môi trường local nếu sử dụng những chức năng đó. Dịch vụ chạy trên máy host
có thể được truy cập qua `host.docker.internal` khi dùng Docker Desktop.

Tham khảo: [PHP Docker image](https://hub.docker.com/_/php),
[thứ tự khởi động Compose](https://docs.docker.com/compose/how-tos/startup-order/),
[Compose profiles](https://docs.docker.com/compose/how-tos/profiles/).
