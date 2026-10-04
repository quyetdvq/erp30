---
document_id: "NFS-ERP-P1-001"
title: "Đặc tả Phi chức năng - Dinktech Mini ERP V2.0 - P1 Core"
phase: "Non-functional Specification"
status: "draft"
version: "0.1.0"
owner: "TBD"
authority: "Bản đặc tả downstream cho 7 NFR thuộc REQ-ERP-001 P1_CORE; chỉ trở thành implementation evidence sau khi được phê duyệt và đưa dưới configuration control."
source_baseline: "REQ-ERP-001 v1.0.2"
related_frs: "FRS-ERP-P1-001 v0.5.0"
target_phase: "P1_CORE"
---

# Đặc tả Phi chức năng - Dinktech Mini ERP V2.0 - P1 Core

## 1. Hợp đồng tài liệu

Tài liệu này đặc tả 7 Non-functional Requirement thuộc `P1_CORE` của `REQ-ERP-001 v1.0.2`. Ngôn ngữ chính của tài liệu là tiếng Việt. Các thuật ngữ tiếng Anh chỉ được giữ khi chúng là ID, metric, tên chuẩn kỹ thuật hoặc thuật ngữ canonical đã có trong baseline nguồn.

Nguyên tắc áp dụng:

1. Mỗi `NFS-*` trace trực tiếp về đúng một `NFR-*` P1 theo `planned_implemented_by` của User Requirements.
2. NFS không mở rộng capability P2/P3 thành nghĩa vụ P1. Những điều kiện có tính conditional applicability chỉ có hiệu lực khi capability nguồn tương ứng nằm trong approved scope.
3. NFS mô tả quality contract, measurement contract, evidence contract và boundary cần cho implementation/acceptance; không thay thế Business Requirement hoặc Functional Requirement.
4. Khi User Requirements chưa xác định giá trị policy, catalog, thời lượng hoặc thuật toán cụ thể, tài liệu ghi `TBD` có kiểm soát thay vì tự suy diễn.
5. `NFR-TEST-*` là verification artifact riêng; tài liệu này chỉ nêu evidence requirement và verification hook cần có.
6. Actual traceability trong User Requirements chỉ được chuyển từ `planned_*` sang actual field sau khi artifact tương ứng được phê duyệt và đưa dưới configuration control.

## 2. Phạm vi P1 NFR

P1 có đúng 7 NFR bắt buộc:

| Upstream NFR | NFS | Planned Verification | Mục tiêu chất lượng |
|---|---|---|---|
| `NFR-SEC-001` | `NFS-SEC-001` | `NFR-TEST-SEC-001` | Security/authorization consistency |
| `NFR-AVL-001` | `NFS-AVL-001` | `NFR-TEST-AVL-001` | Availability trong operating window |
| `NFR-PER-001` | `NFS-PER-001` | `NFR-TEST-PER-001` | Hiệu năng tương tác P1 |
| `NFR-AUD-001` | `NFS-AUD-001` | `NFR-TEST-AUD-001` | Auditability/integrity/queryability |
| `NFR-DAT-001` | `NFS-DAT-001` | `NFR-TEST-DAT-001` | Precision/rounding/data integrity |
| `NFR-USE-001` | `NFS-USE-001` | `NFR-TEST-USE-001` | Usability/localization/semantic consistency |
| `NFR-PLT-001` | `NFS-PLT-001` | `NFR-TEST-PLT-001` | Web desktop platform support |

### 2.1 Ngoài phạm vi P1

Các NFR sau không phải acceptance obligation của P1: `NFR-AVL-002`, `NFR-PER-002`, `NFR-PER-003`, `NFR-MNT-001`, `NFR-CMP-001`, `NFR-FRESH-001`.

Việc một NFR P1 tham chiếu NFR phase sau không tự kéo NFR phase sau vào P1. Ví dụ `NFR-AUD-001` tham chiếu `NFR-CMP-001` nhưng P1 chỉ cần một retention baseline tạm thời, được kiểm soát, đủ cho acceptance của audit evidence.

## 3. Quy ước đặc tả và đo lường

### 3.1 Measurement scope

Mỗi phép đo NFR phải gắn với một scope có thể tái lập, tối thiểu gồm:

