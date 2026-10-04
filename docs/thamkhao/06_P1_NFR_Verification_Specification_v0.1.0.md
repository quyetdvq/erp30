---
document_id: "NFR-VS-ERP-P1-001"
title: "Đặc tả Kiểm chứng Yêu cầu Phi chức năng - Dinktech Mini ERP V2.0 - P1 Core"
phase: "NFR Verification Specification"
status: "draft"
version: "0.1.0"
owner: "TBD"
authority: "Bản verification specification downstream cho 7 P1 NFR; chỉ trở thành verification evidence sau khi được phê duyệt, thực thi và đưa dưới configuration control."
source_baseline: "REQ-ERP-001 v1.0.2"
source_nfs: "NFS-ERP-P1-001 v0.1.0"
related_frs: "FRS-ERP-P1-001 v0.5.0"
target_phase: "P1_CORE"
---

# Đặc tả Kiểm chứng Yêu cầu Phi chức năng - Dinktech Mini ERP V2.0 - P1 Core

## 1. Hợp đồng tài liệu

Tài liệu này định nghĩa verification specification cho 7 NFR thuộc `P1_CORE`. Ngôn ngữ chính là tiếng Việt; ID, metric và thuật ngữ canonical được giữ nguyên khi cần để tránh sai nghĩa.

Nguyên tắc:

1. Mỗi `NFR-TEST-*` trace trực tiếp về một `NFR-*` và một `NFS-*` tương ứng.
2. Test chỉ kiểm các success criteria/capability thực sự nằm trong approved scope P1.
3. Không tạo giả report, document, tax, payroll, DR, manufacturing, intercompany hoặc capability phase sau chỉ để làm test data.
4. Mọi test run phải ghi build/version, environment, dataset/profile version, cấu hình/policy có hiệu lực, thời gian, timezone khi liên quan và người thực hiện/xác nhận.
5. Nếu một `TBD` trong NFS ảnh hưởng trực tiếp pass/fail semantics thì test case liên quan ở trạng thái `BLOCKED` cho tới khi có controlled decision.
6. Test evidence phải đủ để một reviewer độc lập tái kiểm kết luận PASS/FAIL.

## 2. Traceability matrix

| Upstream NFR | NFS | Verification Artifact | Số success criteria nguồn |
|---|---|---|---:|
| `NFR-SEC-001` | `NFS-SEC-001` | `NFR-TEST-SEC-001` | 3 |
| `NFR-AVL-001` | `NFS-AVL-001` | `NFR-TEST-AVL-001` | 4 |
| `NFR-PER-001` | `NFS-PER-001` | `NFR-TEST-PER-001` | 6 |
| `NFR-AUD-001` | `NFS-AUD-001` | `NFR-TEST-AUD-001` | 8 |
| `NFR-DAT-001` | `NFS-DAT-001` | `NFR-TEST-DAT-001` | 6 |
| `NFR-USE-001` | `NFS-USE-001` | `NFR-TEST-USE-001` | 5 |
| `NFR-PLT-001` | `NFS-PLT-001` | `NFR-TEST-PLT-001` | 4 |

## 3. Quy ước test evidence

Mỗi test case phải lưu tối thiểu:

- `test_case_id`;
- build/application version;
- environment ID;
- test data/profile version;
- policy/configuration version;
- start/end time;
- actor/test identity;
- actual result;
- PASS/FAIL/BLOCKED;
- log/screenshot/report/trace reference khi phù hợp;
- defect reference nếu FAIL.

### 3.1 Trạng thái kết quả

- `PASS`: actual result đáp ứng đầy đủ expected result.
- `FAIL`: actual result vi phạm criterion.
- `BLOCKED`: controlled prerequisite/TBD cần cho pass/fail chưa được resolve hoặc environment không đáp ứng precondition.
- `N/A`: chỉ dùng khi criterion có conditional applicability và source capability không nằm trong approved scope; phải ghi rõ căn cứ.

---

## 4. NFR-TEST-SEC-001 — Kiểm chứng bảo vệ dữ liệu và authorization consistency

