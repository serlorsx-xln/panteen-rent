-- Panteen Rent database schema
-- Works on MySQL 5.7+/8.x and MariaDB 10.3+ (XAMPP). Character set utf8mb4 for Thai text.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications, mail_outbox, appointments, booking_events, receipts, payments,
  damage_items, damage_types, booking_photos, shipments, bookings, addresses, favorites, costume_images, costumes, categories,
  login_attempts, email_tokens, users, settings, counters;

SET FOREIGN_KEY_CHECKS = 1;

-- ผู้ใช้ทุกบทบาท: ผู้ดูแลระบบ (admin), พนักงาน (staff), สมาชิก (member)
CREATE TABLE users (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role              ENUM('admin','staff','member') NOT NULL DEFAULT 'member',
  status            ENUM('active','suspended') NOT NULL DEFAULT 'active',
  first_name        VARCHAR(100) NOT NULL,
  last_name         VARCHAR(100) NOT NULL,
  nickname          VARCHAR(50)  NULL,
  email             VARCHAR(190) NOT NULL,
  phone             VARCHAR(20)  NULL,
  line_id           VARCHAR(50)  NULL,
  password_hash     VARCHAR(255) NOT NULL,
  email_verified_at DATETIME NULL,
  bust_in           DECIMAL(4,1) NULL COMMENT 'รอบอก (นิ้ว)',
  waist_in          DECIMAL(4,1) NULL COMMENT 'รอบเอว (นิ้ว)',
  hip_in            DECIMAL(4,1) NULL COMMENT 'รอบสะโพก (นิ้ว)',
  height_cm         SMALLINT UNSIGNED NULL,
  suspended_reason  VARCHAR(255) NULL,
  last_login_at     DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- โทเค็นยืนยันอีเมลและรีเซ็ตรหัสผ่าน (เก็บเฉพาะค่า hash)
CREATE TABLE email_tokens (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('verify','reset') NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token_hash),
  KEY idx_token_user (user_id, type),
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ประวัติการเข้าสู่ระบบ (ใช้ล็อกเมื่อใส่รหัสผิดหลายครั้ง)
CREATE TABLE login_attempts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email       VARCHAR(190) NOT NULL,
  ip          VARCHAR(45) NOT NULL,
  success     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempt_email (email, created_at),
  KEY idx_attempt_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ประเภทชุด
CREATE TABLE categories (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  slug         VARCHAR(100) NOT NULL,
  color        CHAR(7) NOT NULL DEFAULT '#1D191C',
  description  VARCHAR(500) NULL,
  cover_image  VARCHAR(255) NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_category_slug (slug),
  UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ชุดให้เช่า (1 แถว = ชุดจริง 1 ตัว)
CREATE TABLE costumes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code             VARCHAR(20) NOT NULL,
  category_id      INT UNSIGNED NOT NULL,
  name             VARCHAR(150) NOT NULL,
  description      TEXT NULL,
  color_name       VARCHAR(50) NULL,
  size_label       VARCHAR(20) NOT NULL DEFAULT 'M',
  bust_min         DECIMAL(4,1) NULL,
  bust_max         DECIMAL(4,1) NULL,
  waist_min        DECIMAL(4,1) NULL,
  waist_max        DECIMAL(4,1) NULL,
  hip_max          DECIMAL(4,1) NULL,
  length_in        DECIMAL(4,1) NULL COMMENT 'ความยาวชุด (นิ้ว)',
  rent_price       DECIMAL(10,2) NOT NULL COMMENT 'ค่าเช่าแพ็กเกจพื้นฐาน',
  extra_day_price  DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'ค่าเช่าวันถัดไปต่อวัน',
  deposit          DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'เงินมัดจำ (คืนเมื่อคืนชุด)',
  late_fee_per_day DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'ค่าปรับคืนช้าต่อวัน',
  replacement_value DECIMAL(10,2) NULL COMMENT 'มูลค่าชุด ใช้คิดค่าเสียหายแบบร้อยละ และเป็นเพดานค่าเสียหาย',
  accessories      VARCHAR(300) NULL COMMENT 'ของที่มากับชุด คั่นด้วยจุลภาค (ตรวจตอนส่งและตอนรับคืน)',
  status           ENUM('active','maintenance','retired') NOT NULL DEFAULT 'active',
  is_featured      TINYINT(1) NOT NULL DEFAULT 0,
  arrived_at       DATE NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_costume_code (code),
  KEY idx_costume_cat (category_id, status),
  CONSTRAINT fk_costume_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE costume_images (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  costume_id  INT UNSIGNED NOT NULL,
  path        VARCHAR(255) NOT NULL,
  credit      VARCHAR(255) NULL COMMENT 'ที่มาของภาพ (ภาพตัวอย่างจากคลังภาพฟรี)',
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_img_costume (costume_id, sort_order),
  CONSTRAINT fk_img_costume FOREIGN KEY (costume_id) REFERENCES costumes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
  user_id     INT UNSIGNED NOT NULL,
  costume_id  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, costume_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_costume FOREIGN KEY (costume_id) REFERENCES costumes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สมุดที่อยู่จัดส่งของสมาชิก (สูงสุด 5 ที่อยู่ต่อคน)
CREATE TABLE addresses (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  label        VARCHAR(40)  NULL COMMENT 'เช่น บ้าน หอพัก ที่ทำงาน',
  recipient    VARCHAR(150) NOT NULL,
  phone        VARCHAR(20)  NOT NULL,
  line1        VARCHAR(255) NOT NULL COMMENT 'บ้านเลขที่ อาคาร ห้อง ซอย ถนน',
  subdistrict  VARCHAR(100) NOT NULL COMMENT 'แขวง/ตำบล',
  district     VARCHAR(100) NOT NULL COMMENT 'เขต/อำเภอ',
  province     VARCHAR(60)  NOT NULL,
  postcode     CHAR(5)      NOT NULL,
  note         VARCHAR(255) NULL COMMENT 'จุดสังเกตสำหรับคนส่งของ',
  is_default   TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_address_user (user_id, is_default),
  CONSTRAINT fk_address_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การจองเช่าชุด
-- สถานะ: pending_payment (รอชำระ) → awaiting_verification (รอตรวจสลิป) → confirmed (ยืนยันแล้ว)
--        → [shipped (ร้านส่งพัสดุแล้ว) →] picked_up (ชุดอยู่กับลูกค้า)
--        → [returning (ลูกค้าส่งพัสดุคืนแล้ว) →] returned (ตรวจสภาพและคืนมัดจำแล้ว ปิดรายการ)
--        หรือ cancelled (ยกเลิก) / expired (หมดเวลาชำระ)
-- ช่วงที่ชุดไม่ว่าง = block_from (วันร้านส่งพัสดุ หรือวันรับที่ร้าน) ถึง block_until (วันคืน + วันขนส่งกลับ + วันซัก)
CREATE TABLE bookings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_no       VARCHAR(20) NOT NULL,
  user_id          INT UNSIGNED NOT NULL,
  costume_id       INT UNSIGNED NOT NULL,
  created_by       INT UNSIGNED NULL,
  channel          ENUM('online','counter') NOT NULL DEFAULT 'online',
  event_date       DATE NOT NULL,
  pickup_date      DATE NOT NULL,
  pickup_time      VARCHAR(5) NOT NULL DEFAULT '13:00',
  return_date      DATE NOT NULL,
  return_time      VARCHAR(5) NOT NULL DEFAULT '13:00',
  delivery_method  ENUM('pickup','post','messenger') NOT NULL DEFAULT 'pickup' COMMENT 'รับที่ร้าน / ส่งพัสดุ / เมสเซนเจอร์',
  return_method    ENUM('store','post') NOT NULL DEFAULT 'store' COMMENT 'คืนที่ร้าน / ส่งพัสดุคืน',
  ship_zone        ENUM('metro','upcountry') NULL,
  ship_by_date     DATE NULL COMMENT 'วันที่ร้านต้องส่งพัสดุให้ถึงลูกค้าทันวันรับชุด',
  ship_recipient   VARCHAR(150) NULL,
  ship_phone       VARCHAR(20) NULL,
  ship_address     VARCHAR(600) NULL COMMENT 'ที่อยู่จัดส่ง ณ ตอนจอง (แก้สมุดที่อยู่ภายหลังไม่กระทบ)',
  ship_province    VARCHAR(60) NULL,
  ship_postcode    CHAR(5) NULL,
  block_from       DATE NOT NULL COMMENT 'วันแรกที่ชุดไม่ว่าง (วันส่งพัสดุ หรือวันรับชุดที่ร้าน)',
  block_until      DATE NOT NULL COMMENT 'วันสุดท้ายที่ชุดไม่ว่าง (รวมวันขนส่งกลับและวันซัก)',
  rental_days      SMALLINT UNSIGNED NOT NULL,
  rent_amount      DECIMAL(10,2) NOT NULL,
  extra_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
  deposit_amount   DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee     DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'ค่าส่งชุดถึงลูกค้า',
  return_fee       DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'ค่าใบปะหน้าส่งคืน (ร้านแนบไว้ในกล่อง)',
  total_amount     DECIMAL(10,2) NOT NULL COMMENT 'ยอดที่ต้องชำระตอนจอง = ค่าเช่า + ค่าวันเพิ่ม + มัดจำ + ค่าส่ง',
  status           ENUM('pending_payment','awaiting_verification','confirmed','shipped','picked_up','returning','returned','cancelled','expired') NOT NULL DEFAULT 'pending_payment',
  hold_expires_at  DATETIME NULL,
  note             VARCHAR(500) NULL,
  staff_note       VARCHAR(500) NULL,
  cancel_reason    VARCHAR(255) NULL,
  cancelled_by     INT UNSIGNED NULL,
  cancelled_at     DATETIME NULL,
  refund_amount    DECIMAL(10,2) NULL,
  confirmed_at     DATETIME NULL,
  picked_up_at     DATETIME NULL,
  returned_at      DATETIME NULL,
  late_days        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  late_fee         DECIMAL(10,2) NOT NULL DEFAULT 0,
  damage_fee       DECIMAL(10,2) NOT NULL DEFAULT 0,
  damage_note      VARCHAR(500) NULL,
  deposit_refund   DECIMAL(10,2) NULL,
  extra_charge     DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'ค่าปรับส่วนที่เกินเงินมัดจำ',
  inspected_at     DATETIME NULL,
  inspected_by     INT UNSIGNED NULL,
  inspection_json  TEXT NULL COMMENT 'ผลตรวจสภาพตอนรับคืน: จุดที่ตรวจ ของที่ขาด สภาพชุดหลังตรวจ',
  arrival_note     VARCHAR(500) NULL COMMENT 'ลูกค้าแจ้งปัญหาตอนได้รับพัสดุ',
  arrival_reported_at DATETIME NULL,
  dispute_note     VARCHAR(500) NULL COMMENT 'ลูกค้าโต้แย้งค่าเสียหาย',
  dispute_at       DATETIME NULL,
  dispute_reply    VARCHAR(500) NULL,
  dispute_resolved_at DATETIME NULL,
  damage_waived    DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'ค่าเสียหายที่ร้านลดให้หลังทบทวน (คืนเงินแล้ว)',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_booking_no (booking_no),
  KEY idx_booking_costume_dates (costume_id, status, block_from, block_until),
  KEY idx_booking_user (user_id, status),
  KEY idx_booking_pickup (pickup_date),
  KEY idx_booking_ship (status, ship_by_date),
  KEY idx_booking_return (return_date),
  CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_booking_costume FOREIGN KEY (costume_id) REFERENCES costumes(id),
  CONSTRAINT fk_booking_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- พัสดุ: ขาไป (out: ร้าน → ลูกค้า) และขากลับ (back: ลูกค้า → ร้าน)
-- out: in_transit → delivered | failed (ส่งไม่สำเร็จ/ตีกลับ)   back: in_transit → received | lost (สูญหายระหว่างขนส่ง)
CREATE TABLE shipments (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id   INT UNSIGNED NOT NULL,
  direction    ENUM('out','back') NOT NULL,
  carrier      VARCHAR(20) NOT NULL,
  tracking_no  VARCHAR(40) NULL,
  shipped_on   DATE NOT NULL COMMENT 'วันที่ฝากส่ง (ขากลับใช้คิดว่าคืนช้าหรือไม่)',
  status       ENUM('in_transit','delivered','failed','received','lost') NOT NULL DEFAULT 'in_transit',
  arrived_at   DATETIME NULL COMMENT 'ถึงลูกค้า (ขาไป) หรือถึงร้าน (ขากลับ)',
  note         VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shipment_booking (booking_id, direction),
  KEY idx_shipment_status (status, direction),
  CONSTRAINT fk_shipment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- รูปสภาพชุด (เก็บนอกโฟลเดอร์เว็บ เปิดดูได้เฉพาะเจ้าของการจองและพนักงาน)
-- handover = ก่อนส่ง/ส่งมอบ, arrival = ลูกค้าถ่ายตอนได้รับ, dropoff = ใบรับฝากพัสดุคืน, return = ตอนตรวจรับคืน, dispute = ลูกค้าโต้แย้ง
CREATE TABLE booking_photos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id  INT UNSIGNED NOT NULL,
  stage       ENUM('handover','arrival','dropoff','return','dispute') NOT NULL,
  path        VARCHAR(255) NOT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_photo_booking (booking_id, stage),
  CONSTRAINT fk_photo_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางค่าเสียหายมาตรฐาน: fixed = บาทต่อชิ้น, percent = ร้อยละของมูลค่าชุด
CREATE TABLE damage_types (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  area        VARCHAR(40)  NOT NULL COMMENT 'กลุ่มจุดตรวจ: ผ้า ตะเข็บ ซิปกระดุม งานปัก คราบกลิ่น ของที่มากับชุด ทั้งชุด',
  fee_type    ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  amount      DECIMAL(10,2) NOT NULL,
  hint        VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_damage_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- รายการความเสียหายที่พบตอนตรวจรับคืน (ราคาคงที่ ณ วันตรวจ)
CREATE TABLE damage_items (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id      INT UNSIGNED NOT NULL,
  damage_type_id  INT UNSIGNED NULL,
  label           VARCHAR(200) NOT NULL,
  qty             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  amount          DECIMAL(10,2) NOT NULL,
  note            VARCHAR(255) NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_damage_booking (booking_id),
  CONSTRAINT fk_damage_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_damage_type FOREIGN KEY (damage_type_id) REFERENCES damage_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การชำระเงิน: ค่าจอง (booking), ค่าปรับส่วนเกิน (fine), คืนเงิน (refund)
CREATE TABLE payments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id     INT UNSIGNED NOT NULL,
  kind           ENUM('booking','fine','refund') NOT NULL DEFAULT 'booking',
  method         ENUM('promptpay','transfer','cash') NOT NULL DEFAULT 'promptpay',
  amount         DECIMAL(10,2) NOT NULL,
  slip_path      VARCHAR(255) NULL,
  status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason  VARCHAR(255) NULL,
  reviewed_by    INT UNSIGNED NULL,
  reviewed_at    DATETIME NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payment_booking (booking_id, kind, status),
  KEY idx_payment_status (status, created_at),
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ใบเสร็จ: ใบเสร็จค่าจอง ใบสรุปการคืนชุด และใบปรับปรุงค่าเสียหายหลังทบทวน
CREATE TABLE receipts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_no   VARCHAR(20) NOT NULL,
  booking_id   INT UNSIGNED NOT NULL,
  kind         ENUM('booking','settlement','adjustment') NOT NULL,
  amount       DECIMAL(10,2) NOT NULL,
  items_json   TEXT NOT NULL,
  issued_by    INT UNSIGNED NULL,
  issued_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  print_count  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt_no (receipt_no),
  UNIQUE KEY uq_receipt_booking_kind (booking_id, kind),
  CONSTRAINT fk_receipt_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ไทม์ไลน์ของการจอง
CREATE TABLE booking_events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id  INT UNSIGNED NOT NULL,
  actor_id    INT UNSIGNED NULL,
  type        VARCHAR(40) NOT NULL,
  message     VARCHAR(500) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_event_booking (booking_id, created_at),
  CONSTRAINT fk_event_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การนัดหมาย: นัดลองชุด / นัดรับชุด / นัดคืนชุด
CREATE TABLE appointments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  booking_id  INT UNSIGNED NULL,
  type        ENUM('fitting','pickup','return') NOT NULL,
  starts_at   DATETIME NOT NULL,
  status      ENUM('scheduled','done','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  note        VARCHAR(500) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_appt_time (starts_at, status),
  KEY idx_appt_booking (booking_id, type),
  CONSTRAINT fk_appt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_appt_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การแจ้งเตือนในระบบ
CREATE TABLE notifications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(40) NOT NULL,
  title       VARCHAR(200) NOT NULL,
  body        VARCHAR(500) NULL,
  link        VARCHAR(255) NULL,
  dedupe_key  VARCHAR(120) NULL,
  read_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notification_dedupe (dedupe_key),
  KEY idx_notification_user (user_id, read_at, created_at),
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- อีเมลทุกฉบับที่ระบบส่ง (กล่องจดหมายจำลองสำหรับเดโม)
CREATE TABLE mail_outbox (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  to_email    VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  html        MEDIUMTEXT NOT NULL,
  text_body   MEDIUMTEXT NOT NULL,
  status      ENUM('logged','sent','failed') NOT NULL DEFAULT 'logged',
  error       VARCHAR(500) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mail_to (to_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตั้งค่าร้าน
CREATE TABLE settings (
  `key`       VARCHAR(60) NOT NULL,
  `value`     TEXT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตัวนับเลขที่เอกสาร (เลขจอง / เลขใบเสร็จ) แยกตามปี
CREATE TABLE counters (
  name   VARCHAR(30) NOT NULL,
  year   SMALLINT UNSIGNED NOT NULL,
  value  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (name, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