- phiên bản application/build;
- environment;
- Company/service scope khi áp dụng;
- dataset/profile version;
- thời gian đo và timezone;
- cấu hình/policy có hiệu lực;
- measurement method/tool version;
- người/chủ thể xác nhận kết quả.

### 3.2 Pass/fail

Một NFR chỉ được xem là đạt khi toàn bộ success criteria áp dụng cho P1 có evidence PASS. Criterion có conditional applicability chỉ được đưa vào acceptance khi capability/source tương ứng thực sự nằm trong approved scope.

### 3.3 Evidence retention

Evidence của verification phải có định danh, thời điểm, scope, build/version và kết quả đủ để tái kiểm. Thời hạn lưu evidence chịu `NFS-AUD-001` và controlled P1 audit-evidence retention baseline cho tới khi `NFR-CMP-001` có hiệu lực ở phase sau.

---

## 4. NFS-SEC-001 — Bảo vệ dữ liệu khỏi truy cập, sửa đổi hoặc xuất trái thẩm quyền

```yaml
id: "NFS-SEC-001"
source_requirement: "NFR-SEC-001"
target_phase: "P1_CORE"
priority: MUST
depends_on:
  - "BR-IAM-003"
  - "BR-IAM-005"
planned_verified_by:
  - "NFR-TEST-SEC-001"
status: draft
```

### 4.1 Mục tiêu

Bảo đảm mọi surface thuộc P1 áp dụng cùng authorization invariant: quyền cơ sở đến từ Resource/Action Permission và kết quả cuối cùng phải theo canonical Authorization Evaluation. Không surface nào được tạo đường truy cập, sửa đổi hoặc export vượt quyền chỉ vì dùng UI/API hoặc cơ chế truy xuất khác.

### 4.2 Phạm vi áp dụng

NFS áp dụng cho mọi surface thực sự thuộc P1 và có khả năng đọc/ghi/xuất dữ liệu, bao gồm khi tồn tại:

- màn hình nghiệp vụ;
- API/data-access endpoint;
- chức năng truy xuất dữ liệu nội bộ;
- export surface thuộc P1;
- chức năng quản trị P1 có truy cập business/sensitive data.

Report/document/export capability chưa nằm trong approved scope không phải triển khai giả chỉ để kiểm thử NFR này.

### 4.3 Quality contract

1. Mọi request truy cập dữ liệu phải resolve được User Identity, Group membership/scope, Company/Organization Context khi áp dụng và Resource/Action tương ứng.
2. Quyết định cuối phải dùng canonical authorization semantics của `FR-IAM-003`; surface không được tự triển khai một permission semantics cạnh tranh.
3. Sensitive data action phải đồng thời thỏa `FR-IAM-005`; quyền xem không mặc nhiên cấp quyền export hoặc sửa.
4. Cross-Group và cross-Company access phải bị từ chối khi context/scope tương ứng không được cấp.
5. Không được bỏ qua authorization chỉ vì request đi qua API, batch/admin surface hoặc đường truy cập khác trong P1.
6. Deny result phải không trả về dữ liệu nghiệp vụ nhạy cảm ngoài thông tin lỗi tối thiểu cần thiết.

### 4.4 Security surface register

Trước acceptance phải có một controlled `P1 Security Surface Register` liệt kê tối thiểu:

| Trường | Yêu cầu |
|---|---|
| Surface ID | Định danh ổn định của surface |
| Surface type | UI / API / data access / export / admin |
| Resource | Resource canonical |
| Action | Action canonical |
| Data class | Normal / sensitive khi áp dụng |
| Group/Company context | Context phải resolve |
| Required Permission | Permission gate |
| Policy required | Theo metadata canonical của Permission |
| Verification coverage | Test case liên quan |

Register là verification scope; việc thêm surface P1 mới phải kéo theo security verification tương ứng.

### 4.5 Observability và evidence

Evidence phải đủ để giải thích ít nhất: actor/User, Group/Company context, Resource/Action, Permission gate, applicable policy/effect, final decision và timestamp. Evidence không yêu cầu expose implementation secret cho end user nhưng phải đủ cho kiểm tra có thẩm quyền.

### 4.6 Boundary

- Không yêu cầu triển khai `BR-IAM-006` security-event monitoring trong P1.
- Không yêu cầu report/document/export capability phase sau chỉ để tạo test surface.
- NFS này không tự định nghĩa authentication mechanism, MFA, password policy hoặc cryptographic standard vì baseline nguồn chưa quy định.