```yaml
id: "NFR-TEST-SEC-001"
verifies: "NFR-SEC-001"
source_nfs: "NFS-SEC-001"
status: draft
```

### 4.1 Mục tiêu

Chứng minh user/surface thuộc P1 không thể đọc, sửa hoặc export dữ liệu ngoài quyền; mọi P1 surface dùng cùng Resource/Action Permission và final Authorization Evaluation.

### 4.2 Preconditions

- `P1 Security Surface Register` đã được khóa.
- Có test identities tối thiểu cho: đúng Group/Company, sai Group, sai Company/context, thiếu Permission, Permission + policy DENY, Permission + ALLOW, sensitive-data view/export split.
- Dataset có dữ liệu phân tách theo ít nhất hai Group hoặc scope tương đương để kiểm tra isolation.

### 4.3 Test cases

| Test case | Mục tiêu | Phương pháp | Expected result | Trace |
|---|---|---|---|---|
| `SEC-TC-001` | Cross-Group isolation | Dùng identity chỉ có membership Group A truy cập record Group B qua từng P1 surface | Tất cả request bị DENY; không lộ business data Group B | SC1 |
| `SEC-TC-002` | Cross-Company/context isolation | Dùng identity có Group access nhưng không có context phù hợp truy cập Company khác | Bị DENY theo authorization context | SC1 |
| `SEC-TC-003` | Missing Permission gate | User có context nhưng không có Resource/Action Permission | Final decision DENY kể cả khi policy khác có ALLOW | SC2 |
| `SEC-TC-004` | Policy-required behavior | User có Permission; chạy các trường hợp ABSOLUTE_DENY/ALLOW/DENY/NO_MATCH theo controlled fixture | Kết quả đúng canonical `FR-IAM-003` | SC2 |
| `SEC-TC-005` | Sensitive view/export separation | User có quyền view nhưng không có export | View chỉ hoạt động khi final decision ALLOW; export bị DENY | SC1/SC2 |
| `SEC-TC-006` | API/UI consistency | Thực hiện cùng Resource/Action qua UI và API/data-access surface khi cả hai tồn tại | Không có đường bypass; final authorization semantics tương đương | SC2 |
| `SEC-TC-007` | Future surface exclusion | Review scope register với report/document/export phase sau | Không yêu cầu surface chưa approved phải tồn tại để PASS P1 | SC3 |

### 4.4 Pass criteria

`NFR-TEST-SEC-001` chỉ PASS khi:

1. mọi surface trong controlled register có coverage;
2. không test cross-scope nào trả dữ liệu/trạng thái cho phép trái quyền;
3. không có UI/API mismatch dẫn đến bypass;
4. sensitive export không được suy ra từ view permission;
5. không có test giả cho capability phase sau.

### 4.5 Evidence bắt buộc

- Security Surface Register version;
- permission/policy fixture version;
- request/result matrix;
- decision trace cho representative allow/deny cases;
- defect list nếu có.

---

## 5. NFR-TEST-AVL-001 — Kiểm chứng availability trong operating window

```yaml
id: "NFR-TEST-AVL-001"
verifies: "NFR-AVL-001"
source_nfs: "NFS-AVL-001"
status: draft
```

### 5.1 Preconditions

- P1 Company/service scope đã khóa.
- Operating window/version/timezone đã phê duyệt.
- Có telemetry đủ để xác định downtime start/end.
- Planned maintenance có approval và notification evidence khi cần loại trừ.

### 5.2 Test cases

| Test case | Phương pháp | Expected result | Trace |
|---|---|---|---|
| `AVL-TC-001` | Tái tính monthly availability từ raw intervals | Kết quả `>= 99.5%` cho scope/kỳ acceptance | SC1 |
| `AVL-TC-002` | Kiểm tra raw evidence của measurement window, timezone, downtime intervals | Có đủ dữ liệu để reviewer tái tính | SC2 |
| `AVL-TC-003A` | Planned maintenance được approved và notified >=48h | Interval đủ điều kiện có thể được loại khỏi phép đo | SC3 |
| `AVL-TC-003B` | Maintenance thiếu approval hoặc notification <48h | Interval phải tính vào downtime | SC3 |
| `AVL-TC-004` | Thay operating-window version sau một kỳ đã công bố | Kết quả lịch sử không bị hồi tố; kỳ mới dùng version mới | SC4 |

