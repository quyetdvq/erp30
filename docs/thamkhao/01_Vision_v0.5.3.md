# ERP Dinktech - Dinktech Mini ERP V2.0

## Tài liệu Tầm nhìn (Vision)

> **Mục đích tài liệu**\
> Tài liệu này mô tả ý tưởng ban đầu và định hướng tổng thể cho hệ thống
> quản trị Dinktech. Nội dung nhằm tạo một cách hiểu chung về lý do xây
> dựng hệ thống, giá trị mong muốn, phạm vi nghiệp vụ ở mức khái quát,
> nguyên tắc định hướng và lộ trình phát triển dự kiến.
>
> **Giới hạn tài liệu**\
> Đây **không phải** tài liệu đặc tả yêu cầu, quy trình nghiệp vụ, mô
> hình dữ liệu, phân quyền chi tiết, thiết kế kiến trúc hay thiết kế kỹ
> thuật. Các nội dung đó sẽ được phân tích và lập thành tài liệu riêng ở
> các giai đoạn tiếp theo.

------------------------------------------------------------------------

# 1. Tổng quan

## 1.1 Tên dự án

**ERP Dinktech - Dinktech Mini ERP V2.0**

## 1.2 Định hướng sản phẩm

ERP Dinktech được định hướng là một hệ thống ERP trên nền tảng web, phục
vụ hoạt động sản xuất, thương mại và quản trị nội bộ.

Hệ thống hướng tới việc tạo một nền tảng quản trị thống nhất có khả năng
phục vụ mô hình đa tập đoàn (multi-group).

Mỗi tập đoàn có thể bao gồm nhiều công ty, chi nhánh, nhà máy, kho và
các kênh bán trong cùng một hệ sinh thái quản trị. Nền tảng cũng được
định hướng có khả năng phục vụ nhiều tập đoàn độc lập tương tự Dinktech
trong cùng một hệ thống.

Khái niệm Tập đoàn trong Vision được hiểu là một Organization Context
Type cấp cao trong mô hình tổ chức ERP, không phải là một khái niệm chỉ
dành riêng cho Dinktech.

Sản phẩm được định hướng theo các đặc điểm chính:

-   Tinh gọn và phù hợp với nhu cầu thực tế của doanh nghiệp.
-   Tổ chức theo module để có thể phát triển từng bước.
-   Dữ liệu được quản lý thống nhất giữa các bộ phận.
-   Có khả năng mở rộng theo quy mô tổ chức và hoạt động kinh doanh.
-   Dễ sử dụng, bảo trì và phát triển lâu dài.

## 1.3 Đối tượng sử dụng chính

Hệ thống được định hướng phục vụ các nhóm người dùng liên quan đến hoạt
động quản trị và vận hành doanh nghiệp, bao gồm:

-   Ban điều hành và chủ sở hữu doanh nghiệp.
-   Bộ phận mua hàng, cung ứng và quản lý nhà cung cấp.
-   Bộ phận kho, logistics và giao nhận.
-   Bộ phận kế hoạch, sản xuất và kiểm soát chất lượng.
-   Bộ phận kinh doanh, chăm sóc khách hàng và quản lý kênh bán.
-   Bộ phận tài chính, công nợ, thuế và đối soát.
-   Bộ phận nhân sự, chấm công và tiền lương.
-   Người quản trị hệ thống, dữ liệu và kiểm soát nội bộ.

Hệ thống trước hết hướng tới nhu cầu của doanh nghiệp sản xuất và thương
mại quy mô nhỏ và vừa, đồng thời phải có khả năng mở rộng khi Dinktech
phát triển thêm đơn vị, sản phẩm, kênh bán hoặc năng lực sản xuất.

------------------------------------------------------------------------

# 2. Bối cảnh và lý do xây dựng hệ thống

Hoạt động quản lý bằng Excel, file riêng lẻ hoặc nhiều công cụ không
liên thông có thể dẫn đến dữ liệu phân tán, nhập liệu lặp lại, khó đối
soát và khó xác định đâu là thông tin chính thức.

Khi hoạt động của Dinktech mở rộng qua nhiều công ty, chi nhánh, nhà
máy, kho và kênh bán, cách quản lý phân tán sẽ ngày càng khó duy trì và
làm tăng rủi ro sai lệch giữa các bộ phận.

ERP Dinktech được hình thành với định hướng:

-   Tập trung dữ liệu vận hành quan trọng vào một nền tảng chung.
-   Tạo một nguồn thông tin đáng tin cậy phục vụ vận hành và ra quyết
    định.
-   Giảm sai lệch giữa mua hàng, kho, sản xuất, bán hàng, giao nhận và
    tài chính vận hành.
-   Giảm việc nhập lại cùng một dữ liệu ở nhiều nơi.
-   Tăng khả năng truy vết nguồn gốc dữ liệu, trạng thái xử lý và trách
    nhiệm liên quan.
-   Tạo nền tảng để Dinktech mở rộng hoạt động mà không phải nhân bản
    thủ công cách quản lý hiện tại.

------------------------------------------------------------------------

# 3. Tầm nhìn

## 3.1 Tuyên bố tầm nhìn

Xây dựng một hệ thống ERP tinh gọn, theo module, dễ bảo trì và có khả
năng mở rộng, phù hợp với hoạt động sản xuất và thương mại của Dinktech.

