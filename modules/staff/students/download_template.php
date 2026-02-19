<?php
require_once '../../../includes/SimpleXLSXGen.php';

$header = ['MSSV', 'Họ tên', 'Giới tính', 'Tên Khoa', 'Lớp', 'Khóa', 'SĐT', 'Email', 'Địa chỉ'];
$sample = ['SV001', 'Nguyễn Văn A', 'Nam', 'Công nghệ thông tin', 'D19CNTT01', '2019', '0901234567', 'a@example.com', 'Hà Nội'];

$data = [$header, $sample];

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs('Mau_Import_SinhVien.xlsx');