### 5.3 Pass criteria

- Monthly availability đạt 99.5% trở lên.
- Reviewer độc lập có thể tái tính từ evidence.
- Không interval maintenance nào bị loại trừ trái điều kiện nguồn.
- Version/effective time của operating window được bảo toàn.

### 5.4 Evidence bắt buộc

Measurement dataset, raw downtime log, maintenance approval/notification evidence, formula/calculation workbook hoặc report, operating-window version và reviewer sign-off.

---

## 6. NFR-TEST-PER-001 — Kiểm chứng hiệu năng tương tác P1

```yaml
id: "NFR-TEST-PER-001"
verifies: "NFR-PER-001"
source_nfs: "NFS-PER-001"
status: draft
```

### 6.1 Preconditions

- Controlled performance data profile đạt ít nhất baseline nguồn.
- Workload mix và interaction-class catalog đã được phê duyệt.
- Test environment và measurement method được ghi version.
- Có 100 concurrent users trong workload acceptance.

### 6.2 Dataset gate

Test run không hợp lệ nếu dataset nhỏ hơn bất kỳ baseline tối thiểu nào sau:

- 100,000 Product;
- 50,000 Customer;
- 10,000 Supplier;
- 500,000 Inventory Transaction;
- 250,000 Sales Order;
- 100,000 Purchase Order.

### 6.3 Test cases

| Test case | Workload | Metric | Pass criterion | Trace |
|---|---|---|---|---|
| `PER-TC-001` | Normal lookup/navigation | p95 response time | `<= 2s` | SC1 |
| `PER-TC-002` | Normal transaction create/update | p95 response time | `<= 3s` | SC2 |
| `PER-TC-003` | Complex business query | p95 response time | `<= 5s` | SC3 |
| `PER-TC-004` | Full mixed workload | concurrency/profile | 100 concurrent users + dataset baseline + controlled mix | SC4 |
| `PER-TC-005` | Reproducibility review | metadata/evidence | dataset version, workload mix, environment, measurement method đầy đủ | SC5 |
| `PER-TC-006` | Approved async task | UX/result handling | trạng thái tiến trình, kết quả và lỗi rõ ràng | SC6 |

### 6.4 Execution rules

1. Warm-up/ramp-up/excluded interval phải được ghi rõ.
2. Failed/timeout requests không được loại khỏi report một cách im lặng.
3. Mỗi class phải có sample volume đủ theo performance test profile đã phê duyệt.
4. p95 phải được tính riêng theo class; không dùng trung bình để thay thế p95.
5. Nếu workload mix hoặc interaction classification chưa khóa, test tương ứng là `BLOCKED`, không được tự chọn mix có lợi cho kết quả.

### 6.5 Evidence bắt buộc

Raw metrics, percentile report, dataset manifest, workload script/version, environment profile, concurrent-user trace, error/timeout summary và execution log.

---

## 7. NFR-TEST-AUD-001 — Kiểm chứng audit evidence integrity và reconstruction

```yaml
id: "NFR-TEST-AUD-001"
verifies: "NFR-AUD-001"
source_nfs: "NFS-AUD-001"
status: draft
```

### 7.1 Preconditions

- P1 audit-evidence retention baseline đã được phê duyệt.
- Có controlled sample transaction cho create/change/approve/cancel/adjust hoặc các lifecycle path thực sự tồn tại ở P1.
- Tamper-detection oracle/mechanism đã được xác định.

### 7.2 Test cases