Hệ thống hướng tới việc cung cấp một nền tảng quản trị thống nhất cho
các hoạt động chính như mua hàng, tồn kho, sản xuất, bán hàng, giao
nhận, tài chính vận hành, nhân sự và báo cáo; đồng thời đủ đơn giản để
người dùng doanh nghiệp có thể tiếp nhận và sử dụng trong công việc hằng
ngày.

## 3.2 Bức tranh mong muốn

Trong trạng thái mục tiêu, Dinktech có thể sử dụng ERP như một nền tảng
quản trị chung để:

-   Nhìn được dữ liệu quan trọng theo đúng công ty, chi nhánh, nhà máy,
    kho và phạm vi quản lý.
-   Theo dõi xuyên suốt các hoạt động từ mua hàng, nhập kho, sản xuất,
    bán hàng, giao hàng đến thu và chi tiền trong phạm vi được triển
    khai.
-   Biết nguồn gốc của dữ liệu, trạng thái hiện tại và người hoặc đơn vị
    chịu trách nhiệm.
-   Đối soát thông tin giữa các bộ phận trên cùng một nguồn dữ liệu
    thống nhất.
-   Sử dụng báo cáo và dashboard phục vụ vận hành và quản trị trên dữ
    liệu có phạm vi và thời điểm rõ ràng.
-   Mở rộng thêm đơn vị, kênh bán, sản phẩm hoặc năng lực sản xuất mà
    không phải xây dựng lại toàn bộ hệ thống quản lý.

------------------------------------------------------------------------

# 4. Giá trị định hướng

ERP Dinktech được kỳ vọng tạo ra các giá trị chính sau.

## 4.1 Dữ liệu thống nhất

Các thông tin quan trọng về tổ chức, sản phẩm, đối tác, tồn kho, giao
dịch và trạng thái vận hành cần được quản lý trên một nền tảng thống
nhất, hạn chế việc tồn tại nhiều phiên bản dữ liệu không đồng nhất.

## 4.2 Khả năng truy vết

Dữ liệu quan trọng cần có khả năng truy về nguồn nghiệp vụ, phạm vi phát
sinh và trách nhiệm liên quan, đặc biệt đối với tồn kho, lô, serial,
chứng từ, phê duyệt, công nợ và các dữ liệu phục vụ kiểm soát.

## 4.3 Giảm thao tác lặp lại

Hệ thống hướng tới việc giảm nhập liệu nhiều lần giữa các bộ phận và
giữa ERP với các nguồn hoặc đối tác được tích hợp trong tương lai.

## 4.4 Hỗ trợ điều hành

Thông tin phục vụ quản trị cần có phạm vi, thời điểm và nguồn dữ liệu rõ
ràng để hỗ trợ Ban điều hành đánh giá tình hình và ra quyết định.

## 4.5 Kiểm soát phù hợp

Hệ thống cần hỗ trợ kiểm soát theo mức độ rủi ro và trách nhiệm mà không
tạo thêm thủ tục không cần thiết cho hoạt động hằng ngày.

## 4.6 Khả năng mở rộng

Hệ thống cần có khả năng phát triển theo công ty, chi nhánh, nhà máy,
kho, kênh bán và các miền nghiệp vụ mới khi Dinktech có nhu cầu thực tế.

## 4.7 Khả năng duy trì lâu dài

Sản phẩm cần được tổ chức theo hướng rõ ràng, dễ đọc hiểu, kiểm tra, bảo
trì và nâng cấp, với chi phí sở hữu phù hợp với quy mô của Dinktech.

------------------------------------------------------------------------

# 5. Mục tiêu tổng quát

Dự án hướng tới các mục tiêu cấp cao sau:

1.  Giảm phụ thuộc vào Excel và các nguồn dữ liệu rời rạc trong quản lý
    nghiệp vụ cốt lõi.
2.  Chuẩn hóa dữ liệu dùng chung như tổ chức, sản phẩm, khách hàng, nhà
    cung cấp và các danh mục nền tảng.
3.  Nâng độ tin cậy của dữ liệu tồn kho, lô, serial và truy xuất nguồn
    gốc.
4.  Kết nối thông tin giữa mua hàng, kho, sản xuất, bán hàng, giao nhận
    và tài chính vận hành.
5.  Hỗ trợ quản lý sản xuất từ định mức, nhu cầu vật tư đến sản lượng,
    WIP, hao hụt, phế phẩm và chất lượng ở mức phù hợp với Dinktech.
6.  Cung cấp dữ liệu đáng tin cậy về công nợ, thu chi, chi phí, thuế và
    các hoạt động đối soát trong phạm vi triển khai.
7.  Hỗ trợ quản lý dữ liệu nhân sự, chấm công, nghỉ phép, tăng ca và
    tiền lương theo chính sách Dinktech.
8.  Giảm nhập liệu lặp lại giữa các kênh bán, vận chuyển, ngân hàng và
    đối tác khi các tích hợp tương ứng được triển khai.
9.  Cung cấp báo cáo, dashboard và KPI có định nghĩa, nguồn dữ liệu và
    phạm vi sử dụng rõ ràng.
10. Tạo nền tảng để Dinktech có thể mở rộng tổ chức và hoạt động kinh
    doanh trong tương lai.

