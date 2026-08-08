---
title: "บทที่ 5: ตั้งค่า .env"
nav_order: 5
---

# บทที่ 5: ตั้งค่าระบบผ่านไฟล์ `.env` แบบละเอียด
{: .no_toc }

## สารบัญในบทนี้
{: .no_toc .text-delta }

1. TOC
{:toc}

---

ไฟล์ `.env` คือจุดศูนย์กลางในการปรับแต่งพฤติกรรมของระบบโดย**ไม่ต้องแก้โค้ดเลย** หลังแก้ไฟล์นี้ต้องสั่ง `docker compose up -d --build` ใหม่เพื่อให้ค่ามีผล

## 5.1 ค่าเกี่ยวกับโมเดล AI

```env
OLLAMA_MODEL=llama3.2
OLLAMA_CODE_MODEL=qwen2.5-coder:3b
OLLAMA_VISION_MODEL=llava-phi3
OLLAMA_EMBED_MODEL=nomic-embed-text
```

| ตัวแปร | ความหมาย |
|---|---|
| `OLLAMA_MODEL` | โมเดลสำหรับโหมด "แชททั่วไป" (ตัวเลือกเริ่มต้นในดรอปดาวน์) |
| `OLLAMA_CODE_MODEL` | โมเดลสำหรับโหมด "เขียนโค้ด" |
| `OLLAMA_VISION_MODEL` | โมเดลสำหรับดูรูปภาพที่ผู้ใช้แนบมา |
| `OLLAMA_EMBED_MODEL` | โมเดลสำหรับระบบความรู้ AI (RAG) ใช้ค้นหาเนื้อหาที่เกี่ยวข้อง |

## 5.2 ค่าปรับความยาว/ความเร็วของคำตอบ

```env
OLLAMA_TIMEOUT=120
OLLAMA_STREAM_TIMEOUT=600
OLLAMA_NUM_CTX=8192
OLLAMA_NUM_PREDICT=-1
```

| ตัวแปร | ความหมาย |
|---|---|
| `OLLAMA_TIMEOUT` | เวลารอคำตอบสูงสุดแบบไม่ stream (วินาที) |
| `OLLAMA_STREAM_TIMEOUT` | เวลารอคำตอบสูงสุดแบบ streaming (วินาที) — ตั้งไว้นานกว่าเพราะผู้ใช้เห็นคำตอบทยอยออกมาระหว่างรอได้ |
| `OLLAMA_NUM_CTX` | ขนาด context window ที่ AI จำได้ต่อการสนทนา ยิ่งมากยิ่งจำบทสนทนายาวๆ ได้ แต่ใช้ RAM มากขึ้น |
| `OLLAMA_NUM_PREDICT` | จำนวน token สูงสุดที่ AI ตอบได้ต่อครั้ง ใส่ `-1` = ไม่จำกัดความยาว |

## 5.3 ค่าเกี่ยวกับองค์กรและ System Prompt

```env
APP_ORG_NAME=องค์กรของเรา
```

ชื่อองค์กรจะแสดงบนหน้าเว็บและถูกใส่เข้าไปใน system prompt อัตโนมัติ (เช่น "คุณคือผู้ช่วย AI ประจำ [ชื่อองค์กร]")

## 5.4 ค่าโควตาการใช้งาน

```env
APP_DEFAULT_DAILY_REQUEST_LIMIT=100
APP_DEFAULT_DAILY_TOKEN_LIMIT=20000
```

จำนวนครั้งและจำนวน token สูงสุดที่ผู้ใช้แต่ละคน**ใช้ได้ต่อวัน** โดยค่าเริ่มต้นนี้ แอดมินสามารถตั้งค่าทับเฉพาะรายบุคคลได้ในหน้าจัดการผู้ใช้ (บทที่ 12) ใส่ `0` เพื่อไม่จำกัด

## 5.5 ค่าฐานข้อมูล MySQL/MariaDB

```env
DB_NAME=faonex
DB_USER=faonex
DB_PASSWORD=faonex_change_me
DB_ROOT_PASSWORD=faonex_root_change_me
```

{: .important }
> **ต้องเปลี่ยนรหัสผ่านทั้งสองตัวนี้ก่อนใช้งานจริงเสมอ** โดยเฉพาะถ้าเซิร์ฟเวอร์เข้าถึงได้จากภายนอกองค์กร

## 5.6 ค่าปรับความเข้มงวดระบบความรู้ AI (RAG)

```env
APP_RAG_TOP_K=4
APP_RAG_SIMILARITY_THRESHOLD=0.35
```

| ตัวแปร | ความหมาย |
|---|---|
| `APP_RAG_TOP_K` | จำนวนส่วนเนื้อหาสูงสุดที่ดึงมาอ้างอิงต่อคำถาม |
| `APP_RAG_SIMILARITY_THRESHOLD` | ค่าความใกล้เคียงขั้นต่ำ (0-1) ที่เนื้อหาต้องมีจึงจะถือว่า "พบข้อมูล" — ตั้งสูงขึ้นถ้าต้องการให้เข้มงวดขึ้น |

รายละเอียดวิธีทำงานของระบบนี้อธิบายในบทที่ 16-17

## 5.7 ตัวอย่างไฟล์ `.env` ฉบับเต็ม

```env
OLLAMA_MODEL=llama3.2
OLLAMA_CODE_MODEL=qwen2.5-coder:3b
OLLAMA_VISION_MODEL=llava-phi3
OLLAMA_EMBED_MODEL=nomic-embed-text

APP_RAG_TOP_K=4
APP_RAG_SIMILARITY_THRESHOLD=0.35

APP_ORG_NAME=โรงเรียนตัวอย่างวิทยา

OLLAMA_TIMEOUT=120
OLLAMA_STREAM_TIMEOUT=600
OLLAMA_NUM_CTX=8192
OLLAMA_NUM_PREDICT=-1

APP_DEFAULT_DAILY_REQUEST_LIMIT=100
APP_DEFAULT_DAILY_TOKEN_LIMIT=20000

DB_NAME=faonex
DB_USER=faonex
DB_PASSWORD=รหัสผ่านของจริงที่ตั้งเอง
DB_ROOT_PASSWORD=รหัสผ่านรูทของจริงที่ตั้งเอง
```

หลังแก้ไฟล์เสร็จ อย่าลืมรัน:

```bash
docker compose up -d --build
```

---

## ต่อไป

ตั้งค่าระบบเสร็จแล้ว มาเริ่มใช้งานจริงกันที่ **[บทที่ 6: ระบบสมาชิก](./06-authentication.html)**
