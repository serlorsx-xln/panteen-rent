<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Demonstration data, dated relative to "today" so the demo always has something to pick up,
 * return, check and report on. Every person, phone number and payment here is fictional.
 * Photos: Pexels (see database/photo-credits.json).
 */
final class DemoSeeder
{
    public const PASSWORDS = ['admin' => 'Admin1234', 'staff' => 'Staff1234', 'member' => 'Member1234'];

    /** [name, slug, colour, description, code of the dress whose photograph stands for the category] */
    private const CATEGORIES = [
        ['รับปริญญา', 'graduation', '#F2D36B', 'ชุดขาวและครีม สุภาพ ใส่ใต้ครุยหรือถ่ายรูปกับเพื่อนวันซ้อมและวันจริง', 'GR-007'],
        ['ราตรี', 'evening', '#5A1E2C', 'ชุดยาวออกงานกลางคืน งานเลี้ยง งานประกาศรางวัล', 'EV-001'],
        ['ชุดไทย', 'thai', '#0F6B4F', 'ชุดไทยผ้าไหมและผ้ายก สำหรับงานบุญ งานหมั้น และงานพิธี', 'TH-001'],
        ['ปาร์ตี้', 'party', '#9CC7E8', 'มินิเดรสและค็อกเทลเดรส สำหรับปาร์ตี้วันเกิดและธีมปาร์ตี้', 'PA-001'],
        ['ไปงานแต่ง', 'wedding', '#F2C4C0', 'เดรสสีพาสเทลสำหรับแขกและเพื่อนเจ้าสาว', 'WE-006'],
    ];

    /** What travels with a dress of each kind (checked at handover and at the return inspection). */
    private const ACCESSORIES = [
        'thai' => 'สไบ, เข็มขัด, ถุงคลุมชุด',
        'evening' => 'ถุงคลุมชุด, ไม้แขวนบุฟองน้ำ',
        'wedding' => 'ผ้าคล้องไหล่, ถุงคลุมชุด',
        'graduation' => 'ถุงคลุมชุด',
    ];

    /** Demo delivery addresses: [member index, label, line1, subdistrict, district, province, postcode]. Not real addresses. */
    private const ADDRESSES = [
        [0, 'คอนโด', '88/12 อาคารตัวอย่าง ชั้น 9 ซอยจุฬาลงกรณ์ 42 (ที่อยู่ตัวอย่าง)', 'วังใหม่', 'ปทุมวัน', 'กรุงเทพมหานคร', '10330'],
        [0, 'บ้านต่างจังหวัด', '15 หมู่ 3 ถนนตัวอย่าง (ที่อยู่ตัวอย่าง)', 'ศรีภูมิ', 'เมืองเชียงใหม่', 'เชียงใหม่', '50200'],
        [2, null, '120/4 ซอยตัวอย่าง 5 (ที่อยู่ตัวอย่าง)', 'สุเทพ', 'เมืองเชียงใหม่', 'เชียงใหม่', '50200'],
        [3, 'หอพัก', '9/9 หอพักตัวอย่าง ห้อง 305 (ที่อยู่ตัวอย่าง)', 'บางกระสอ', 'เมืองนนทบุรี', 'นนทบุรี', '11000'],
        [4, null, '45 ซอยอารีย์ตัวอย่าง (ที่อยู่ตัวอย่าง)', 'สามเสนใน', 'พญาไท', 'กรุงเทพมหานคร', '10400'],
        [5, 'ที่ทำงาน', '1 อาคารสำนักงานตัวอย่าง ชั้น 12 (ที่อยู่ตัวอย่าง)', 'สีลม', 'บางรัก', 'กรุงเทพมหานคร', '10500'],
        [6, null, '77 หมู่ 5 ถนนมิตรภาพ (ที่อยู่ตัวอย่าง)', 'ในเมือง', 'เมืองขอนแก่น', 'ขอนแก่น', '40000'],
        [7, null, '300/7 หมู่บ้านตัวอย่าง (ที่อยู่ตัวอย่าง)', 'บางพลีใหญ่', 'บางพลี', 'สมุทรปราการ', '10540'],
    ];

