<?php
require_once '../../../includes/SimpleXLSXGen.php';

$header = ['MSSV', 'Họ tên', 'Giới tính', 'Tên Khoa / Mã khoa', 'Lớp', 'Khóa', 'SĐT', 'Email', 'Địa chỉ'];
$sampleByName = ['SV001', 'Nguyễn Văn A', 'Nam', 'Khoa Công nghệ Thông tin', 'D19CNTT01', '2019', '0901234567', 'sv001@example.com', 'Hà Nội'];
$sampleByCode = ['SV002', 'Trần Thị B', 'Nữ', 'CNTT', 'D20CNTT02', '2020', '0912345678', 'sv002@example.com', 'Đà Nẵng'];

$xlsx = Shuchkin\SimpleXLSXGen::fromArray([$header, $sampleByName, $sampleByCode]);
$xlsx->downloadAs('Mau_Import_SinhVien.xlsx');
