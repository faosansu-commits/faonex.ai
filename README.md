# FAONEX.AI

เว็บแอป AI Chatbot สำหรับตอบคำถามภายในองค์กร ธีมมืด (dark) พร้อมโลโก้และตัวอักษรไล่สีม่วง-ขาวแบบมีประกาย (shimmer) ประกอบด้วย:

- **Frontend**: SvelteKit + Tailwind CSS (build เป็น static site แล้ว serve ผ่าน Nginx)
- **Backend**: PHP (Apache) เป็น REST API เชื่อมต่อกับ Ollama + SQLite (ผู้ใช้/ประวัติแชท)
- **AI**: [Ollama](https://ollama.com) รันโมเดลภาษาในเครื่อง ไม่ต้องใช้ API key ภายนอก มี 2 โหมด (แชททั่วไป / เขียนโค้ด)
- **Reverse proxy**: Nginx เสิร์ฟหน้าเว็บ + proxy คำขอ `/api/*` ไปยัง backend

## ฟีเจอร์

- ธีมมืดทั้งระบบ โลโก้ FAONEX.AI พร้อมเอฟเฟกต์เรืองแสง (glow) และตัวอักษรแบรนด์ไล่สีม่วง-ขาวแบบมีประกาย
- ระบบสมาชิก: สมัคร/เข้าสู่ระบบ (username + password, session-based)
- เก็บประวัติการแชทของแต่ละผู้ใช้แยกเป็นบทสนทนา ดูย้อนหลัง/ลบได้จากแถบด้านข้าง
- พิมพ์ข้อความด้วยเสียง (ปุ่มไมโครโฟน ใช้ Web Speech API ของเบราว์เซอร์) และให้บอทอ่านคำตอบออกเสียงได้ (ปุ่ม 🔊 ต่อข้อความ หรือเปิดออโต้ที่ header)
- โหมด "เขียนโค้ด" สลับไปใช้โมเดลเฉพาะทางด้านโปรแกรมมิ่ง (เช่น `qwen2.5-coder`) พร้อม render โค้ดเป็น code block พร้อมปุ่มคัดลอก

## โครงสร้างโปรเจกต์

```
AI Chatbot/
├── docker-compose.yml
├── .env                        # ค่าคอนฟิก (คัดลอกจาก .env.example)
├── backend/                    # PHP API
│   ├── Dockerfile
│   ├── public/index.php        # router: /health /config /auth/* /conversations/* /chat
│   └── src/
│       ├── Config.php
│       ├── Database.php        # เชื่อมต่อ + migrate SQLite
│       ├── Auth.php            # register/login/logout/session
│       ├── ConversationStore.php  # CRUD ประวัติแชท
│       └── OllamaClient.php
└── frontend/                   # SvelteKit + Tailwind
    ├── Dockerfile               # multi-stage: build ด้วย Node แล้ว serve ด้วย Nginx
    ├── nginx.conf
    └── src/
        ├── lib/
        │   ├── api.js           # เรียก backend API
        │   ├── stores.js        # svelte store: user, authChecked
        │   └── Logo.svelte      # โลโก้ FAONEX.AI (glow badge)
        └── routes/
            ├── +layout.svelte   # auth guard (redirect ไป /login ถ้ายังไม่ล็อกอิน)
            ├── +page.svelte     # หน้าแชทหลัก (sidebar ประวัติ, เสียง, โหมดโค้ด)
            ├── login/+page.svelte
            └── register/+page.svelte
```

## เริ่มใช้งาน

### 1. ข้อกำหนดเบื้องต้น

- ติดตั้ง Docker Desktop (รองรับ `docker compose`)
- แนะนำ RAM อย่างน้อย ~8GB ขึ้นไป (ระบบนี้รัน 2 โมเดลภาษา: แชททั่วไป + เขียนโค้ด)
- เครื่องนี้ไม่มี GPU จึงรันบน CPU ล้วน — คำตอบอาจใช้เวลาหลายวินาทีถึงเป็นนาทีต่อข้อความ ขึ้นกับสเปกเครื่อง

### 2. ตั้งค่า

ไฟล์ `.env` ถูกสร้างไว้ให้แล้วพร้อมค่าเริ่มต้น ปรับได้ตามต้องการ:

```env
OLLAMA_MODEL=llama3.2            # โมเดลสำหรับแชททั่วไป
OLLAMA_CODE_MODEL=qwen2.5-coder:3b  # โมเดลสำหรับโหมดเขียนโค้ด
APP_ORG_NAME=องค์กรของเรา          # ชื่อองค์กร แสดงบนหน้าเว็บ
OLLAMA_TIMEOUT=120                # timeout รอคำตอบ AI (วินาที)
```

โมเดลอื่นที่น่าสนใจ (ดูเพิ่มเติมที่ https://ollama.com/library):
- แชททั่วไป: `llama3.2` (เล็ก/เร็ว) หรือ `qwen2.5:7b` (คุณภาพภาษาไทยดีขึ้น แต่หนักขึ้น)
- เขียนโค้ด: `qwen2.5-coder:3b` (เบา เหมาะกับ CPU) หรือ `qwen2.5-coder:7b`/`14b` (แม่นยำขึ้นแต่ช้าลงมากถ้าไม่มี GPU)

### 3. รันระบบ

```bash
docker compose up -d --build
```

รอบแรกที่รัน service `ollama-init` จะดาวน์โหลดโมเดลทั้งสองตัวอัตโนมัติ (รวมกันหลาย GB อาจใช้เวลาสักครู่) ตรวจสอบความคืบหน้าได้ด้วย:

```bash
docker compose logs -f ollama-init
```

เมื่อพร้อมแล้วเปิดเบราว์เซอร์ไปที่ `http://localhost:8095` แล้วสมัครสมาชิกเพื่อเริ่มใช้งาน (ระบบยังไม่มีบัญชีเริ่มต้นให้ ต้องสมัครเองครั้งแรก)

### 4. หยุดระบบ

```bash
docker compose down
```

ข้อมูลจะยังอยู่ใน volume แม้ปิดระบบ: `ollama_data` (โมเดลที่ดาวน์โหลด) และ `backend_data` (ฐานข้อมูลผู้ใช้/ประวัติแชท เป็นไฟล์ SQLite) ครั้งต่อไปที่รันจะไม่ต้องดาวน์โหลด/สมัครใหม่

## การใช้งานฟีเจอร์เสียง

- **พูดเพื่อพิมพ์**: กดปุ่ม 🎤 ข้างช่องพิมพ์ (รองรับดีสุดบน Chrome/Edge; เบราว์เซอร์ที่ไม่รองรับ Web Speech API ปุ่มนี้จะไม่แสดง)
- **อ่านคำตอบออกเสียง**: กดปุ่ม 🔊 ที่มุมขวาบนของแต่ละข้อความบอทเพื่อฟังทีละข้อความ หรือกดปุ่ม 🔊 ที่ header เพื่อเปิดให้อ่านออกเสียงอัตโนมัติทุกคำตอบ

## API Endpoints (backend)

เรียกผ่าน Nginx ด้วย prefix `/api` (ทุก endpoint ยกเว้น `/auth/*` และ `/health`, `/config` ต้องล็อกอินก่อน — ใช้ session cookie):

| Method | Path                          | คำอธิบาย                                                     |
|--------|-------------------------------|----------------------------------------------------------------|
| GET    | `/api/health`                 | ตรวจสอบว่าเชื่อมต่อ Ollama ได้หรือไม่                          |
| GET    | `/api/config`                 | คืนค่าคอนฟิกฝั่ง frontend (เช่น ชื่อองค์กร)                   |
| POST   | `/api/auth/register`          | สมัครสมาชิก `{ username, password, displayName }`             |
| POST   | `/api/auth/login`             | เข้าสู่ระบบ `{ username, password }`                           |
| POST   | `/api/auth/logout`            | ออกจากระบบ                                                     |
| GET    | `/api/auth/me`                | ข้อมูลผู้ใช้ปัจจุบัน                                            |
| GET    | `/api/conversations`          | รายการบทสนทนาของผู้ใช้                                         |
| POST   | `/api/conversations`          | สร้างบทสนทนาใหม่                                                |
| GET    | `/api/conversations/{id}/messages` | ข้อความทั้งหมดในบทสนทนา                                    |
| DELETE | `/api/conversations/{id}`     | ลบบทสนทนา                                                       |
| POST   | `/api/chat`                   | ส่งข้อความแชท `{ message, conversationId, mode: "chat"\|"code" }` คืนค่า `{ reply, conversationId }` |

## เกี่ยวกับฟีเจอร์สร้างภาพ/วิดีโอ (ยังไม่รวมอยู่ใน baseline นี้)

ตอนนี้ยังไม่ได้เพิ่มความสามารถสร้างภาพ/วิดีโอ เพราะ Ollama รองรับเฉพาะโมเดลภาษา (และโมเดล vision แบบ "อ่าน" ภาพเข้า ไม่ใช่ "สร้าง" ภาพออก) การสร้างภาพจริงต้องเพิ่มบริการแยก เช่น Stable Diffusion (ผ่าน AUTOMATIC1111/ComfyUI API) ส่วนสร้างวิดีโอสั้นยิ่งต้องใช้โมเดลและทรัพยากรที่หนักกว่ามาก

เนื่องจากเครื่องที่รันระบบนี้ไม่มี GPU การรัน Stable Diffusion บน CPU จะช้ามาก (อาจหลายนาทีต่อภาพ) และการสร้างวิดีโอบน CPU แทบไม่คุ้มที่จะใช้งานจริง จึงยังไม่ใส่ไว้ในระบบนี้ ถ้าต้องการเพิ่มในอนาคตแนะนำให้พิจารณา:

1. หาเครื่อง/เซิร์ฟเวอร์ที่มี GPU (NVIDIA) สำหรับรันส่วนสร้างภาพ/วิดีโอโดยเฉพาะ หรือ
2. เชื่อมต่อบริการสร้างภาพ/วิดีโอผ่าน API ภายนอกแทนการรันเองในเครื่อง

แจ้งได้เมื่อพร้อมดำเนินการต่อในส่วนนี้

## หมายเหตุสำหรับใช้งานจริง (production)

โครงสร้างนี้เป็น baseline ที่ใช้งานได้จริง แต่ก่อนนำไปใช้งานจริงในวงกว้าง ควรพิจารณาเพิ่มเติม:

- ทำ HTTPS ผ่าน reverse proxy ด้านหน้า (เช่น Caddy/Traefik) หรือ Nginx + certbot — จำเป็นเพื่อความปลอดภัยของรหัสผ่านและ session cookie เมื่อเข้าถึงผ่านอินเทอร์เน็ต
- จำกัดอัตราการเรียก (rate limiting) ที่ endpoint `/api/chat` และ `/api/auth/*` (กันสแปม/brute-force)
- สำรองข้อมูล (backup) volume `backend_data` เป็นระยะ เนื่องจากเก็บบัญชีผู้ใช้และประวัติแชททั้งหมด
- ปรับ system prompt ในไฟล์ `backend/src/Config.php` (หรือกำหนด `APP_SYSTEM_PROMPT` / `APP_CODE_SYSTEM_PROMPT` ใน `.env`) ให้เหมาะกับข้อมูลและบริบทองค์กร
- หากต้องการให้ AI ตอบจากข้อมูลภายในองค์กร (เอกสาร นโยบาย ฯลฯ) จะต้องเพิ่มระบบ RAG (ค้นคืนเอกสารมาแนบใน prompt) ซึ่งยังไม่รวมอยู่ใน baseline นี้