Các chỉ tiêu định lượng cụ thể, đường cơ sở, phương pháp đo và tiêu chí
nghiệm thu sẽ được xác định trong các tài liệu mục tiêu, yêu cầu hoặc kế
hoạch triển khai tương ứng; không được xem là nội dung cố định của tài
liệu Vision này.

------------------------------------------------------------------------

# 6. Phạm vi định hướng ở mức khái quát

Phần này chỉ xác định các **miền quản trị dự kiến** của hệ thống. Nội
dung chi tiết của từng miền sẽ được phân tích riêng trong tài liệu yêu
cầu và tài liệu nghiệp vụ.

## 6.1 Tổ chức, người dùng, quản trị hệ thống và phân quyền theo phạm vi và trạng thái của dữ liệu

Hỗ trợ mô hình nhiều công ty, chi nhánh, nhà máy, kho và phòng ban; đồng
thời tạo nền tảng cho quản lý người dùng, phạm vi dữ liệu, trạng thái
của dữ liệu, trách nhiệm và các cấu hình chung của hệ thống.

Bao gồm: Phân quyền user, quản trị user, nhân viên,, cây thư mục tổ chức
hành chính ...

## 6.2 Dữ liệu nền dùng chung

Quản lý các dữ liệu dùng xuyên suốt hệ thống như Product Master, khách
hàng, nhà cung cấp, đơn vị tính, địa chỉ, thuế, tiền tệ và các danh mục
chung khác.

Trong Vision này, **Product** trong miền ERP vận hành được hiểu là
**Product Master**. Product Master đại diện cho các đối tượng như vật tư,
vật tư tiêu hao, bán thành phẩm, thành phẩm, sản phẩm bán, hàng hóa
thương mại, combo, quà tặng và các loại item khác được doanh nghiệp quản
lý trong mua hàng, tồn kho, sản xuất, bán hàng và giao nhận.

Product Master quản lý các thuộc tính vận hành như mã, tên, phân loại,
đơn vị tính, quy cách, trọng lượng, kích thước, barcode/QR, chính sách
lot/serial, trạng thái sử dụng và giá bán mặc định. Product Master
**không quản lý dữ liệu thiết kế kỹ thuật hoặc vòng đời phát triển sản
phẩm của PLM**.

Giá bán mặc định thuộc miền Product và được dùng làm giá cơ sở khi không
có chính sách Pricing & Promotion có hiệu lực. Pricing & Promotion chỉ
cần định nghĩa các trường hợp có chính sách giá, chiết khấu hoặc khuyến
mại khác với giá mặc định; không bắt buộc phải đưa toàn bộ Product Master
vào bảng giá riêng.

## 6.3 Mua hàng và nhà cung cấp

Hỗ trợ quản lý nhu cầu mua, làm việc với nhà cung cấp, nhận hàng và các
thông tin liên quan đến công nợ, chất lượng và đánh giá nhà cung cấp.

## 6.4 Kho và tồn kho

Hỗ trợ quản lý nhập, xuất, chuyển, kiểm kê và trạng thái tồn kho; đồng
thời định hướng hỗ trợ lô, serial, truy xuất và kiểm soát chất lượng
hàng tồn.

## 6.5 Kế hoạch và sản xuất

Mô hình sản xuất được định hướng hỗ trợ nhiều hình thức vận hành khác
nhau:

-   Make to Stock.
-   Make to Order.
-   Assembly/Lắp ráp.
-   Manufacturing theo công đoạn.
-   Gia công thuê ngoài.

Ngoài quản lý nghiệp vụ sản xuất, hệ thống có định hướng hỗ trợ quản lý
thông tin vận hành thực tế như máy móc đang chạy và thời gian dừng máy
(Downtime) trong phạm vi được triển khai.

Hỗ trợ các khái niệm chính của sản xuất như định mức, công đoạn, nhu cầu
vật tư, lệnh sản xuất, WIP, sản lượng, hao hụt, phế phẩm, chất lượng và
giá thành theo phạm vi được phê duyệt.

## 6.6 Bán hàng và khách hàng

ERP hỗ trợ mô hình bán hàng đa dạng bao gồm:

-   Website.
-   Shopee.
-   TikTok Shop.
-   Đại lý.
-   Sales trực tiếp.
-   B2B.
-   Nhà phân phối.

Ngoài quản lý bán hàng, hệ thống định hướng hỗ trợ CRM cơ bản gồm:

-   Khách hàng.
-   Lịch sử mua hàng.
-   Chăm sóc khách hàng.
-   Khiếu nại.

Hỗ trợ quản lý giá, báo giá, đơn hàng, khả năng đáp ứng, giao hàng, công
nợ, trả hàng, bảo hành và thông tin chăm sóc khách hàng ở mức phù hợp
với mô hình kinh doanh của Dinktech.

## 6.7 Giao nhận và bán hàng đa kênh

Hỗ trợ kết nối hoạt động bán hàng với giao nhận, vận chuyển, COD, đối
soát và các kênh bán được Dinktech lựa chọn triển khai.

## 6.8 Tài chính vận hành

ERP Dinktech được định hướng là nguồn dữ liệu chính phục vụ tính toán
tài chính vận hành trong phạm vi triển khai.