| Test case | Phương pháp | Expected result | Trace |
|---|---|---|---|
| `AUD-TC-001` | Thử sửa/xóa audit evidence qua business flow thông thường | Không thể sửa/xóa | SC1 |
| `AUD-TC-002` | Thực hiện privileged maintenance được policy cho phép, nếu có | Có evidence về actor, time, reason, result | SC2 |
| `AUD-TC-003` | Tạo mẫu tampering/chuỗi evidence thiếu theo test fixture | Integrity check phát hiện theo oracle đã phê duyệt | SC3 |
| `AUD-TC-004` | Hủy/thay thế/điều chỉnh business record | Before/after và relation cũ/mới vẫn truy cập được | SC4 |
| `AUD-TC-005` | Đi qua exception path hợp lệ | Audit history trước exception vẫn còn nguyên | SC5 |
| `AUD-TC-006` | Chọn sample P1 transaction và reconstruction từ audit store | Tái lập được time, scope, actor và change/context | SC6 |
| `AUD-TC-007` | Query evidence ở biên retention baseline | Evidence vẫn queryable trong toàn retention baseline | SC7 |
| `AUD-TC-008` | Review scope đối với report/decision surface phase sau | Không tạo giả surface ngoài P1 chỉ để PASS | SC8 |

### 7.3 Pass criteria

Tất cả test áp dụng phải PASS. `AUD-TC-007` là `BLOCKED` nếu `NE-TBD-AUD-001` chưa resolve vì retention duration/start event là điều kiện trực tiếp của pass/fail.

### 7.4 Evidence bắt buộc

Audit query exports/references, tamper-test output, privileged-action evidence khi có, lifecycle relation evidence, retention-baseline version và reconstruction worksheet/report.

---

## 8. NFR-TEST-DAT-001 — Kiểm chứng precision, rounding và reconciliation tolerance

```yaml
id: "NFR-TEST-DAT-001"
verifies: "NFR-DAT-001"
source_nfs: "NFS-DAT-001"
status: draft
```

### 8.1 Test data classes

Tối thiểu gồm:

- quantity có hơn 2 và tới ít nhất 6 decimal places;
- UOM conversion có factor tạo kết quả thập phân;
- unit price/rate có precision cao;
- currency có minor unit áp dụng;
- multi-line transaction để kiểm tra reconciliation tolerance khi phù hợp.

### 8.2 Test cases

| Test case | Phương pháp | Expected result | Trace |
|---|---|---|---|
| `DAT-TC-001` | Ghi và xử lý quantity yêu cầu 6 decimal places | Không mất source value trước controlled rounding | SC1 |
| `DAT-TC-002` | Tính với high-precision unit price/exchange rate | Internal precision đủ để final posting tuân policy; không truncate trái phép | SC2 |
| `DAT-TC-003` | Monetary calculation trên currency có minor unit | Final posting làm tròn đúng minor unit; pre/post value truy được | SC3 |
| `DAT-TC-004` | Review tax path | `N/A` ở P1 nếu `BR-FIN-004` chưa approved; không tạo tax engine giả | SC4 |
| `DAT-TC-005` | Review Payroll/legal-specialized path | `N/A` ở P1 nếu capability chưa approved | SC5 |
| `DAT-TC-006` | Reconcile document total và chi tiết | Delta mặc định `<= 1 minor unit` trừ controlled policy khác | SC6 |

### 8.3 Pass criteria

- Source quantity được bảo toàn.
- Không có truncation làm sai final posting.
- Monetary value đúng minor unit.
- Reconciliation tolerance đúng baseline.
- Conditional phase-later rule được xử lý `N/A`, không giả lập capability.

### 8.4 Evidence bắt buộc

Input raw values, factor/rate, intermediate values, rounding rule, final values, reconciliation worksheet và database/application trace đủ để kiểm tra precision behavior.

---

## 9. NFR-TEST-USE-001 — Kiểm chứng usability, localization và semantic consistency

```yaml
id: "NFR-TEST-USE-001"
verifies: "NFR-USE-001"
source_nfs: "NFS-USE-001"
status: draft
```

### 9.1 Preconditions

- Controlled terminology glossary đã có owner/version.
- Locale/language set P1 đã được phê duyệt.
- Representative-user cohort đã được xác định.
- Có sample transaction chứa timestamp, date, currency, monetary value và unit display.

### 9.2 Test cases

