---
title: "บทที่ 4: โครงสร้างโปรเจกต์"
nav_order: 4
---

# บทที่ 4: โครงสร้างโปรเจกต์และไฟล์สำคัญ
{: .no_toc }

## สารบัญในบทนี้
{: .no_toc .text-delta }

1. TOC
{:toc}

---

บทนี้เหมาะสำหรับผู้ที่ต้องการเข้าใจว่าไฟล์ต่างๆ ในโปรเจกต์ทำหน้าที่อะไร — ถ้าสนใจแค่การใช้งานทั่วไป สามารถข้ามไปบทที่ 5 ได้เลย

## 4.1 ภาพรวมโครงสร้างโฟลเดอร์

```
faonex.ai/
├── docker-compose.yml      # นิยามบริการทั้งหมด (web, backend, mysql, ollama, phpmyadmin)
├── .env                    # ค่าตั้งค่าระบบ (คัดลอกจาก .env.example)
├── backend/                # ส่วน API (PHP)
│   ├── Dockerfile
│   ├── composer.json       # รายการไลบรารี PHP ที่ใช้
│   ├── fonts/               # ฟอนต์ไทยสำหรับสร้างรายงาน PDF
│   ├── public/index.php    # จุดเริ่มต้นของทุก request (router)
│   └── src/                 # โค้ดหลักของระบบ แยกเป็นคลาสตามหน้าที่
└── frontend/                # ส่วนหน้าเว็บ (SvelteKit)
    ├── Dockerfile
    ├── nginx.conf
    └── src/
        ├── lib/              # โค้ดที่ใช้ร่วมกันหลายหน้า
        └── routes/           # แต่ละหน้าเว็บ
```

## 4.2 ไฟล์ในฝั่ง Backend (`backend/src/`)

| ไฟล์ | หน้าที่ |
|---|---|
| `Config.php` | อ่านค่าตั้งค่าจาก `.env`, system prompt, รายชื่อโมเดล AI |
| `Database.php` | เชื่อมต่อและสร้างตารางฐานข้อมูล MySQL/MariaDB |
| `Auth.php` | สมัคร/ล็อกอิน/ออกจากระบบ, จัดการสิทธิ์แอดมิน |
| `ConversationStore.php` | บันทึก/ดึงประวัติบทสนทนา |
| `UsageStore.php` | บันทึกการใช้งาน token/จำนวนครั้ง, สร้างสถิติ |
| `OllamaClient.php` | ติดต่อกับ Ollama เพื่อขอคำตอบจาก AI (รวมโหมด streaming) |
| `ContentModerator.php` | ตรวจจับคำที่อาจเข้าข่ายอันตราย |
| `ModerationStore.php` | บันทึกข้อความที่ถูกตรวจจับ |
| `SystemMonitor.php` | อ่านค่าการใช้งาน CPU/RAM/ดิสก์ของเครื่อง |
| `ApiKeyStore.php` | ออก/ตรวจสอบ API key สำหรับเซิร์ฟเวอร์ภายนอก |
| `UserImportExport.php` | นำเข้า/ส่งออกผู้ใช้งานเป็น Excel และ PDF |
| `KnowledgeStore.php` | จัดการหัวข้อและเอกสารของระบบความรู้ AI (RAG + Rule) |
| `RagService.php` | ค้นหาเนื้อหาที่เกี่ยวข้องและสร้างคำตอบแบบจำกัดขอบเขต |
| `PdfTextExtractor.php` | ดึงข้อความจากไฟล์ PDF ที่อัปโหลด |
| `TextChunker.php` | แบ่งข้อความยาวเป็นส่วนย่อยสำหรับค้นหา |
| `KnowledgeTemplateBuilder.php` | สร้างไฟล์ Word ฟอร์มตัวอย่างการเขียนคำตอบ |

ทุกคำขอ (request) จากผู้ใช้งานจะเข้ามาที่ `public/index.php` ก่อนเสมอ ไฟล์นี้ทำหน้าที่เป็น "router" อ่าน URL ที่เรียกเข้ามาแล้วส่งต่อไปยังฟังก์ชันที่เกี่ยวข้อง เช่น คำขอ `POST /api/chat` จะถูกส่งไปประมวลผลที่ฟังก์ชัน `handleChat()`

## 4.3 ไฟล์ในฝั่ง Frontend (`frontend/src/`)

| ไฟล์/โฟลเดอร์ | หน้าที่ |
|---|---|
| `lib/api.js` | ฟังก์ชันเรียก API ของ backend ทั้งหมด |
| `lib/i18n.js` | ระบบสองภาษา (ไทย/อังกฤษ) |
| `lib/theme.js` | ระบบสลับธีมมืด/สว่าง |
| `lib/ChatInputBar.svelte` | แถบพิมพ์ข้อความ/แนบไฟล์/ปุ่มส่ง ใช้ร่วมกันในหน้าแชท |
| `lib/Logo.svelte` | โลโก้ FAONEX.AI |
| `routes/+page.svelte` | หน้าแชทหลัก |
| `routes/login/+page.svelte` | หน้าเข้าสู่ระบบ |
| `routes/register/+page.svelte` | หน้าสมัครสมาชิก |
| `routes/admin/` | ทุกหน้าของแผงควบคุมแอดมิน |

## 4.4 ไฟล์ตั้งค่าระดับโปรเจกต์

| ไฟล์ | หน้าที่ |
|---|---|
| `docker-compose.yml` | นิยามว่าระบบมีบริการอะไรบ้าง เชื่อมต่อกันอย่างไร ใช้พอร์ตอะไร |
| `.env` / `.env.example` | ค่าตั้งค่าที่ปรับได้โดยไม่ต้องแก้โค้ด (อธิบายละเอียดในบทที่ 5) |
| `backend/Dockerfile` | ขั้นตอนสร้างอิมเมจของ backend (ติดตั้ง PHP, Composer, ไลบรารี) |
| `frontend/Dockerfile` | ขั้นตอนสร้างอิมเมจของ frontend (build ด้วย Node แล้ว serve ด้วย Nginx) |
| `frontend/nginx.conf` | ตั้งค่า Nginx ที่ทำหน้าที่ส่งต่อคำขอ `/api/*` ไปยัง backend |

---

## ต่อไป

ไปดูรายละเอียดค่าตั้งค่าทั้งหมดใน `.env` กันที่ **[บทที่ 5: ตั้งค่าระบบผ่านไฟล์ .env แบบละเอียด](./05-env-configuration.html)**