    /** [code, category slug, name, colour, size, rent, extra/day, deposit, late/day, length, featured, days since it arrived (0 = long-standing stock), description] */
    private const COSTUMES = [
        ['GR-001', 'graduation', 'เดรสเกาะอกขาวทรงเข้ารูป', 'ขาว', 'S', 690, 100, 1000, 150, 38, 0, 0, 'ผ้าเครปเนื้อหนา ไม่บาง ทรงเข้ารูปช่วงเอว ยาวคลุมเข่า ใส่กับครุยได้พอดี'],
        ['GR-002', 'graduation', 'เดรสคอถ่วงแขนกุดสีขาว', 'ขาว', 'M', 590, 100, 1000, 150, 40, 0, 0, 'คอถ่วงทิ้งน้ำหนักสวย ผ้าซาตินด้าน ไม่ยับง่าย ทรงตรงใส่สบายทั้งวัน'],
        ['GR-003', 'graduation', 'เดรสแขนยาวชายขนนก', 'ขาว', 'S', 890, 150, 1000, 150, 36, 0, 40, 'ผ้ายืดหนาเข้ารูป แขนยาว ชายกระโปรงแต่งขนนก ถ่ายรูปขึ้นมาก'],
        ['GR-004', 'graduation', 'มินิเดรสเว้าเอวสีขาว', 'ขาว', 'XS', 590, 100, 800, 100, 33, 0, 0, 'มินิเดรสเว้าข้างเอว ซิปหลัง เหมาะกับวันถ่ายรูปนอกรอบ'],
        ['GR-005', 'graduation', 'แม็กซี่เดรสไหมพรมเปิดไหล่', 'ครีม', 'FREE', 790, 100, 1000, 150, 52, 1, 0, 'ไหมพรมเนื้อนุ่ม ยืดได้ดี เปิดไหล่แขนยาว ฟรีไซส์ใส่ได้หลายหุ่น'],
        ['GR-006', 'graduation', 'ชุดเสื้อลูกไม้กับกระโปรงพลีท', 'ขาว', 'M', 750, 100, 1000, 150, 42, 1, 5, 'เสื้อลูกไม้แขนยาวกับกระโปรงพลีทยาวกลางน่อง แยกชิ้น ปรับใส่กับครุยง่าย'],
        ['GR-007', 'graduation', 'เดรสคล้องคอปักกลีบดอก', 'ขาว', 'S', 850, 150, 1000, 150, 37, 1, 17, 'ผ้าทอลายนูน ปักกลีบดอกไม้ทั้งตัว คล้องคอ ยาวเหนือเข่าเล็กน้อย'],
        ['EV-001', 'evening', 'ราตรีซาตินสีเขียวหัวเป็ด', 'เขียว', 'M', 1890, 300, 2500, 300, 60, 1, 0, 'ซาตินเงาลื่น ยาวกรอมเท้า มีหางชุดสั้น ซิปซ่อนด้านหลัง'],
        ['EV-002', 'evening', 'ราตรีหางปลาปักทอง', 'เขียว', 'S', 2490, 400, 3000, 400, 62, 0, 0, 'หางปลาเข้ารูป ปักดิ้นทองทั้งตัว เหมาะกับงานกาล่าและงานประกาศรางวัล'],
        ['EV-003', 'evening', 'ราตรีเกาะอกหางปลาดอกไม้สามมิติ', 'พีช', 'S', 2290, 400, 3000, 400, 61, 1, 20, 'ดอกไม้สามมิติตัดเย็บมือ เกาะอกมีโครง ไม่หลุดง่าย'],
        ['EV-004', 'evening', 'ราตรีเกาะอกกระโปรงฟูระบาย', 'แดง', 'M', 2190, 300, 2500, 300, 60, 0, 0, 'กระโปรงออร์แกนซ่าซ้อนหลายชั้น ฟูสวยโดยไม่ต้องใส่สุ่ม'],
        ['EV-005', 'evening', 'ราตรีเลื่อมแดงจับเดรปไหล่', 'แดง', 'M', 2590, 400, 3000, 400, 61, 1, 0, 'เลื่อมแดงทั้งตัว ไหล่จับเดรปเป็นประติมากรรม หางปลาเดินง่าย'],
        ['EV-006', 'evening', 'ราตรีซาตินน้ำเงินแขนลูกไม้', 'น้ำเงิน', 'L', 1990, 300, 2500, 300, 62, 0, 0, 'ซาตินน้ำเงินเข้ม แขนลูกไม้โปร่ง ช่วยพรางต้นแขน'],
        ['EV-007', 'evening', 'ราตรีปักลูกปัดแขนพองผ่าข้าง', 'ม่วง', 'S', 2390, 400, 3000, 400, 60, 0, 36, 'ปักลูกปัดทั้งตัว แขนพองเล็ก ผ่าข้างถึงต้นขา เดินแล้วพลิ้ว'],
        ['EV-008', 'evening', 'ราตรีคล้องคอหางปลาสีดำ', 'ดำ', 'M', 1690, 300, 2000, 300, 60, 1, 3, 'ทรงคล้องคอเรียบหรู ผ้ายืดเข้ารูป ใส่ได้หลายงาน'],
        ['TH-001', 'thai', 'ชุดไทยสไบยกดอกทอง ผ้านุ่งไหมเขียวมิ้นต์', 'เขียว', 'FREE', 1490, 200, 2000, 250, 40, 1, 0, 'สไบผ้ายกดอกทองกับผ้านุ่งไหมไทยสีเขียวมิ้นต์ ร้านช่วยแต่งตัวให้ที่ร้าน'],
        ['TH-002', 'thai', 'ชุดไทยสไบลายทองห่มผ้าไหมแดง', 'แดง', 'FREE', 1590, 200, 2000, 250, 40, 0, 0, 'สไบลายทอง ห่มผ้าไหมแดงทับ ผ้านุ่งยกทอง เหมาะงานหมั้นและงานพิธี'],
        ['TH-003', 'thai', 'ชุดไทยเสื้อลูกไม้เทา ผ้าไหมเทอร์ควอยซ์', 'ฟ้า', 'M', 1290, 200, 2000, 250, 41, 0, 27, 'เสื้อลูกไม้สีเทาเงินกับผ้าซิ่นไหมสีเทอร์ควอยซ์ สดใส ถ่ายรูปกลางแจ้งสวย'],
        ['TH-004', 'thai', 'ชุดไทยเสื้อแขนยาวยกดอกเลือดหมู', 'น้ำตาลแดง', 'M', 1390, 200, 2000, 250, 41, 0, 0, 'เสื้อแขนยาวผ้ายกสีเลือดหมูกับผ้าซิ่น ใส่ไปวัดและงานบุญได้สุภาพ'],
        ['TH-005', 'thai', 'ชุดไทยสไบเฉียงไหมชมพูกะปิ', 'ชมพู', 'S', 1190, 200, 2000, 250, 40, 1, 8, 'สไบเฉียงผ้าไหมชมพูกะปิ กระโปรงจีบหน้านาง พร้อมเข็มขัดเงิน'],
        ['TH-006', 'thai', 'ชุดไทยสไบพลีทเหลือง ผ้านุ่งยกทอง', 'เหลือง', 'FREE', 1690, 200, 2000, 250, 40, 1, 0, 'สไบพลีทสีเหลืองทอง ผ้านุ่งยกเขียวทอง โทนสีสดใส'],
        ['PA-001', 'party', 'เดรสกำมะหยี่ชมพูชายเลื่อม', 'ชมพู', 'S', 690, 100, 1000, 100, 35, 1, 0, 'ตัวเสื้อกำมะหยี่แขนยาว กระโปรงพู่เลื่อม ขยับตัวแล้ววิบวับ'],
        ['PA-002', 'party', 'มินิเดรสเปิดไหล่เมทัลลิก', 'เงิน', 'XS', 590, 100, 800, 100, 30, 1, 11, 'ผ้าเมทัลลิกเงินอมทองแต่งพู่ เปิดไหล่ เหมาะธีมดิสโก้'],
        ['PA-003', 'party', 'มินิเดรสซาตินดำแขนระฆัง', 'ดำ', 'M', 490, 100, 800, 100, 33, 0, 0, 'ซาตินดำคอถ่วง แขนระฆัง ใส่ง่าย เข้ากับรองเท้าทุกแบบ'],
        ['PA-004', 'party', 'เดรสลูกไม้แดงแขนยาวกระโปรงชั้น', 'แดง', 'M', 550, 100, 800, 100, 34, 0, 0, 'ลูกไม้แดงทั้งตัว กระโปรงบานซ้อนชั้น ธีมคริสต์มาสและวาเลนไทน์'],
        ['PA-005', 'party', 'เดรสลูกไม้น้ำเงินกระโปรงทูลเล่', 'น้ำเงิน', 'S', 690, 100, 1000, 100, 38, 0, 0, 'เกาะอกลูกไม้น้ำเงินกับกระโปรงทูลเล่ยาวคลุมเข่า'],
        ['PA-006', 'party', 'มินิเดรสเลื่อมชมพูเปิดไหล่', 'ชมพู', 'S', 790, 100, 1000, 100, 30, 1, 23, 'บัสเทียร์เลื่อมชมพูเปิดไหล่ ซิปหลัง มีฟองน้ำในตัว'],
        ['PA-007', 'party', 'มินิเดรสทูลเล่สายขนนก', 'ชมพู', 'XS', 650, 100, 1000, 100, 31, 0, 0, 'ทูลเล่ชมพูอ่อนหลายชั้น สายเดี่ยวแต่งขนนก'],
        ['PA-008', 'party', 'มินิเดรสลายดอกสีเหลือง', 'เหลือง', 'M', 450, 80, 800, 100, 32, 0, 0, 'ผ้าซาตินลายดอกจับย่น สายเดี่ยว สดใสสำหรับปาร์ตี้กลางวัน'],
        ['WE-001', 'wedding', 'เดรสชีฟองชมพูหม่นป้ายอก', 'ชมพู', 'M', 790, 150, 1000, 150, 55, 0, 0, 'ชีฟองสองชั้น ป้ายอกผูกเอว เหมาะกับเพื่อนเจ้าสาว'],
        ['WE-002', 'wedding', 'แม็กซี่เดรสแขนกุดสีม่วงชมพู', 'ม่วง', 'L', 690, 100, 1000, 150, 56, 0, 0, 'ทรงเอไลน์ยาวกรอมเท้า ผ้ายืดเล็กน้อย ใส่สบาย'],
        ['WE-003', 'wedding', 'เดรสคอเหลี่ยมลายดอกเขียวเสจ', 'เขียว', 'S', 650, 100, 1000, 150, 44, 0, 31, 'คอเหลี่ยม แขนตุ๊กตา ลายดอกเล็กบนพื้นเขียวเสจ งานแต่งกลางวัน'],
        ['WE-004', 'wedding', 'แม็กซี่เดรสทูลเล่แขนพอง', 'ชมพู', 'M', 990, 150, 1500, 150, 57, 0, 0, 'ทูลเล่ชมพูอ่อนแขนพอง ยาวคลุมเท้า ถ่ายรูปในสวนสวยมาก'],
        ['WE-005', 'wedding', 'เดรสเกาะอกซาตินเหลืองมะนาว', 'เหลือง', 'S', 890, 150, 1500, 150, 56, 0, 0, 'ซาตินเงาสีเหลืองมะนาว ทรงตรงเกาะอก มีโครงในตัว'],
        ['WE-006', 'wedding', 'เดรสซาตินลาเวนเดอร์กระโปรงบาน', 'ม่วง', 'M', 750, 100, 1000, 150, 40, 1, 14, 'คอหัวใจ กระโปรงบานยาวกลางน่อง สีลาเวนเดอร์อ่อน'],
        ['WE-007', 'wedding', 'แม็กซี่เดรสชีฟองแขนยาวระบายชั้น', 'ชมพู', 'L', 850, 150, 1000, 150, 57, 1, 0, 'ชีฟองชมพูหม่นแขนยาว กระโปรงระบายชั้น ใส่ไปงานเช้าได้สุภาพ'],
    ];