### 4.7 TBD có kiểm soát

- `NE-TBD-SEC-001`: danh mục đầy đủ P1 Security Surface Register.
- `NE-TBD-SEC-002`: policy về nội dung lỗi/deny response cho UI và API.
- `NE-TBD-SEC-003`: test identity/data fixture chuẩn cho cross-Group/cross-Company/sensitive-data verification.

---

## 5. NFS-AVL-001 — Đảm bảo availability trong khung giờ vận hành

```yaml
id: "NFS-AVL-001"
source_requirement: "NFR-AVL-001"
target_phase: "P1_CORE"
priority: MUST
depends_on: []
planned_verified_by:
  - "NFR-TEST-AVL-001"
status: draft
```

### 5.1 Mục tiêu

Đạt monthly availability tối thiểu **99.5%** trong configured operating window đã công bố cho Company/service scope thuộc acceptance.

### 5.2 Operating-window contract

Mỗi Company/service scope phải có bản ghi controlled gồm:

- operating window có hiệu lực;
- business timezone;
- effective-from/effective-to;
- version;
- owner/approver;
- lịch planned maintenance nếu có.

Thay đổi operating window không được hồi tố làm thay đổi kết quả availability đã công bố.

### 5.3 Measurement contract

Availability tháng được tái tính từ các thành phần sau:

- tổng thời gian thuộc configured operating window trong tháng;
- downtime bắt đầu/kết thúc;
- planned maintenance đủ điều kiện loại trừ;
- timezone dùng để xác định measurement window.

Planned maintenance chỉ được loại khỏi phép đo khi **đồng thời**:

1. đã được phê duyệt theo policy; và
2. đã được thông báo trước tối thiểu **48 giờ**.

Khoảng downtime không thỏa cả hai điều kiện trên phải tính vào downtime của availability.

### 5.4 Evidence contract

Mỗi kỳ đo phải lưu đủ:

- measurement period;
- Company/service scope;
- operating-window version;
- timezone;
- danh sách downtime interval;
- danh sách maintenance interval và evidence approval/notification;
- phép tính availability;
- kết quả PASS/FAIL so với 99.5%.

### 5.5 Boundary

- Business continuity/DR (`NFR-AVL-002`) thuộc P2, không phải acceptance obligation P1.
- NFS này không tự đặt RPO/RTO cho P1.

### 5.6 TBD có kiểm soát

- `NE-TBD-AVL-001`: Company/service scope cụ thể dùng cho P1 acceptance.
- `NE-TBD-AVL-002`: nguồn telemetry canonical và quy tắc gộp/khử trùng downtime event.
- `NE-TBD-AVL-003`: quy tắc xử lý partial outage/degraded service trong phép đo availability.

---

## 6. NFS-PER-001 — Đáp ứng hiệu năng tương tác vận hành

```yaml
id: "NFS-PER-001"
source_requirement: "NFR-PER-001"
target_phase: "P1_CORE"
priority: MUST
depends_on:
  - "BR-ORG-002"
planned_verified_by:
  - "NFR-TEST-PER-001"
status: draft
```

### 6.1 Mục tiêu hiệu năng

Trong controlled P1 workload acceptance:

| Loại tương tác | Metric | Ngưỡng |
|---|---|---:|
| Tra cứu/điều hướng thông thường | p95 response time | `<= 2 giây` |
| Tạo/cập nhật giao dịch thông thường | p95 response time | `<= 3 giây` |
| Truy vấn nghiệp vụ phức tạp | p95 response time | `<= 5 giây` |

Tác vụ dự kiến vượt interaction class có thể chạy async nếu người dùng nhận được trạng thái tiến trình, kết quả và lỗi rõ ràng.

### 6.2 Controlled P1 performance data profile

Dataset tối thiểu:

| Data class | Baseline tối thiểu |
|---|---:|
| Product | 100,000 |
| Customer | 50,000 |
| Supplier | 10,000 |
| Inventory Transaction | 500,000 |
| Sales Order | 250,000 |
| Purchase Order | 100,000 |
| Concurrent users | 100 |

Dataset/profile phải có version và generation/load evidence để lần test khác có thể tái lập baseline tương đương.

### 6.3 Workload contract

Workload acceptance phải:

1. sử dụng **100 concurrent users**;
2. có controlled workload mix;
3. phân loại request vào đúng 3 response-time class;
4. ghi rõ think time/pacing nếu có;
5. không loại bỏ failed request khỏi thống kê mà không giải thích;
6. giữ test environment và measurement method đủ để retest.

### 6.4 Measurement contract

- Response time được đo theo cùng điểm bắt đầu/kết thúc đã kiểm soát cho mỗi interaction class.
- p95 được tính trên request thuộc cùng class và measurement window hợp lệ.
- Warm-up, ramp-up và interval bị loại khỏi measurement phải được ghi rõ nếu áp dụng.
- Error rate, timeout và resource saturation phải được ghi cùng kết quả p95 để tránh kết luận PASS từ mẫu thành công không đại diện.

### 6.5 Async contract

Nếu một tác vụ được thiết kế async vì vượt interaction class, UI/surface phải cung cấp tối thiểu:

- trạng thái đang xử lý;
- kết quả khi hoàn thành;
- lỗi/thất bại rõ ràng;
- khả năng truy lại job/result trong phạm vi retention vận hành phù hợp.

NFS nguồn không đặt SLA hoàn thành cho async task; nếu cần phải được phê duyệt bằng requirement/NFR riêng hoặc controlled policy.

### 6.6 TBD có kiểm soát

- `NE-TBD-PER-001`: workload mix chi tiết theo P1 transaction/use case.
- `NE-TBD-PER-002`: danh mục interaction gán vào normal lookup / normal create-update / complex query.
- `NE-TBD-PER-003`: test environment sizing và mức tương đương với acceptance environment.
- `NE-TBD-PER-004`: error-rate/timeout acceptance threshold nếu cần ngoài response-time criteria nguồn.

---

## 7. NFS-AUD-001 — Bảo toàn, truy vấn và tái lập audit evidence

```yaml
id: "NFS-AUD-001"
source_requirement: "NFR-AUD-001"
target_phase: "P1_CORE"
priority: MUST
depends_on:
  - "BR-AUD-001"
references:
  - "NFR-CMP-001"
planned_verified_by:
  - "NFR-TEST-AUD-001"
status: draft
```

### 7.1 Mục tiêu

Audit evidence của capability P1 phải không thể sửa/xóa qua luồng nghiệp vụ thông thường, vẫn queryable trong retention baseline có hiệu lực và đủ để tái lập ngữ cảnh actor, thời điểm, scope và thay đổi liên quan.

### 7.2 Integrity contract

1. Business flow thông thường không được update/delete audit evidence đã phát sinh.
2. Nếu privileged administration đối với audit evidence được policy cho phép, hành động đó phải tự tạo evidence về actor, thời điểm, lý do và kết quả.
3. Verification phải có khả năng phát hiện mẫu tampering hoặc thiếu chuỗi evidence theo `NFR-TEST-AUD-001`.
4. Hủy, thay thế, đảo hoặc điều chỉnh business record không được làm mất audit history đã có.
5. Exception flow hợp lệ không được xóa dấu vết trước exception.

### 7.3 Queryability và reconstruction contract

Từ một sample transaction P1 phải có thể truy ra chuỗi audit evidence đủ để xác định khi áp dụng:

- object/business record;
- before/after hoặc event/action context;
- actor/User;
- Company/Organization scope;
- timestamp;
- reason;
- approval/attachment reference nếu có;
- relation tới record cũ/mới khi hủy, thay thế hoặc điều chỉnh.

Report/decision surface phase sau không phải tạo giả trong P1 để thỏa reconstruction requirement.

### 7.4 Controlled P1 audit-evidence retention baseline

Do `NFR-CMP-001` chưa thuộc P1, P1 phải có một baseline riêng được phê duyệt trước acceptance, tối thiểu xác định:

| Thuộc tính | Trạng thái v0.1.0 |
|---|---|
| Record classes áp dụng | P1 audit evidence |
| Retention duration | `TBD - bắt buộc phê duyệt trước acceptance` |
| Start event | `TBD` |
| Owner | `TBD` |
| Purge authority | `TBD` |
| Legal/compliance override | Chỉ áp dụng khi controlled source/policy tương ứng được phê duyệt |

Tài liệu này **không tự đặt** thời hạn retention vì User Requirements P1 chưa cung cấp giá trị đó.