Phạm vi Finance Operations tập trung vào:

-   Công nợ phải thu, phải trả.
-   Thu chi.
-   Đối soát.
-   Chi phí.

Tài liệu Vision không xác định ERP Dinktech là hệ thống kế toán tổng hợp
đầy đủ hoặc hợp nhất kế toán theo chuẩn kế toán tài chính.

Hỗ trợ dữ liệu phục vụ công nợ phải thu, phải trả, thu chi, tiền, chi
phí, ngân sách, thuế và đối soát trong phạm vi tài chính vận hành của
ERP.

Tài liệu Vision không xác định ERP Dinktech là một hệ thống kế toán tổng
hợp hoặc hợp nhất tập đoàn đầy đủ.

## 6.9 Nhân sự và tiền lương

Hỗ trợ dữ liệu nhân viên, tổ chức, chấm công, nghỉ phép, tăng ca và tiền
lương theo chính sách được Dinktech xác nhận ở từng giai đoạn.

## 6.10 Tài liệu, báo cáo và dữ liệu quản trị

Hỗ trợ lưu trữ và liên kết tài liệu nghiệp vụ, báo cáo vận hành,
dashboard, KPI và các hoạt động nhập/xuất dữ liệu cần thiết cho quản
trị.

------------------------------------------------------------------------

# 7. Định hướng tổ chức và phạm vi quản lý

Hệ thống được định hướng để phục vụ một mô hình doanh nghiệp có thể bao
gồm nhiều cấp tổ chức, chẳng hạn:

-   Tập đoàn.
-   Công ty con hoặc pháp nhân độc lập.
-   Chi nhánh.
-   Nhà máy.
-   Kho.
-   Phòng ban.
-   Chức vụ.
-   Nhân viên

Một người dùng có thể làm việc với một hoặc nhiều phạm vi tổ chức tùy
theo vai trò và trách nhiệm thực tế.

Việc xác định cấu trúc tổ chức chính thức, cách kế thừa phạm vi, mô hình
phân quyền và quy tắc truy cập dữ liệu sẽ được mô tả trong tài liệu
chuyên biệt sau khi mô hình vận hành được xác nhận.

Mô hình tổ chức hỗ trợ chia sẻ dữ liệu theo cơ chế kế thừa hai chiều
theo phạm vi được cấu hình, nhằm phục vụ nhu cầu quản trị dữ liệu dùng
chung giữa các cấp tổ chức.

------------------------------------------------------------------------

# 8. Nguyên tắc định hướng sản phẩm

## 8.1 Một nguồn dữ liệu đáng tin cậy

Mỗi dữ liệu hoặc quy tắc quan trọng cần có nguồn quản lý rõ ràng, hạn
chế việc duy trì cùng một nội dung ở nhiều nơi mà không có cơ chế đồng
bộ.

## 8.2 Nhất quán về thuật ngữ và ý nghĩa

Thuật ngữ, trạng thái và cách đặt tên cần được sử dụng nhất quán giữa
tài liệu, chức năng, giao diện và báo cáo.

## 8.3 Theo module nhưng dùng chung nền tảng

Các miền nghiệp vụ có thể được phát triển thành các module tương đối độc
lập, nhưng phải sử dụng chung các nền tảng về tổ chức, người dùng,
quyền, cấu hình và dữ liệu nền để tránh tạo thành các hệ thống rời rạc.

## 8.4 Phân biệt quyền thao tác và phạm vi dữ liệu, và trạng thái dữ liệu

Mô hình Authorization được định hướng theo Policy Based Access Control
(PBAC). Ở mức khái niệm, quyền truy cập được đánh giá dựa trên User,
Condition, Resource và Action. Ở mức vận hành, Permission theo
Resource/Action được cấp qua Profile; Policy Rule chỉ giới hạn quyền hiệu
lực theo Organization Context, Resource Type, State, Participant,
Condition và Effect, không tạo thêm quyền ngoài Profile.

Hệ thống cần có khả năng phân biệt giữa việc người dùng **được phép làm
gì** và **được phép thực hiện trong phạm vi tổ chức hoặc dữ liệu nào**
và **dữ liệu ở trạng thái nào**.

Cơ chế chi tiết sẽ được xác định trong tài liệu phân quyền và thiết kế
hệ thống.

## 8.5 Cấu hình thay vì tùy biến không kiểm soát

Ưu tiên các chính sách và cấu hình dùng chung có thể áp dụng cho nhiều
đơn vị, thay vì tạo các nhánh xử lý riêng cho từng đơn vị khi không thực
sự cần thiết. Cho phép user tuỳ chỉnh các cấu hình. VD: múi giờ, định
dạng ngày tháng, dấu thập phân, số các số sau dấu phảy,...

## 8.6 Khả năng giải thích và truy vết

Các thay đổi quan trọng cần được lưu lịch sử thay đổi theo cơ chế kiểm
soát checkout/checkin, cho phép theo dõi dữ liệu trước và sau thay đổi.

Việc ghi nhận lịch sử thay đổi không nhất thiết là cơ chế tự động cho
mọi trường hợp mà được kiểm soát theo loại dữ liệu và yêu cầu nghiệp vụ.