    /** Measurement ranges (inches) per size label: bust min/max, waist min/max, hip max. */
    private const SIZE_RANGES = [
        'XS' => [30, 32, 23, 25, 34], 'S' => [32, 34, 25, 27, 36], 'M' => [34, 36, 27, 29, 38],
        'L' => [36, 38, 29, 31, 40], 'XL' => [38, 40, 31, 33, 42], 'FREE' => [32, 38, 25, 31, 41],
    ];

    private const MEMBERS = [
        // first, last, nickname, email, phone, line, bust, waist, hip, verified, status
        ['พิมพ์ชนก', 'ใจดี', 'พิม', 'member@panteen.test', '0800000001', 'pim.demo', 33, 26, 36, 1, 'active'],
        ['ณัฐธิดา', 'ศรีสุข', 'เฟิร์น', 'fern@panteen.test', '0800000002', 'fern.demo', 32, 25, 35, 1, 'active'],
        ['กมลชนก', 'วงศ์ไทย', 'แพร', 'prae@panteen.test', '0800000003', null, 35, 28, 38, 1, 'active'],
        ['ปวีณา', 'ทองดี', 'มิ้นท์', 'mint@panteen.test', '0800000004', 'mint.demo', 34, 27, 37, 1, 'active'],
        ['ศิริลักษณ์', 'แสงทอง', 'ฝน', 'fon@panteen.test', '0800000005', null, 36, 29, 39, 1, 'active'],
        ['ชลธิชา', 'บุญมา', 'เจน', 'jane@panteen.test', '0800000006', 'jane.demo', 31, 24, 34, 1, 'active'],
        ['อรอุมา', 'พรหมมา', 'ปุ๊ก', 'pook@panteen.test', '0800000007', null, 37, 30, 40, 1, 'active'],
        ['วรรณิศา', 'มีสุข', 'เบล', 'bell@panteen.test', '0800000008', 'bell.demo', 33, 26, 36, 1, 'active'],
        ['ธนพร', 'รุ่งเรือง', 'ข้าวหอม', 'khaohom@panteen.test', '0800000009', null, null, null, null, 0, 'active'],
        ['สุภาวดี', 'คงมั่น', 'ปลา', 'pla@panteen.test', '0800000010', null, 34, 28, 37, 1, 'suspended'],
    ];

    /** @var array<string, int> code => costume id */
    private static array $costumeIds = [];
    /**
     * code => days from today when the dress arrived (negative)
     * @var array<string, int>
     */
    private static array $arrival = [];
    /** @var list<int> */
    private static array $userIds = [];
    private static int $adminId = 0;
    private static int $staffId = 0;

    /** @return array{users: int, costumes: int, bookings: int} */
    public static function run(?callable $log = null): array
    {
        $log ??= fn(string $m) => null;
        $pdo = DB::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['damage_items', 'damage_types', 'booking_photos', 'shipments', 'addresses',
                  'notifications', 'mail_outbox', 'appointments', 'booking_events', 'receipts', 'payments', 'bookings', 'favorites',
                  'costume_images', 'costumes', 'categories', 'login_attempts', 'email_tokens', 'users', 'settings', 'counters'] as $t) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        Settings::flush();
        mt_srand(20261121);