### 7.5 Evidence contract

Verification evidence phải gồm kết quả immutability test, privileged-action test khi áp dụng, tamper-detection test, query/reconstruction test và retention-baseline reference.

### 7.6 TBD có kiểm soát

- `NE-TBD-AUD-001`: P1 audit-evidence retention duration và start event.
- `NE-TBD-AUD-002`: privileged maintenance actions được phép trên audit store, nếu có.
- `NE-TBD-AUD-003`: tamper/integrity detection mechanism và verification oracle.
- `NE-TBD-AUD-004`: performance/query limit cho audit retrieval nếu cần một NFR riêng.

---

## 8. NFS-DAT-001 — Bảo toàn precision và rounding của dữ liệu định lượng

```yaml
id: "NFS-DAT-001"
source_requirement: "NFR-DAT-001"
target_phase: "P1_CORE"
priority: MUST
depends_on:
  - "BR-MDM-001"
  - "BR-FIN-005"
planned_verified_by:
  - "NFR-TEST-DAT-001"
status: draft
```

### 8.1 Quantity precision

1. Quantity phải hỗ trợ tối thiểu **6 decimal places** khi Product/UOM policy yêu cầu.
2. Source quantity phải được giữ trước bước rounding đã công bố.
3. UOM conversion phải có thể truy lại source quantity, factor và converted quantity theo `FR-PRD-002`.

### 8.2 Rate và unit-price precision

Unit price, exchange rate và các rate khác phải giữ đủ internal precision để kết quả monetary posting sau cùng đáp ứng currency/financial rounding policy. Baseline nguồn chưa chỉ định số decimal internal cụ thể; giá trị implementation phải được kiểm soát và chứng minh bằng verification.

### 8.3 Monetary rounding

1. Monetary posting được làm tròn theo minor unit của currency áp dụng.
2. Khi cần đối soát phải giải thích được original value, pre-rounding value và final rounded value.
3. Reconciliation tolerance mặc định không vượt quá **1 minor unit của currency trên một chứng từ**, trừ khi controlled specialized policy được phê duyệt quy định khác.

### 8.4 Conditional rules

- Tax rounding theo `BR-FIN-004` chỉ trở thành acceptance obligation khi tax capability nằm trong approved scope.
- Payroll/legal-specialized rounding chỉ áp dụng khi capability tương ứng được approved.
- Không tạo tax/payroll test giả trong P1 chỉ để thỏa NFS này.

### 8.5 Evidence contract

Evidence cần giữ input raw value, conversion factor/rate khi áp dụng, intermediate/pre-round value, rounding rule/policy, final value và reconciliation delta.

### 8.6 TBD có kiểm soát

- `NE-TBD-DAT-001`: internal numeric precision/scale theo data class ngoài minimum quantity requirement.
- `NE-TBD-DAT-002`: rounding mode cụ thể khi currency/policy nguồn không chỉ định.
- `NE-TBD-DAT-003`: treatment của cumulative rounding across multi-line transaction nếu cần ngoài tolerance nguồn.

---

## 9. NFS-USE-001 — Đảm bảo usability, localization và semantic consistency

```yaml
id: "NFS-USE-001"
source_requirement: "NFR-USE-001"
target_phase: "P1_CORE"
priority: MUST
depends_on:
  - "BR-CTL-001"
  - "BR-ORG-004"
  - "BR-SYS-001"
planned_verified_by:
  - "NFR-TEST-USE-001"
status: draft
```

### 9.1 Mục tiêu

Người dùng đại diện có thể hiểu và sử dụng thuật ngữ/đơn vị hiển thị nhất quán; thay đổi locale/display chỉ thay đổi cách hiển thị chứ không thay đổi semantic/canonical business value.

### 9.2 Terminology contract

P1 phải có controlled business terminology glossary tối thiểu gồm:

- canonical term;
- thuật ngữ hiển thị theo ngôn ngữ/locale được hỗ trợ;
- định nghĩa nghiệp vụ;
- owner;
- version/effective time khi cần.

Người dùng đại diện phải xác nhận thuật ngữ và đơn vị hiển thị trong acceptance scope.

### 9.3 Localization invariant

Thay đổi locale/display setting không được làm thay đổi:

- canonical business timestamp;
- business date/period meaning;
- business/base/functional currency;
- monetary value;
- Company Business Calendar meaning.