Các thay đổi quan trọng cần giữ được khả năng giải thích dữ liệu trước
và sau thay đổi, đặc biệt với các nội dung liên quan đến tồn kho, tài
chính, chất lượng, lương và báo cáo quản trị.

## 8.7 Bảo vệ dữ liệu theo mục đích sử dụng

Thông tin hiển thị và khả năng thao tác cần phù hợp với vai trò, phạm vi
dữ liệu và mức độ nhạy cảm của thông tin.

## 8.8 Không hy sinh kiểm soát để đổi lấy tốc độ ngắn hạn

Tốc độ triển khai là quan trọng nhưng không nên làm mất tính nhất quán,
khả năng kiểm tra hoặc khả năng duy trì lâu dài của hệ thống.

------------------------------------------------------------------------

# 9. Định hướng cấu trúc sản phẩm

Ở mức khái niệm, ERP Dinktech được định hướng gồm hai lớp chính:

### Nền tảng dùng chung và phân quyền

Cung cấp các khả năng dùng xuyên suốt hệ thống như:

-   Organization, phòng ban ... Nhân viên
-   Authorization & Permission.
-   Settings.

### Các dữ liệu và nghiệp vụ dùng chung

-   Địa chỉ địa lý.
-   State, life cycle,
-   Địa chỉ liên hệ \### Các miền nghiệp vụ

Các chức năng kinh doanh được tổ chức thành các module theo miền nghiệp
vụ, ví dụ:

-   Product Master.
-   PLM / Engineering.
-   Pricing & Promotion.
-   Supplier.
-   Inventory.
-   Purchase.
-   Sales.
-   Delivery.
-   Production.
-   Finance Operations.
-   HCM.
-   Document.
-   Approval.
-   Reporting.

Ranh giới khái niệm chính:

-   **Product Master** là dữ liệu vận hành ERP và không phải đối tượng
    thiết kế kỹ thuật của PLM.
-   **PLM / Engineering** là miền độc lập quản lý Engineering Product /
    Development Product, dữ liệu kỹ thuật, version/revision và vòng đời
    trong quá trình thiết kế - phát triển sản phẩm.
-   **Pricing & Promotion** chỉ định nghĩa chính sách giá/chiết khấu/
    khuyến mại khi cần thay đổi so với giá bán mặc định của Product
    Master; nếu không có chính sách phù hợp thì hệ thống dùng giá bán mặc
    định.

Danh sách module trên chỉ thể hiện **định hướng phân chia sản phẩm**,
không phải danh sách chức năng đã chốt. Tên module, ranh giới module và
quan hệ giữa các module sẽ được xác định trong tài liệu kiến trúc và yêu
cầu hệ thống.

------------------------------------------------------------------------

# 10. Kết quả mong đợi

Khi hệ thống được triển khai và ổn định theo đúng định hướng, Dinktech
kỳ vọng có thể:

-   Biết tồn kho thực tế và tồn có thể sử dụng theo phạm vi quản lý.
-   Theo dõi các đơn hàng cần xử lý, giao, thu tiền hoặc giải quyết
    ngoại lệ.
-   Nhận biết nhu cầu vật tư và tình trạng các hoạt động sản xuất trong
    phạm vi được triển khai.
-   Theo dõi các khoản phải thu, phải trả, đã thu, đã trả và chưa đối
    soát.
-   Phân biệt dữ liệu hoặc báo cáo theo trạng thái và thời điểm phù hợp.
-   Biết ai có thẩm quyền, ai thực hiện và ai chịu trách nhiệm đối với
    các nghiệp vụ quan trọng.
-   Truy xuất được dữ liệu và hồ sơ cần thiết phục vụ quản trị, đối
    soát, kiểm toán hoặc xử lý khiếu nại.
-   Hạn chế việc phải duy trì file ngoài cho cùng một số liệu chính
    thức.

Các tiêu chí nghiệm thu, tỷ lệ mục tiêu và phương pháp đo lường sẽ được
xác định riêng sau khi có đường cơ sở và phạm vi triển khai được phê
duyệt.

------------------------------------------------------------------------

# 11. Định hướng lộ trình phát triển

Lộ trình dưới đây chỉ là định hướng ưu tiên ban đầu. Phạm vi thực tế của
từng giai đoạn phụ thuộc vào giá trị kinh doanh, nguồn lực, mức sẵn sàng
dữ liệu và quyết định của Dinktech.

## 11.1 Giai đoạn 1 - Nền tảng ERP cốt lõi

Tập trung vào:

-   Cấu trúc tổ chức và nền tảng quản trị người dùng, nhân viên và phân
    quyền
-   Nhân sự vận hành và tiền lương: hồ sơ nhân viên, chấm công, nghỉ phép,
    tăng ca và tiền lương.
-   Các thiết lập hệ thống: Theo cấp hệ thống, cấp tổ chức và cấp user
    như: Múi giờ, định dạng ngày tháng, ...
-   Dữ liệu dùng chung.
-   Sản phẩm, danh mục sản phẩm, đơn vị tính
-   Mua hàng.
-   Kho.
-   Bán hàng, và đa kênh
-   Kết nối các kênh bán: Shopee, Tiktok
-   Kế nối đơn vị vận chuyển: GHN, GHTK, Viettel Post
-   COD, phí sàn, hoàn tiền và đối soát đối tác.
-   Công nợ cơ bản.
-   Quản lý tài liệu, lịch sử thay đổi và lịch sử phê duyệt
-   Báo cáo vận hành: Tồn kho, công nợ ...