        self::users();
        self::addresses();
        Inspection::seedTypes();
        $log('users: ' . (count(self::$userIds) + 2));
        self::catalog();
        $log('costumes: ' . count(self::$costumeIds));
        $n = self::bookings();
        $n += self::parcels();
        $log("bookings: $n");
        self::appointments();
        self::extras();
        Settings::flush();
        return ['users' => count(self::$userIds) + 2, 'costumes' => count(self::$costumeIds), 'bookings' => $n];
    }

    private static function users(): void
    {
        $hash = fn($p) => password_hash($p, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        self::$adminId = DB::insert('users', [
            'role' => 'admin', 'first_name' => 'เจ้าของร้าน', 'last_name' => 'Panteen', 'nickname' => 'แอดมิน', 'email' => 'admin@panteen.test',
            'phone' => '0800000000', 'password_hash' => $hash(self::PASSWORDS['admin']), 'email_verified_at' => $now,
            'created_at' => date('Y-m-d H:i:s', strtotime('-120 days')),
        ]);
        self::$staffId = DB::insert('users', [
            'role' => 'staff', 'first_name' => 'ภัทรา', 'last_name' => 'หน้าร้าน', 'nickname' => 'แพท', 'email' => 'staff@panteen.test',
            'phone' => '0800000099', 'password_hash' => $hash(self::PASSWORDS['staff']), 'email_verified_at' => $now,
            'created_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
        ]);
        $memberHash = $hash(self::PASSWORDS['member']);
        foreach (self::MEMBERS as $i => $m) {
            self::$userIds[] = DB::insert('users', [
                'role' => 'member', 'status' => $m[10], 'first_name' => $m[0], 'last_name' => $m[1], 'nickname' => $m[2], 'email' => $m[3],
                'phone' => $m[4], 'line_id' => $m[5], 'bust_in' => $m[6], 'waist_in' => $m[7], 'hip_in' => $m[8],
                'height_cm' => $m[6] ? 155 + ($i * 3) % 15 : null,
                'password_hash' => $memberHash, 'email_verified_at' => $m[9] ? $now : null,
                'suspended_reason' => $m[10] === 'suspended' ? 'ค้างคืนชุดและติดต่อไม่ได้ (ข้อมูลตัวอย่าง)' : null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . (90 - $i * 7) . ' days')),
            ]);
        }
    }

    private static function catalog(): void
    {
        $catIds = [];
        foreach (self::CATEGORIES as $i => [$name, $slug, $color, $desc, $cover]) {
            $catIds[$slug] = DB::insert('categories', [
                'name' => $name, 'slug' => $slug, 'color' => $color, 'description' => $desc,
                'cover_image' => 'costumes/' . strtolower($cover) . '.jpg', 'sort_order' => $i + 1,
            ]);
        }
        $credits = [];
        $file = BASE_PATH . '/database/photo-credits.json';
        if (is_file($file)) {
            foreach (json_decode((string) file_get_contents($file), true) as $c) {
                $credits[$c['file']] = 'ภาพ: ' . $c['creator'] . ' · Pexels · ' . $c['source_page_url'];
            }
        }
        foreach (self::COSTUMES as $i => $c) {
            [$code, $slug, $name, $color, $size, $rent, $extra, $deposit, $late, $len, $featured, $new, $desc] = $c;
            $r = self::SIZE_RANGES[$size];
            $id = DB::insert('costumes', [
                'code' => $code, 'category_id' => $catIds[$slug], 'name' => $name, 'description' => $desc, 'color_name' => $color,
                'size_label' => $size, 'bust_min' => $r[0], 'bust_max' => $r[1], 'waist_min' => $r[2], 'waist_max' => $r[3], 'hip_max' => $r[4],
                'length_in' => $len, 'rent_price' => $rent, 'extra_day_price' => $extra, 'deposit' => $deposit, 'late_fee_per_day' => $late,
                'status' => $code === 'PA-004' ? 'maintenance' : 'active', 'is_featured' => $featured,
                'replacement_value' => round($rent * 8, -2), 'accessories' => self::ACCESSORIES[$slug] ?? null,
                'arrived_at' => add_days(today(), $new ? -$new : -(60 + $i * 4)),
            ]);
            self::$costumeIds[$code] = $id;
            self::$arrival[$code] = $new ? -$new : -(60 + $i * 4);
            $path = 'costumes/' . strtolower($code) . '.jpg';
            DB::insert('costume_images', ['costume_id' => $id, 'path' => $path, 'credit' => $credits[$path] ?? null, 'sort_order' => 0]);
        }
    }

    /**
     * Insert one booking directly with full history.
     * @param array<string, mixed> $opt
     */
    private static function booking(string $code, int $memberIdx, int $pickupOffset, int $days, string $status, array $opt = []): int
    {
        $costume = DB::one('SELECT * FROM costumes WHERE id = ?', [self::$costumeIds[$code]]);
        $userId = self::$userIds[$memberIdx];
        $pickup = add_days(today(), $pickupOffset);
        $return = add_days($pickup, $days - 1);
        $event = $opt['event'] ?? add_days($pickup, min(1, $days - 1));
        $addr = null;
        $plan = Shipping::plan($pickup, $return);
        if (isset($opt['ship'])) {
            [$delivery, $back] = $opt['ship'];
            $addr = DB::one('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id LIMIT 1', [$userId]);
            $plan = Shipping::plan($pickup, $return, $delivery, $back, Shipping::zone($addr['province']));
        }
        $q = Pricing::quote($costume, $pickup, $return, null, $plan['delivery_fee'], $plan['return_fee']);
        $channel = $opt['channel'] ?? 'online';
        $created = $opt['created'] ?? date('Y-m-d H:i:s', strtotime($pickup . ' 10:00:00 -' . (3 + mt_rand(0, 12)) . ' days'));
        if ($created > date('Y-m-d H:i:s')) {
            $created = date('Y-m-d H:i:s', strtotime('-' . mt_rand(2, 30) . ' hours'));
        }
        $confirmedAt = in_array($status, ['confirmed', 'picked_up', 'returned'], true) ? date('Y-m-d H:i:s', strtotime($created . ' +' . mt_rand(1, 6) . ' hours')) : null;
        if ($confirmedAt && $confirmedAt > date('Y-m-d H:i:s')) {
            $confirmedAt = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        }
        $row = [
            'booking_no' => Counter::code('PT', 'booking'), 'user_id' => $userId, 'costume_id' => $costume['id'],
            'created_by' => $channel === 'counter' ? self::$staffId : $userId, 'channel' => $channel,
            'event_date' => $event, 'pickup_date' => $pickup, 'pickup_time' => $opt['pickup_time'] ?? '13:00',
            'return_date' => $return, 'return_time' => '13:00', 'block_from' => $plan['block_from'], 'block_until' => $plan['block_until'],
            'delivery_method' => $plan['delivery'], 'return_method' => $plan['return'], 'ship_zone' => $plan['zone'], 'ship_by_date' => $plan['ship_by'],
            'delivery_fee' => $plan['delivery_fee'], 'return_fee' => $plan['return_fee'],
            'ship_recipient' => $addr['recipient'] ?? null, 'ship_phone' => $addr['phone'] ?? null,
            'ship_address' => $addr ? Shipping::addressText($addr) : null, 'ship_province' => $addr['province'] ?? null, 'ship_postcode' => $addr['postcode'] ?? null,
            'rental_days' => $q['days'], 'rent_amount' => $q['rent'], 'extra_amount' => $q['extra'], 'deposit_amount' => $q['deposit'],
            'total_amount' => $q['total'], 'status' => $status, 'note' => $opt['note'] ?? null,
            'hold_expires_at' => $status === 'pending_payment' ? date('Y-m-d H:i:s', time() + ($opt['hold'] ?? 20) * 3600) : null,
            'confirmed_at' => $confirmedAt, 'created_at' => $created,
        ];
        if (in_array($status, ['picked_up', 'returned'], true)) {
            $row['picked_up_at'] = $pickup . ' ' . sprintf('%02d:%02d:00', 12 + mt_rand(0, 4), mt_rand(0, 59));
        }
        $id = DB::insert('bookings', $row);
        $ev = fn(string $type, string $msg, string $at, ?int $actor = null) => DB::insert('booking_events', ['booking_id' => $id, 'actor_id' => $actor, 'type' => $type, 'message' => $msg, 'created_at' => $at]);
        $ev('created', $channel === 'counter' ? 'พนักงานสร้างการจองที่หน้าร้าน' : 'ลูกค้าจองผ่านเว็บไซต์', $created, $channel === 'counter' ? self::$staffId : $userId);

        $slip = $opt['slip'] ?? null;
        if ($slip) {
            @mkdir(STORAGE_PATH . '/slips', 0775, true);
            $dest = 'demo-' . $id . '-' . $slip;
            $slipAt = date('Y-m-d H:i:s', strtotime($created . ' +40 minutes'));
            if ($slipAt > date('Y-m-d H:i:s')) {
                $slipAt = date('Y-m-d H:i:s', strtotime('-15 minutes'));
            }
            self::slipImage(BASE_PATH . '/database/seed-slips/' . $slip, STORAGE_PATH . '/slips/' . $dest, (float) $q['total'], $slipAt, $id);
            DB::insert('payments', ['booking_id' => $id, 'kind' => 'booking', 'method' => 'promptpay', 'amount' => $q['total'], 'slip_path' => $dest,
                'status' => $status === 'awaiting_verification' ? 'pending' : 'approved', 'created_by' => $userId, 'created_at' => $slipAt,
                'reviewed_by' => $status === 'awaiting_verification' ? null : self::$staffId, 'reviewed_at' => $status === 'awaiting_verification' ? null : $confirmedAt]);
            $ev('slip', 'แนบสลิปการโอน ' . money($q['total']), $slipAt, $userId);
        } elseif ($confirmedAt) {
            $method = $channel === 'counter' ? (mt_rand(0, 1) ? 'cash' : 'transfer') : 'promptpay';
            DB::insert('payments', ['booking_id' => $id, 'kind' => 'booking', 'method' => $method, 'amount' => $q['total'],
                'slip_path' => null, 'status' => 'approved', 'created_by' => $channel === 'counter' ? self::$staffId : $userId,
                'created_at' => $confirmedAt, 'reviewed_by' => self::$staffId, 'reviewed_at' => $confirmedAt]);
        }
        if ($confirmedAt) {
            $ev('confirmed', $channel === 'counter' ? 'รับชำระที่หน้าร้าน ' . money($q['total']) : 'อนุมัติสลิปการโอน ' . money($q['total']), $confirmedAt, self::$staffId);
            $r = Receipts::issueBooking($id, self::$staffId);
            DB::run('UPDATE receipts SET issued_at = ? WHERE id = ?', [$confirmedAt, $r['id']]);
            DB::run('UPDATE booking_events SET created_at = ? WHERE booking_id = ? AND type = ?', [$confirmedAt, $id, 'receipt']);
            if ($status !== 'returned') {
                Appointments::syncForBooking($id, self::$staffId);
            }
        }
        if (in_array($status, ['picked_up', 'returned'], true)) {
            $ev('picked_up', 'ลูกค้ารับชุดแล้ว', $row['picked_up_at'], self::$staffId);
            DB::run("UPDATE appointments SET status = 'done' WHERE booking_id = ? AND type = 'pickup'", [$id]);
        }
        if ($status === 'returned') {
            $late = $opt['late'] ?? 0;
            $damage = (float) ($opt['damage'] ?? 0);
            $actual = add_days($return, $late);
            $s = Pricing::settle($q['deposit'], (float) $costume['late_fee_per_day'], $return, $actual, $damage);
            $returnedAt = $actual . ' ' . sprintf('%02d:%02d:00', 11 + mt_rand(0, 6), mt_rand(0, 59));
            DB::update('bookings', [
                'returned_at' => $returnedAt, 'late_days' => $s['late_days'], 'late_fee' => $s['late_fee'], 'damage_fee' => $s['damage_fee'],
                'damage_note' => $damage > 0 ? ($opt['damage_note'] ?? 'คราบเครื่องสำอางที่คอเสื้อ ต้องส่งซักแห้งพิเศษ') : null,
                'deposit_refund' => $s['deposit_refund'], 'extra_charge' => $s['extra_charge'],
            ], 'id = :id', ['id' => $id]);
            if ($s['deposit_refund'] > 0) {
                DB::insert('payments', ['booking_id' => $id, 'kind' => 'refund', 'method' => 'cash', 'amount' => $s['deposit_refund'], 'status' => 'approved',
                    'created_by' => self::$staffId, 'reviewed_by' => self::$staffId, 'reviewed_at' => $returnedAt, 'created_at' => $returnedAt]);
            }
            if ($s['extra_charge'] > 0) {
                DB::insert('payments', ['booking_id' => $id, 'kind' => 'fine', 'method' => 'cash', 'amount' => $s['extra_charge'], 'status' => 'approved',
                    'created_by' => self::$staffId, 'reviewed_by' => self::$staffId, 'reviewed_at' => $returnedAt, 'created_at' => $returnedAt]);
            }
            $ev('returned', 'คืนชุดแล้ว' . ($s['late_days'] ? ' (ช้า ' . $s['late_days'] . ' วัน ปรับ ' . money($s['late_fee']) . ')' : ' ตรงเวลา')
                . ($s['damage_fee'] > 0 ? ' · ค่าเสียหาย ' . money($s['damage_fee']) : '') . ' · คืนมัดจำ ' . money($s['deposit_refund']), $returnedAt, self::$staffId);
            $r = Receipts::issueSettlement($id, self::$staffId);
            DB::run('UPDATE receipts SET issued_at = ? WHERE id = ?', [$returnedAt, $r['id']]);
            DB::run("UPDATE booking_events SET created_at = ? WHERE booking_id = ? AND type = 'receipt' AND message LIKE '%คืนชุด%'", [$returnedAt, $id]);
            foreach (['pickup' => $row['picked_up_at'], 'return' => $returnedAt] as $type => $at) {
                DB::insert('appointments', ['user_id' => $userId, 'booking_id' => $id, 'type' => $type, 'starts_at' => $at, 'status' => 'done', 'created_by' => self::$staffId, 'created_at' => $created]);
            }
        }
        if ($status === 'cancelled') {
            $at = date('Y-m-d H:i:s', strtotime($created . ' +1 day'));
            $at = min($at, date('Y-m-d H:i:s'));
            DB::update('bookings', ['cancel_reason' => $opt['reason'] ?? 'เปลี่ยนวันงาน', 'cancelled_by' => $userId, 'cancelled_at' => $at, 'hold_expires_at' => null], 'id = :id', ['id' => $id]);
            $ev('cancelled', 'ยกเลิก: ' . ($opt['reason'] ?? 'เปลี่ยนวันงาน'), $at, $userId);
        }
        if ($status === 'expired') {
            $at = date('Y-m-d H:i:s', strtotime($created . ' +24 hours'));
            $ev('expired', 'ระบบยกเลิกอัตโนมัติ เพราะไม่ได้ชำระเงินภายในเวลาที่กำหนด', min($at, date('Y-m-d H:i:s')));
        }
        return $id;
    }

    private static function bookings(): int
    {
        $n = 0;
        // History for the reports: returned rentals over the last ~80 days, without overlaps.
        $codes = array_keys(self::$costumeIds);
        /** @var array<string, list<array{int, int}>> $busy days already taken, per dress: [first, last] as day offsets */
        $busy = [];
        $free = function (string $code, int $p, int $days) use (&$busy): bool {
            foreach ($busy[$code] ?? [] as [$a, $b]) {
                if ($p <= $b + 1 && $a <= $p + $days) {
                    return false;
                }
            }
            return true;
        };
        $mark = function (string $code, int $p, int $days) use (&$busy) {
            $busy[$code][] = [$p, $p + $days - 1];
        };
        $mark('EV-004', -40, 4);   // kept clear for the demo member's own history below
        foreach (['EV-006', 'EV-007', 'EV-008', 'TH-003', 'TH-004', 'TH-006', 'PA-007', 'WE-002', 'WE-003'] as $c) {
            $mark($c, -12, 20);    // the parcel rentals below
        }
        $mark('WE-004', -21, 3);
        for ($k = 0; $k < 34; $k++) {
            $code = $codes[mt_rand(0, count($codes) - 1)];
            if ($code === 'PA-004' && $k > 5) {
                continue;
            }
            $p = -82 + (int) round($k * 2.2) + mt_rand(0, 2);
            $days = mt_rand(0, 9) < 7 ? 3 : mt_rand(4, 5);
            if ($p + $days > -6 || $p < self::$arrival[$code] || !$free($code, $p, $days)) {   // nothing is rented before it arrives
                continue;
            }
            $mark($code, $p, $days);
            $roll = mt_rand(1, 100);
            self::booking($code, 1 + mt_rand(0, 7), $p, $days, 'returned', [
                'channel' => mt_rand(1, 10) <= 7 ? 'online' : 'counter',
                'late' => $roll > 85 ? ($roll > 96 ? 2 : 1) : 0,
                'damage' => $roll === 50 || $roll === 77 ? 300 : 0,
            ]);
            $n++;
        }
        // The demo member's own history
        self::booking('EV-004', 0, -40, 3, 'returned', ['late' => 1]);
        self::booking('WE-004', 0, -21, 3, 'returned', []);
        self::booking('PA-003', 0, -12, 3, 'cancelled', ['reason' => 'งานเลื่อนออกไป']);
        $n += 3;

        // Live operations around today (T)
        self::booking('GR-001', 1, -4, 3, 'picked_up', ['note' => 'ขอเข็มกลัดเพิ่มที่เอว']);          // overdue 2 days
        self::booking('EV-002', 2, -2, 3, 'picked_up');                                                // due back today
        self::booking('TH-001', 3, 0, 3, 'confirmed', ['pickup_time' => '11:00', 'note' => 'ขอให้ร้านช่วยแต่งตัวด้วย']); // pickup today
        self::booking('PA-001', 4, 0, 2, 'confirmed', ['pickup_time' => '16:30', 'channel' => 'counter']); // pickup today
        self::booking('WE-004', 5, 1, 3, 'confirmed');                                                 // pickup tomorrow
        self::booking('GR-003', 6, 5, 3, 'awaiting_verification', ['slip' => 'slip-a.jpg']);
        self::booking('EV-005', 7, 9, 3, 'awaiting_verification', ['slip' => 'slip-b.jpg']);
        self::booking('TH-005', 2, 12, 4, 'awaiting_verification', ['slip' => 'slip-c.jpg']);
        self::booking('EV-003', 4, 7, 3, 'pending_payment', ['hold' => 2]);
        self::booking('WE-001', 0, 6, 3, 'confirmed', ['note' => 'งานแต่งเพื่อนที่โรงแรม ขอรับช่วงบ่าย']);
        self::booking('GR-005', 0, 25, 3, 'pending_payment', ['hold' => 18]);
        self::booking('PA-006', 5, -1, 3, 'expired', ['created' => date('Y-m-d H:i:s', strtotime('-3 days'))]);
        $n += 12;

        // Graduation season: dresses already taken on popular December dates
        foreach ([['GR-002', 1, 22], ['GR-007', 3, 22], ['GR-006', 6, 23], ['GR-004', 7, 24], ['TH-002', 4, 22], ['EV-001', 5, 30], ['WE-005', 3, 15], ['PA-005', 6, 16]] as [$code, $m, $off]) {
            self::booking($code, $m, $off, 3, 'confirmed');
            $n++;
        }
        return $n;
    }

    private static function addresses(): void
    {
        foreach (self::ADDRESSES as $i => [$m, $label, $line1, $sub, $district, $province, $postcode]) {
            $u = DB::one('SELECT first_name, last_name, phone FROM users WHERE id = ?', [self::$userIds[$m]]);
            $first = !DB::value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [self::$userIds[$m]]);
            DB::insert('addresses', ['user_id' => self::$userIds[$m], 'label' => $label, 'recipient' => $u['first_name'] . ' ' . $u['last_name'],
                'phone' => $u['phone'], 'line1' => $line1, 'subdistrict' => $sub, 'district' => $district, 'province' => $province,
                'postcode' => $postcode, 'note' => $i === 0 ? 'ฝากนิติบุคคลชั้น 1 ได้' : null, 'is_default' => $first ? 1 : 0]);
        }
    }

    /**
     * Rentals that travel by parcel, one at each stage, run through the real services so they behave
     * exactly like live ones; afterwards their timestamps are moved back to the days they happened.
     */
    private static function parcels(): int
    {
        $staff = self::$staffId;
        $at = function (int $id, string $type, string $when): void {
            DB::run('UPDATE booking_events SET created_at = ? WHERE booking_id = ? AND type = ?', [$when, $id, $type]);
        };
        $day = fn(int $offset, string $time) => add_days(today(), $offset) . ' ' . $time;
        // a parcel that went out and arrived in the past (ship() itself only works on the day it happens)
        $deliver = function (int $id, string $carrier, string $tracking, int $shipped, int $arrived) use ($staff, $at, $day): void {
            $out = DB::insert('shipments', ['booking_id' => $id, 'direction' => 'out', 'carrier' => $carrier, 'tracking_no' => $tracking !== '' ? $tracking : null,
                'shipped_on' => add_days(today(), $shipped), 'status' => 'delivered', 'arrived_at' => $day($arrived, '11:20:00'),
                'created_by' => $staff, 'created_at' => $day($shipped, '13:40:00')]);
            DB::update('bookings', ['status' => 'picked_up', 'picked_up_at' => $day($arrived, '11:20:00')], 'id = :id', ['id' => $id]);
            $s = DB::one('SELECT * FROM shipments WHERE id = ?', [$out]);
            BookingService::log($id, $staff, 'shipped', 'ส่งชุดออกจากร้าน · ' . Fulfilment::describe($s));
            BookingService::log($id, $staff, 'delivered', 'ร้านยืนยันว่าพัสดุถึงลูกค้าแล้ว (ตามระบบติดตามพัสดุ)');
            $at($id, 'shipped', $day($shipped, '13:40:00'));
            $at($id, 'delivered', $day($arrived, '11:20:00'));
        };
        $postBack = function (int $id, string $tracking, int $posted) use ($at, $day): void {
            $b = DB::one('SELECT user_id FROM bookings WHERE id = ?', [$id]);
            Fulfilment::postBack($id, (int) $b['user_id'], 'thaipost', $tracking, add_days(today(), $posted));
            DB::run("UPDATE shipments SET created_at = ? WHERE booking_id = ? AND direction = 'back'", [$day($posted, '17:05:00'), $id]);
            $at($id, 'posted_back', $day($posted, '17:05:00'));
        };
        $receive = function (int $id, int $when) use ($staff, $at, $day): void {
            Fulfilment::receiveBack($id, $staff);
            DB::run("UPDATE shipments SET arrived_at = ? WHERE booking_id = ? AND direction = 'back'", [$day($when, '10:15:00'), $id]);
            $at($id, 'received_back', $day($when, '10:15:00'));
        };
        $type = fn(string $name) => (int) DB::value('SELECT id FROM damage_types WHERE name = ?', [$name]);

        // 1. to send out today (up-country, so it leaves two days before the customer's pick-up day)
        self::booking('EV-006', 2, 2, 3, 'confirmed', ['ship' => ['post', 'store'], 'note' => 'ขอแพ็กกันยับ เดินทางต่ออีกทอด']);
        // 2. on the way to the demo member (she can follow it and press "received")
        $id = self::booking('EV-007', 0, 1, 3, 'confirmed', ['ship' => ['post', 'post']]);
        Fulfilment::ship($id, $staff, 'thaipost', 'EF100000016TH');
        // 3. arrived this morning: the arrival photo window is open
        $id = self::booking('WE-003', 4, 0, 3, 'confirmed', ['ship' => ['post', 'post']]);
        $deliver($id, 'kex', 'KEX0000000071', -1, 0);
        DB::run('UPDATE bookings SET picked_up_at = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('-2 hours')), $id]);
        // 4. came by messenger, customer reported a small mark on arrival, returns at the shop
        $id = self::booking('TH-004', 3, -1, 4, 'confirmed', ['ship' => ['messenger', 'store']]);
        $deliver($id, 'messenger', '', -1, -1);
        DB::update('bookings', ['arrival_note' => 'เข็มขัดมีรอยถลอกเล็กน้อยด้านใน (มีอยู่ก่อนแล้ว)', 'arrival_reported_at' => $day(-1, '12:05:00')], 'id = :id', ['id' => $id]);
        BookingService::log($id, self::$userIds[3], 'arrival', 'ลูกค้าแจ้งปัญหาตอนได้รับชุด: เข็มขัดมีรอยถลอกเล็กน้อยด้านใน (มีอยู่ก่อนแล้ว)');
        $at($id, 'arrival', $day(-1, '12:05:00'));
        // 5. posted back two days ago, still in transit
        $id = self::booking('WE-002', 6, -5, 3, 'confirmed', ['ship' => ['post', 'post']]);
        $deliver($id, 'thaipost', 'EF100000020TH', -7, -5);
        $postBack($id, 'EF100000033TH', -3);
        // 6. back at the shop, waiting for inspection
        $id = self::booking('PA-007', 7, -5, 3, 'confirmed', ['ship' => ['post', 'post']]);
        $deliver($id, 'flash', 'TH01234567890', -6, -5);
        $postBack($id, 'EF100000047TH', -3);
        $receive($id, 0);
        // 7. inspected today with damage: the demo member can still dispute it
        $id = self::booking('EV-008', 0, -6, 3, 'confirmed', ['ship' => ['post', 'post']]);
        $deliver($id, 'thaipost', 'EF100000055TH', -7, -6);
        $postBack($id, 'EF100000064TH', -4);
        $receive($id, -1);
        Inspection::complete($id, $staff, ['outcome' => 'ready', 'checks' => ['fabric', 'seams', 'closures', 'beading', 'shape'],
            'rows' => [['type_id' => $type('คราบเล็กน้อย ขจัดได้'), 'qty' => 1, 'note' => 'คราบรองพื้นด้านในคอเสื้อ']],
            'missing' => ['ไม้แขวนบุฟองน้ำ']]);
        // 8. inspected with damage, the customer disputed it: waiting for the shop's answer
        $id = self::booking('TH-006', 5, -7, 3, 'confirmed', ['ship' => ['post', 'post']]);
        $deliver($id, 'jnt', '800000000123', -8, -7);
        $postBack($id, 'EF100000078TH', -5);
        $receive($id, -2);
        Inspection::complete($id, $staff, ['outcome' => 'ready', 'checks' => ['fabric', 'seams', 'closures', 'stains', 'shape'],
            'rows' => [['type_id' => $type('เลื่อมหรือลูกปัดหลุด'), 'qty' => 2, 'note' => 'ลูกปัดที่สไบหลุดสองจุด']], 'missing' => []]);
        Inspection::dispute($id, self::$userIds[5], 'ลูกปัดหลุดตั้งแต่ตอนได้รับกล่อง แต่ลืมถ่ายรูปไว้ ขอให้ร้านช่วยพิจารณาค่ะ');
        return 8;
    }

    private static function appointments(): void
    {
        $T = today();
        $rows = [
            [3, $T, '11:00', 'scheduled', 'ชุดที่อยากลอง: TH-003 ชุดไทยเสื้อลูกไม้เทา'],
            [7, $T, '14:30', 'scheduled', 'ลองชุดราตรี 2-3 ชุดสำหรับงานกาล่า'],
            [0, add_days($T, 1), '13:00', 'scheduled', 'ชุดที่อยากลอง: GR-007 เดรสคล้องคอปักกลีบดอก'],
            [2, add_days($T, 2), '10:30', 'scheduled', null],
            [5, add_days($T, -3), '15:00', 'done', 'ลองชุดไทย'],
            [6, add_days($T, -6), '11:30', 'no_show', null],
            [1, add_days($T, -9), '13:30', 'done', null],
            [4, add_days($T, -15), '16:00', 'done', 'ลองชุดปาร์ตี้'],
            [8, add_days($T, -20), '10:00', 'cancelled', null],
            [3, add_days($T, -28), '14:00', 'done', null],
        ];
        foreach ($rows as [$m, $d, $t, $status, $note]) {
            DB::insert('appointments', [
                'user_id' => self::$userIds[$m], 'type' => 'fitting', 'starts_at' => "$d $t:00", 'status' => $status, 'note' => $note,
                'created_by' => self::$userIds[$m], 'created_at' => date('Y-m-d H:i:s', strtotime("$d -2 days")),
            ]);
        }
    }

    private static function extras(): void
    {
        $me = self::$userIds[0];
        foreach (['GR-007', 'EV-003', 'TH-005', 'WE-004'] as $code) {
            DB::insert('favorites', ['user_id' => $me, 'costume_id' => self::$costumeIds[$code]]);
        }
        $b = DB::one("SELECT id, booking_no FROM bookings WHERE user_id = ? AND status = 'confirmed' ORDER BY pickup_date LIMIT 1", [$me]);
        if ($b) {
            DB::insert('notifications', ['user_id' => $me, 'type' => 'booking_confirmed', 'title' => 'ยืนยันการจอง ' . $b['booking_no'] . ' แล้ว',
                'body' => 'ร้านได้รับเงินเรียบร้อย ใบเสร็จดูได้ในหน้าการจอง', 'link' => '/bookings/' . $b['id'], 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        }
        DB::insert('notifications', ['user_id' => $me, 'type' => 'appointment', 'title' => 'นัดลองชุดพรุ่งนี้ 13:00 น.', 'body' => 'GR-007 เดรสคล้องคอปักกลีบดอก',
            'link' => '/account/appointments', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))]);
        foreach ([self::$adminId, self::$staffId] as $s) {
            foreach (DB::all("SELECT b.id, b.booking_no FROM bookings b WHERE b.status = 'awaiting_verification'") as $p) {
                DB::insert('notifications', ['user_id' => $s, 'type' => 'slip_new', 'title' => 'สลิปใหม่รอตรวจ ' . $p['booking_no'], 'link' => '/admin/payments',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(10, 300) . ' minutes'))]);
            }
        }
    }

    /**
     * Stamp the real amount, time and a reference onto a sample slip so the
     * "check the slip" step in the demo shows numbers that match the booking.
     * Falls back to a plain copy when GD/FreeType is unavailable.
     */
    private static function slipImage(string $src, string $dest, float $amount, string $at, int $ref): void
    {
        $bold = BASE_PATH . '/database/seed-fonts/slip-700.ttf';
        $reg = BASE_PATH . '/database/seed-fonts/slip-400.ttf';
        if (!function_exists('imagettftext') || !is_file($bold) || !($im = @imagecreatefromjpeg($src))) {
            copy($src, $dest);
            return;
        }
        $px = fn(int $x, int $y) => imagecolorat($im, $x, $y);
        $ink = imagecolorallocate($im, 0x1F, 0x24, 0x22);
        $head = $px(8, 120);
        $body = $px(8, 470);
        $white = imagecolorallocate($im, 0xEE, 0xF2, 0xF0);
        $ts = strtotime($at);
        $when = (int) date('j', $ts) . ' ' . THAI_MONTHS_SHORT[(int) date('n', $ts)] . ' ' . substr((string) ((int) date('Y', $ts) + 543), 2) . ' ' . date('H:i', $ts) . ' น.';
        imagefilledrectangle($im, 30, 104, 700, 140, $head);
        imagettftext($im, 19, 0, 40, 132, $white, $reg, $when);
        imagefilledrectangle($im, 30, 446, 700, 506, $body);
        imagettftext($im, 32, 0, 40, 492, $ink, $bold, number_format($amount, 2) . ' บาท');
        imagefilledrectangle($im, 30, 650, 700, 700, $body);
        imagettftext($im, 22, 0, 40, 686, $ink, $bold, date('Ymd', $ts) . '-P' . str_pad((string) $ref, 4, '0', STR_PAD_LEFT));
        imagejpeg($im, $dest, 86);
    }
}