Cùng một transaction có thể hiển thị khác theo locale nhưng phải resolve về cùng canonical timestamp/currency/value.

### 9.4 Conditional HR rule

Khi HR work calendar nằm trong approved scope, ngày làm việc/ngày nghỉ phải theo work-calendar policy có hiệu lực và không thay đổi chỉ do user locale. Đây chưa phải acceptance obligation của P1 hiện tại.

### 9.5 Usability evidence

Verification phải có evidence từ representative users và controlled glossary/version. Baseline nguồn chưa quy định SUS score, task-completion percentage hoặc accessibility standard; tài liệu này không tự thêm các metric đó.

### 9.6 TBD có kiểm soát

- `NE-TBD-USE-001`: danh sách locale/ngôn ngữ P1 được hỗ trợ chính thức.
- `NE-TBD-USE-002`: nhóm representative users và phương pháp xác nhận terminology/display units.
- `NE-TBD-USE-003`: owner và change-control workflow của terminology glossary.

---

## 10. NFS-PLT-001 — Cung cấp ERP trên nền tảng web desktop

```yaml
id: "NFS-PLT-001"
source_requirement: "NFR-PLT-001"
target_phase: "P1_CORE"
priority: MUST
depends_on: []
planned_verified_by:
  - "NFR-TEST-PLT-001"
status: draft
```

### 10.1 Supported platform baseline

Các chức năng P1 phải vận hành trên desktop web với:

- Google Chrome: current stable và 2 major versions gần nhất tại thời điểm acceptance;
- Microsoft Edge: current stable và 2 major versions gần nhất tại thời điểm acceptance;
- Windows 10/11 hoặc Windows desktop version còn nằm trong Dinktech support baseline tại thời điểm acceptance.

Mobile/tablet không phải môi trường được hỗ trợ chính thức trong P1 nếu chưa có requirement riêng.

### 10.2 Controlled browser/OS matrix

Mỗi đợt acceptance phải snapshot matrix cụ thể gồm:

| Browser | Version | OS | OS version/build | Build under test | Kết quả |
|---|---|---|---|---|---|
| Chrome | resolved at test time | Windows | resolved at test time | build ID | PASS/FAIL |
| Edge | resolved at test time | Windows | resolved at test time | build ID | PASS/FAIL |

Các version cụ thể không được hard-code vĩnh viễn trong NFS vì baseline nguồn dùng quy tắc relative-to-acceptance-time.

### 10.3 Compatibility contract

Trên mỗi combination thuộc controlled matrix, các P1 acceptance-critical flows phải không có lỗi làm mất khả năng hoàn thành nghiệp vụ hoặc làm sai dữ liệu/authorization. Lỗi trình bày không ảnh hưởng nghiệp vụ được phân loại theo defect policy riêng; baseline nguồn chưa quy định defect severity threshold cụ thể.

### 10.4 TBD có kiểm soát

- `NE-TBD-PLT-001`: Dinktech Windows support baseline áp dụng tại thời điểm acceptance.
- `NE-TBD-PLT-002`: browser/OS combination cụ thể sau khi resolve current stable và hai major versions gần nhất.
- `NE-TBD-PLT-003`: defect severity/compatibility acceptance policy nếu cần ngoài functional acceptance.

---

## 11. Ma trận tương tác giữa các NFS P1

| NFS | Phụ thuộc/ảnh hưởng chính |
|---|---|
| `NFS-SEC-001` | dùng IAM authorization semantics; evidence liên quan được bảo toàn theo audit |
| `NFS-AVL-001` | dùng Company/service scope và timezone controlled |
| `NFS-PER-001` | test data/profile phải giữ isolation/authorization đúng khi load test |
| `NFS-AUD-001` | giữ evidence cho business/audit context và verification artifacts |
| `NFS-DAT-001` | ảnh hưởng Product UOM, Pricing, FX, Inventory và transaction calculations |
| `NFS-USE-001` | phụ thuộc lifecycle/locale/business-time semantics của FRS P1 |
| `NFS-PLT-001` | compatibility matrix phải chạy các critical P1 flows và security invariants |

Không NFS nào trong bảng trên được dùng để tạo thêm business capability ngoài FRS P1.

## 12. Controlled Open Decisions — Wave E NFS