## 11.2 Giai đoạn 2 - Sản xuất và tài chính vận hành mở rộng

Tập trung vào:

-   Định mức và kế hoạch vật tư: BOM sản phẩm ..
-   Lệnh sản xuất và chất lượng.
-   WIP, hao hụt, phế phẩm và giá thành.
-   Đối soát ngân hàng, ngân sách, thuế và chi phí trong phạm vi được
    xác nhận.
-   Báo cáo quản trị và KPI.

## 11.3 Giai đoạn 3 - Mở rộng trải nghiệm và quản lý nội bộ

Tập trung vào:

-   Nhu cầu sử dụng trên thiết bị di động nếu có luận cứ kinh doanh phù
    hợp.
-   Các năng lực quản lý nội bộ bổ sung chưa thuộc phạm vi Phase 1 hoặc
    Phase 2, khi có nhu cầu và chủ sở hữu rõ ràng.

## 11.4 Giai đoạn 4 - Mở rộng năng lực doanh nghiệp

Có thể xem xét:

-   Phân tích nâng cao và dự báo.
-   CRM chuyên sâu.
-   Quản lý tài sản hoặc bảo trì chuyên sâu.
-   Các miền nghiệp vụ mới khi có nhu cầu và chủ sở hữu rõ ràng.

## 11.5 Nguyên tắc ưu tiên

Ưu tiên phát triển cần dựa trên:

-   Giá trị kinh doanh.
-   Mức độ rủi ro cần kiểm soát.
-   Mức sẵn sàng của dữ liệu.
-   Năng lực tiếp nhận của người dùng.
-   Chi phí sở hữu và khả năng duy trì.
-   Sự phụ thuộc giữa các miền nghiệp vụ.

Không nên ưu tiên một chức năng chỉ vì đó là tính năng phổ biến của các
hệ thống ERP khác.

------------------------------------------------------------------------

# 12. Những nội dung chưa phải ưu tiên ban đầu

Các nội dung dưới đây có thể được xem xét trong tương lai nhưng không
phải trọng tâm của Vision giai đoạn đầu:

-   CRM chuyên sâu, quản lý cơ hội bán hàng và marketing automation.
-   HRM chuyên sâu như tuyển dụng, năng lực, đánh giá hiệu suất, đào tạo
    và cổng phúc lợi.
-   Kế toán tổng hợp đầy đủ, hợp nhất tập đoàn hoặc ngân quỹ chuyên sâu.
-   Quản trị bảo trì thiết bị, tài sản chuyên sâu hoặc đội xe nội bộ.
-   Tối ưu tuyến vận tải, hải quan điện tử hoặc khai thuế tự động.
-   Ứng dụng di động độc lập khi chưa có luận cứ kinh doanh rõ ràng.
-   Các kênh, nhà vận chuyển, ngân hàng hoặc đối tác chưa được Dinktech
    lựa chọn.
-   Tự động ra quyết định thay cho người có thẩm quyền đối với các vấn
    đề như giá, tín dụng, chất lượng, lương hoặc nhân sự.
-   Mở rộng sang ngành nghề hoặc quốc gia mới khi chưa có chính sách và
    đánh giá phù hợp.

Danh sách này là định hướng ưu tiên, không phải một cam kết phạm vi cố
định. Phạm vi chính thức sẽ được xác nhận theo từng giai đoạn triển
khai.

------------------------------------------------------------------------

# 13. Tiêu chí thành công ở mức Vision

Ở cấp độ tầm nhìn, dự án được xem là đi đúng hướng khi:

-   Các nghiệp vụ cốt lõi có thể dần được quản lý trên cùng một nền tảng
    thay vì nhiều nguồn rời rạc.
-   Dữ liệu giữa các bộ phận có thể được đối chiếu và truy vết đáng tin
    cậy hơn.
-   Người dùng có thể thực hiện công việc chính mà không phải duy trì
    một hệ thống dữ liệu song song ngoài ERP cho cùng một số liệu chính
    thức.
-   Tồn kho, công nợ, sản xuất, giao nhận và dữ liệu tài chính vận hành
    có mức độ minh bạch cao hơn.
-   Quyền truy cập và phạm vi dữ liệu phù hợp với trách nhiệm của người
    dùng.
-   Báo cáo quản trị có nguồn và thời điểm dữ liệu rõ ràng.
-   Hệ thống có thể tiếp tục mở rộng mà không phải thay đổi toàn bộ nền
    tảng.

Các tiêu chí định lượng, phương pháp đo, đường cơ sở và thời điểm đánh
giá sẽ được xác định trong tài liệu KPI hoặc kế hoạch triển khai riêng.

------------------------------------------------------------------------

# 14. Ranh giới của tài liệu Vision

Tài liệu này chỉ giữ các nội dung ở cấp độ **ý tưởng và định hướng**.

Các chủ đề sau cần được xây dựng thành tài liệu riêng trước khi triển
khai:

-   Business Requirements / Yêu cầu nghiệp vụ.
-   Functional Requirements / Yêu cầu chức năng.
-   Non-functional Requirements / Yêu cầu phi chức năng.
-   Business Processes / Quy trình nghiệp vụ.
-   Data Model / Mô hình dữ liệu.
-   Organization Model / Mô hình tổ chức chi tiết.
-   Role (Group) & Permission Model / Mô hình vai trò và phân quyền.
-   Approval Model / Cơ chế phê duyệt.
-   System Architecture / Kiến trúc hệ thống.
-   Technology Stack / Công nghệ triển khai.
-   Integration Specification / Đặc tả tích hợp.
-   Reporting & KPI Definition / Định nghĩa báo cáo và KPI.
-   Migration & Data Governance / Chuyển đổi và quản trị dữ liệu.
-   Test, Rollout & Operation Plan / Kiểm thử, triển khai và vận hành.

Các nội dung kỹ thuật hoặc nghiệp vụ chi tiết đã được phác thảo trong
giai đoạn ý tưởng có thể được sử dụng làm **đầu vào tham khảo** cho các
tài liệu trên, nhưng không được xem là yêu cầu đã được phê duyệt chỉ vì
từng xuất hiện trong tài liệu Vision ban đầu.

------------------------------------------------------------------------

# 15. Tóm tắt định hướng

ERP Dinktech được định hướng là một nền tảng quản trị doanh nghiệp thống
nhất, tinh gọn và có khả năng mở rộng, phục vụ trọng tâm cho hoạt động
sản xuất và thương mại của Dinktech.

Giá trị cốt lõi của hệ thống không nằm ở việc tập hợp thật nhiều chức
năng, mà ở khả năng tạo ra **dữ liệu thống nhất, khả năng truy vết, kiểm
soát phù hợp và một nền tảng chung có thể phát triển lâu dài**.

Việc xây dựng hệ thống sẽ được thực hiện theo từng giai đoạn. Vision này
là cơ sở định hướng cho các bước phân tích yêu cầu, quy trình, dữ liệu,
kiến trúc và kế hoạch triển khai tiếp theo.

------------------------------------------------------------------------

# Bổ sung định hướng triển khai kỹ thuật ở mức Vision

ERP Dinktech được định hướng triển khai trên mô hình shared hosting với
nền tảng PHP.

Định hướng công nghệ:

-   Backend dựa trên CodeIgniter Shield/Setting.
-   Frontend sử dụng Vue.
-   Người dùng chính sử dụng hệ thống thông qua Web Desktop.

Các lựa chọn công nghệ chi tiết, kiến trúc triển khai và tiêu chuẩn kỹ
thuật sẽ được xác định trong tài liệu System Architecture và Technology
Stack riêng.

------------------------------------------------------------------------

# Phụ lục cập nhật Vision v0.5.0

## 11. ERP Multi Group Model

ERP Dinktech được định hướng là mô hình ERP Multi Group trong cùng một hệ thống.

Khái niệm Tenant không thuộc mô hình nghiệp vụ của ERP Dinktech. Việc phân tách dữ liệu được thực hiện theo Group, Company và Organization Context.

Mô hình tổ chức:

```
GROUP
 |
COMPANY
 |
BRANCH
 |
PLANT
 |
WAREHOUSE
```

## 12. Ownership Principle

ERP áp dụng nguyên tắc sở hữu dữ liệu theo tầng:

| Entity Level | Ownership |
|---|---|
| System Master | Dữ liệu dùng chung toàn hệ thống, không thuộc Organization nào |
| Group Master | Dữ liệu dùng chung cho các Company trong cùng Group |
| Company Master | Dữ liệu phục vụ riêng từng Company |
| Transaction Data | Thuộc đúng một Company và chịu phân quyền theo Organization Context |

Ví dụ:

| Entity | Owner Level |
|---|---|
| Province | System |
| Product Master | Group |
| Product Default Selling Price | Company |
| Warehouse | Company |
| Sales Order | Company |
| Purchase Order | Company |
| Invoice | Company |
| Pricing Policy / Price List | Company |

Các module chi tiết sẽ xác định ownership level của từng loại dữ liệu.

## 13. ERP Platform Foundation

ERP Dinktech có lớp nền tảng dùng chung:

- Organization.
- Employee.
- User Identity.
- Authorization.
- Workflow.
- Audit.
- Configuration.
- Notification.
- Search.
- Document.

Các thành phần nền tảng này phục vụ xuyên suốt các module nghiệp vụ.

## 14. Finance Operations Scope

ERP Dinktech chịu trách nhiệm quản lý vận hành:

- Sales.
- Purchase.
- Inventory.
- Manufacturing.
- CRM.
- Supplier.
- Customer.
- AR/AP.
- Cash.
- Expense.
- Cost.
- Operational Tax.
- Invoice.
- Payment.
- Reconciliation.
- Exchange Rate.
- Period Lock ở mức nghiệp vụ.

ERP chưa bao gồm:

- General Ledger.
- Journal Posting.
- Trial Balance.
- Financial Statements.
- Consolidation Accounting.
- Tax Declaration theo chuẩn pháp lý.

## 15. PLM / Engineering Scope

PLM / Engineering là miền độc lập với Product Master của ERP. Đối tượng
trung tâm của PLM là **Engineering Product / Development Product** được
quản lý trong quá trình nghiên cứu, thiết kế và phát triển sản phẩm; đối
tượng này không phải Product Master dùng cho mua, tồn, bán hoặc giao
nhận.