| Test case | Phương pháp | Expected result | Trace |
|---|---|---|---|
| `USE-TC-001` | Representative users review thuật ngữ/đơn vị P1 | Có xác nhận hoặc defect/decision record; terminology đạt trạng thái approved cho acceptance | SC1 |
| `USE-TC-002` | Review glossary governance | Mỗi canonical term có định nghĩa và owner | SC2 |
| `USE-TC-003` | Đổi locale/display trên cùng transaction | Canonical timestamp/period/currency/value không đổi | SC3 |
| `USE-TC-004` | Hiển thị cùng transaction ở các locale được hỗ trợ | Display có thể khác nhưng resolve cùng canonical values | SC4 |
| `USE-TC-005` | Review HR work-calendar criterion | `N/A` P1 khi HR capability chưa approved; không tạo HR flow giả | SC5 |

### 9.3 Pass criteria

- Không semantic drift do locale/display.
- Glossary có owner và representative-user confirmation.
- Conditional HR criterion được xử lý đúng scope.

### 9.4 Evidence bắt buộc

Glossary version, review/sign-off record, before/after locale screenshots hoặc data traces, canonical persisted values và defect list nếu có.

---

## 10. NFR-TEST-PLT-001 — Kiểm chứng web desktop platform support

```yaml
id: "NFR-TEST-PLT-001"
verifies: "NFR-PLT-001"
source_nfs: "NFS-PLT-001"
status: draft
```

### 10.1 Preconditions

- Tại thời điểm acceptance đã resolve current stable và 2 major versions gần nhất của Chrome/Edge.
- Windows support baseline Dinktech đã được khóa.
- Có controlled browser/OS matrix và P1 critical-flow suite.

### 10.2 Test cases

| Test case | Phương pháp | Expected result | Trace |
|---|---|---|---|
| `PLT-TC-001` | Chạy P1 critical flows trên Chrome current stable + 2 major trước | Các chức năng P1 vận hành theo acceptance criteria | SC1 |
| `PLT-TC-002` | Chạy cùng suite trên Edge current stable + 2 major trước | Các chức năng P1 vận hành theo acceptance criteria | SC1 |
| `PLT-TC-003` | Chạy matrix trên Windows 10/11 hoặc supported Windows baseline | Environment nằm trong supported baseline và flow PASS | SC2 |
| `PLT-TC-004` | Kiểm tra scope device | Mobile/tablet không được ghi là supported P1 nếu chưa có requirement | SC3 |
| `PLT-TC-005` | Review artifact test | Browser/OS versions cụ thể được snapshot đầy đủ | SC4 |

### 10.3 P1 critical-flow suite tối thiểu

Matrix compatibility phải bao phủ ít nhất representative flows thuộc P1:

- login/access context;
- Organization/Employee/IAM administration representative path;
- Product/Customer/Supplier/Pricing representative path;
- Purchase Request -> Purchase Order -> Receiving;
- Inventory lookup/movement/adjustment representative path;
- Sales Order capture -> ATP/availability;
- locale/display switch representative path.

Suite này không mở rộng business scope; nó chọn representative flow từ FRS P1 để kiểm compatibility.

### 10.4 Evidence bắt buộc

Browser/OS matrix, exact versions/builds, test execution report, screenshot/log/defect references và application build ID.

---

## 11. Verification coverage matrix theo upstream success criterion