| ID | Quyết định cần khóa | NFS ảnh hưởng | Owner/artifact dự kiến |
|---|---|---|---|
| `NE-TBD-SEC-001` | P1 Security Surface Register | SEC | IAM/Security + Verification |
| `NE-TBD-SEC-002` | Deny/error disclosure policy | SEC | Security/API policy |
| `NE-TBD-SEC-003` | Cross-scope/sensitive test fixtures | SEC | Test data specification |
| `NE-TBD-AVL-001` | P1 Company/service acceptance scope | AVL | Ops/Release baseline |
| `NE-TBD-AVL-002` | Canonical availability telemetry | AVL | Ops/Observability design |
| `NE-TBD-AVL-003` | Partial outage/degraded-service rule | AVL | Availability measurement policy |
| `NE-TBD-PER-001` | Controlled workload mix | PER | Performance test profile |
| `NE-TBD-PER-002` | Interaction-class catalog | PER | FRS/NFS performance mapping |
| `NE-TBD-PER-003` | Acceptance environment sizing | PER | Environment specification |
| `NE-TBD-PER-004` | Error/timeout threshold nếu cần | PER | NFR revision/policy |
| `NE-TBD-AUD-001` | P1 audit retention duration/start event | AUD | Controlled retention baseline |
| `NE-TBD-AUD-002` | Privileged audit-store maintenance | AUD | Security/Audit design |
| `NE-TBD-AUD-003` | Tamper-detection mechanism/oracle | AUD | Audit design + verification |
| `NE-TBD-AUD-004` | Audit query performance target nếu cần | AUD | NFR revision |
| `NE-TBD-DAT-001` | Internal precision/scale | DAT | Data design/NFS |
| `NE-TBD-DAT-002` | Default rounding mode | DAT | Data/Finance policy |
| `NE-TBD-DAT-003` | Multi-line cumulative rounding handling | DAT | Data/Finance policy |
| `NE-TBD-USE-001` | Supported locale/language set | USE | Product/System policy |
| `NE-TBD-USE-002` | Representative-user cohort/method | USE | UAT/UX verification plan |
| `NE-TBD-USE-003` | Glossary owner/change control | USE | Product governance |
| `NE-TBD-PLT-001` | Windows support baseline | PLT | Platform support policy |
| `NE-TBD-PLT-002` | Concrete browser/OS matrix | PLT | Acceptance test plan |
| `NE-TBD-PLT-003` | Compatibility defect threshold | PLT | Test/Release policy |

## 13. NFS implementation-readiness gate

Bộ NFS P1 có thể chuyển từ draft sang implementation-ready candidate khi:

1. đủ 7/7 P1 NFR có đúng một `NFS-*` và giữ traceability upstream/downstream;
2. Security Surface Register được khóa cho toàn bộ P1 surfaces;
3. availability scope, operating-window source và telemetry measurement được khóa;
4. performance workload mix, interaction-class catalog, data-profile versioning và environment profile được khóa;
5. P1 audit-evidence retention baseline có duration/start event/owner được phê duyệt;
6. internal precision/rounding decisions đủ để implement và verify `NFR-DAT-001`;
7. supported locale/language và representative-user verification cohort được khóa;
8. browser/OS matrix được resolve tại acceptance time theo relative-version rule;
9. không `TBD` nào còn khả năng làm thay đổi pass/fail semantics của NFR mà chưa có controlled decision;
10. không NFS nào kéo NFR/capability P2/P3 thành nghĩa vụ P1.

## 14. Revision Notes

### v0.1.0 — Initial P1 Non-functional Specification

- Tạo 7 `NFS-*` từ 7 P1 NFR của `REQ-ERP-001 v1.0.2`.
- Giữ ngôn ngữ chính là tiếng Việt và giữ canonical English terms/metrics khi cần.
- Đặc tả Security Surface Register, availability measurement evidence, controlled performance profile, audit-integrity/queryability contract, precision/rounding contract, localization invariant và browser/OS matrix.
- Không tự đặt P1 audit retention duration, performance workload mix, internal precision, locale set hoặc browser versions cụ thể khi source chưa quyết định; các điểm này được đưa vào `NE-TBD-*`.
- Không promote DR, reporting freshness/capacity, configurability, compliance-retention enterprise baseline hoặc data freshness từ P2 vào P1.