ERP hỗ trợ PLM / Engineering ở mức phù hợp, bao gồm:

- Engineering Product / Development Product.
- Product Development Lifecycle.
- Engineering Version / Revision.
- Engineering Document.
- Drawing.
- Specification.
- Technical BOM / cấu trúc kỹ thuật khi áp dụng.
- ECR.
- ECN.
- ECO.

Product Master có thể tồn tại và vận hành mà không yêu cầu phải có hồ sơ
PLM tương ứng. Việc tích hợp hoặc chuyển giao dữ liệu giữa PLM và các
miền vận hành ERP, nếu được triển khai, phải được xác định bằng yêu cầu
riêng và không làm hai khái niệm trở thành cùng một master data.

## 16. Manufacturing Scope

ERP Core tập trung:

- Production Order.
- Material.
- WIP.
- Output.
- Cost.

BOM/định mức dùng cho vận hành sản xuất thuộc miền Production và tham
chiếu Product Master của ERP. Production không bắt buộc phụ thuộc vào
Engineering Product hoặc version/revision của PLM để vận hành.

Machine Monitoring, Downtime, OEE và IoT thuộc hướng mở rộng MES trong tương lai. Khi OEE được sử dụng trong báo cáo ERP, OEE được xem là KPI lấy từ nguồn MES phù hợp; ERP Core không có trách nhiệm tự tính OEE trước khi MES được triển khai.

## 17. Phase 1 Scope

Phase 1 bắt buộc:

### Core Platform

- Organization.
- Employee.
- User.
- Authorization.
- Workflow.
- Audit.

### ERP Core

- Product Master.
- Inventory.
- Purchase.
- Sales.
- Production.

### Business Support

- Finance Operation.
- HCM / Payroll.
- Document.
- Reporting.

## 18. Technology Design Boundary

Framework, ngôn ngữ lập trình, hosting và kiến trúc triển khai kỹ thuật không thuộc Vision.

Các nội dung này được chuyển sang:

DES-ERP Technology Design.


---


---

# 19. Baseline quyết định nền tảng - Vision v0.5.3

## 19.1 Mô hình sản phẩm ERP Dinktech

ERP Dinktech V2.0 là hệ thống ERP riêng phục vụ Dinktech. Hệ thống hỗ trợ Dinktech quản lý nhiều Group trong cùng một nền tảng.

Multi Group trong ERP Dinktech được hiểu là khả năng quản trị nhiều thực thể kinh doanh thuộc Dinktech, không phải định hướng trở thành ERP SaaS mở cho nhiều khách hàng độc lập.

## 19.2 Mô hình Group

Group là một Business Entity trong mô hình quản trị ERP. Group có thông tin quản trị riêng như:

- Mã số thuế.
- Tài khoản ngân hàng.
- Người đại diện.
- Báo cáo tài chính và báo cáo quản trị cấp Group.

Group không sở hữu transaction trực tiếp. Mọi transaction nghiệp vụ phải thuộc một Company cụ thể.

## 19.3 Organization Context

Organization Context sử dụng loại cố định:

- GROUP.
- COMPANY.
- BRANCH.
- PLANT.
- WAREHOUSE.

Hierarchy tổ chức không bắt buộc phải tồn tại đầy đủ tất cả cấp. Ví dụ Company -> Warehouse là cấu trúc hợp lệ khi phù hợp với mô hình vận hành.

## 19.4 Data Ownership Model

ERP áp dụng các mức sở hữu dữ liệu:

- System Master.
- Group Master.
- Company Master.
- Transaction Data.

Một số dữ liệu Group Master có thể cho phép Company override theo chính sách Field Level Override.

## 19.5 Authorization Foundation

Authorization được định hướng theo Policy Based Access Control ở mức khái niệm với User, Condition, Resource và Action; ở mức vận hành, mô hình được chuẩn hóa như sau:

- Permission được định nghĩa cố định theo Resource/Action.
- Profile là tập Permission hiệu lực.
- Business Group là nhóm nghiệp vụ hỗ trợ quản trị và ánh xạ tới Profile Permission. Business Group trong Authorization khác với Group Business Entity trong Organization Model; Business Group không thay thế Permission hoặc Policy Rule.
- Policy Rule chỉ giới hạn quyền hiệu lực theo Organization Context, Resource Type, State, Participant, Condition và Effect; Policy không tạo thêm quyền ngoài Profile.
- Condition là điều kiện nghiệp vụ tổng quát được đánh giá trong Policy Rule, không phải một cơ chế cấp quyền độc lập.
- Policy có version và thời gian hiệu lực.

Quy tắc xử lý xung đột quyền thống nhất toàn hệ thống:

ABSOLUTE DENY > ALLOW > DENY

## 19.6 Workflow Governance

Mô hình quản trị Workflow:

Developer
  -> Tạo Workflow Type

System Admin
  -> Triển khai và quản trị Workflow

Business Owner
  -> Cấu hình Rule và Participant

## 19.7 Manufacturing Roadmap

Manufacturing chuyên sâu không phải trọng tâm triển khai đầu tiên. ERP Core cần ổn định trước khi mở rộng Manufacturing thành module chuyên sâu.