| NFR | Success criterion | Test coverage |
|---|---:|---|
| `NFR-SEC-001` | 1 | `SEC-TC-001`, `002`, `005` |
|  | 2 | `SEC-TC-003`, `004`, `005`, `006` |
|  | 3 | `SEC-TC-007` |
| `NFR-AVL-001` | 1 | `AVL-TC-001` |
|  | 2 | `AVL-TC-002` |
|  | 3 | `AVL-TC-003A`, `003B` |
|  | 4 | `AVL-TC-004` |
| `NFR-PER-001` | 1 | `PER-TC-001` |
|  | 2 | `PER-TC-002` |
|  | 3 | `PER-TC-003` |
|  | 4 | `PER-TC-004` |
|  | 5 | `PER-TC-005` |
|  | 6 | `PER-TC-006` |
| `NFR-AUD-001` | 1 | `AUD-TC-001` |
|  | 2 | `AUD-TC-002` |
|  | 3 | `AUD-TC-003` |
|  | 4 | `AUD-TC-004` |
|  | 5 | `AUD-TC-005` |
|  | 6 | `AUD-TC-006` |
|  | 7 | `AUD-TC-007` |
|  | 8 | `AUD-TC-008` |
| `NFR-DAT-001` | 1 | `DAT-TC-001` |
|  | 2 | `DAT-TC-002` |
|  | 3 | `DAT-TC-003` |
|  | 4 | `DAT-TC-004` |
|  | 5 | `DAT-TC-005` |
|  | 6 | `DAT-TC-006` |
| `NFR-USE-001` | 1 | `USE-TC-001` |
|  | 2 | `USE-TC-002` |
|  | 3 | `USE-TC-003` |
|  | 4 | `USE-TC-004` |
|  | 5 | `USE-TC-005` |
| `NFR-PLT-001` | 1 | `PLT-TC-001`, `002` |
|  | 2 | `PLT-TC-003` |
|  | 3 | `PLT-TC-004` |
|  | 4 | `PLT-TC-005` |

## 12. Controlled blockers từ NFS

Các decision sau phải được resolve trước khi test liên quan có thể kết luận PASS/FAIL đầy đủ:

| NFS TBD | Test bị ảnh hưởng |
|---|---|
| `NE-TBD-SEC-001` | toàn bộ `SEC-TC-*` coverage completeness |
| `NE-TBD-SEC-003` | `SEC-TC-001` đến `006` |
| `NE-TBD-AVL-001` | `AVL-TC-001`, `002`, `004` |
| `NE-TBD-AVL-002` | `AVL-TC-001` đến `003B` |
| `NE-TBD-AVL-003` | availability result nếu có degraded interval |
| `NE-TBD-PER-001` | `PER-TC-001` đến `004` |
| `NE-TBD-PER-002` | `PER-TC-001` đến `003` |
| `NE-TBD-PER-003` | toàn bộ performance run validity |
| `NE-TBD-AUD-001` | `AUD-TC-007` và final audit acceptance |
| `NE-TBD-AUD-003` | `AUD-TC-003` |
| `NE-TBD-DAT-001/002` | `DAT-TC-002/003` nếu implementation choice chưa khóa |
| `NE-TBD-USE-001/002` | `USE-TC-001`, `003`, `004` |
| `NE-TBD-PLT-001/002` | toàn bộ `PLT-TC-*` execution matrix |

## 13. NFR verification exit gate

Wave E verification specification có thể chuyển sang execution-ready candidate khi:

1. 7/7 `NFR-TEST-*` tồn tại và trace đúng `NFR-*`/`NFS-*`;
2. mỗi success criterion nguồn có ít nhất một test case hoặc conditional `N/A` rule rõ ràng;
3. tất cả blocker làm thay đổi pass/fail đã có controlled decision;
4. test data/profile/environment/versioning đủ để tái lập;
5. security surface, performance workload, availability scope, audit retention, locale cohort và browser/OS matrix đã khóa;
6. test không tạo capability P2/P3 giả;
7. evidence format cho phép reviewer độc lập xác minh kết luận;
8. kết quả execution sau này được đưa dưới configuration control trước khi cập nhật `verified_by` trong User Requirements.

## 14. Revision Notes

### v0.1.0 — Initial P1 NFR Verification Specification

- Tạo 7 `NFR-TEST-*` tương ứng 7 P1 NFR.
- Phủ toàn bộ success criteria nguồn bằng test case hoặc conditional scope rule.
- Định nghĩa precondition, phương pháp, pass/fail và evidence cho Security, Availability, Performance, Auditability, Data Integrity, Usability/Localization và Web Desktop Platform.
- Giữ các decision chưa được baseline xác định ở trạng thái BLOCKED/TBD thay vì tự chọn giá trị test có lợi.
- Không kéo DR, capacity/freshness, enterprise retention, configurability hoặc capability business phase sau vào P1.
