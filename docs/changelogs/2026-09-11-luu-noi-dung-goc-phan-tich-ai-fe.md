# Lưu lại nguyên văn đã dán vào ô phân tích AI

> Ngày tạo: 14:05:00 11/09/2026  
> Cập nhật lần cuối: 14:05:00 11/09/2026

Nội dung người dùng dán vào ô "Phân tích bằng AI" ở màn văn bản giao việc trước đây chỉ nằm
trong bộ nhớ trình duyệt: gửi sang DeepSeek, nhận về tiêu đề / tóm tắt / danh sách đầu việc
rồi biến mất. Hệ quả: mở lại văn bản để sửa thì ô AI trống trơn, muốn phân tích lại phải đi
tìm bản gốc dán lần nữa, và cũng không đối chiếu được tóm tắt AI sinh ra với nguyên văn.

Nay nguyên văn được lưu cùng văn bản, ở cột mới `ai_source_content`.

---

## Thay đổi

| | Trước | Sau |
|---|---|---|
| Cột `task_assignment_documents.ai_source_content` | không có | `longtext`, nullable, đứng sau `summary` |
| `POST /api/task-assignment-documents` | — | nhận thêm `ai_source_content` (`nullable\|string\|max:50000`) |
| `PUT /api/task-assignment-documents/{id}` | — | nhận thêm `ai_source_content` (`sometimes\|nullable\|string\|max:50000`) |
| `GET /api/task-assignment-documents/{id}` | — | trả thêm `ai_source_content` |
| `GET /api/task-assignment-documents` (danh sách) | — | **KHÔNG** trả `ai_source_content` |

`max:50000` khớp đúng trần của `AnalyzeDocumentRequest`: dài hơn thế thì AI đã từ chối phân
tích, không có đường nào tạo ra chuỗi hợp lệ dài hơn.

LONGTEXT chứ không TEXT: TEXT chứa ~65.535 **byte**, mà trần trên đếm **ký tự** — tiếng Việt
có dấu tốn tới 3 byte/ký tự nên một văn bản hợp lệ vẫn có thể chạm 150KB và bị MySQL strict
mode chặn.

## Vì sao danh sách không trả trường này

Nguyên văn có thể tới 150KB mỗi bản ghi; một trang 20 dòng là 3MB chỉ để hiển thị tên và
trạng thái. Truy vấn danh sách (`TaskAssignmentDocumentService::index`) select theo hằng
`TaskAssignmentDocument::LIST_COLUMNS` — cố tình không có cột này — còn Resource dùng
`whenHas` nên trường chỉ xuất hiện ở chi tiết.

Thêm cột mới mà muốn nó hiện ở danh sách thì phải khai thêm vào `LIST_COLUMNS`, y như với
`$fillable`.

## Việc cần làm ở FE

Đã làm trong cùng đợt (`core-fe`, `TaskDocumentDetail.vue`): ô nhập AI đổi từ ref rời
`aiInput` sang `form.ai_source_content`, nhờ vậy tự đi theo payload tạo/sửa và được nạp lại
từ `GET /{id}` khi mở sửa. Màn danh sách không dùng trường này nên không phải sửa gì.
