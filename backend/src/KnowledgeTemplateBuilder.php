<?php

/** Generates a downloadable .docx guide/example for writing good AI-answer content before uploading it as a PDF. */
final class KnowledgeTemplateBuilder
{
    public static function build(): \PhpOffice\PhpWord\PhpWord
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Sarabun');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $section->addText('ฟอร์มตัวอย่าง: การเขียนคำตอบสำหรับระบบความรู้ AI (FAONEX.AI)', ['bold' => true, 'size' => 16]);
        $section->addTextBreak();

        $section->addText(
            'ใช้ฟอร์มนี้เป็นแนวทางเตรียมเนื้อหาก่อนกรอกในช่อง "เพิ่มคำตอบด้วยตัวเอง" หรือก่อนบันทึกเป็นไฟล์ PDF เพื่ออัปโหลดเข้าระบบ ' .
            'เมื่อคำถามของผู้ใช้ตรงกับหัวข้อที่กำหนดไว้ AI จะตอบโดยอ้างอิงจากเนื้อหาที่เตรียมไว้นี้เท่านั้น'
        );
        $section->addTextBreak();

        $section->addText('หลักการเขียนคำตอบที่ดี', ['bold' => true, 'size' => 13]);
        $bulletStyle = ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED];
        $points = [
            'เขียนให้ชัดเจน ตรงประเด็น ใช้ประโยคสมบูรณ์ ไม่ใช้คำย่อที่กำกวม',
            'แยกแต่ละหัวข้อย่อยเป็นคนละย่อหน้า หรือใช้หัวข้อ/bullet เพื่อให้ AI ค้นหาส่วนที่เกี่ยวข้องได้แม่นยำ',
            'ระบุข้อมูลที่เป็นข้อเท็จจริงให้ครบ เช่น วันที่ จำนวนเงิน เบอร์โทร เงื่อนไข ไม่ปล่อยให้ AI ต้องเดา',
            'แต่ละย่อหน้าควรอ่านเข้าใจได้ในตัวเอง โดยไม่ต้องอ้างอิงย่อหน้าอื่นประกอบ (เพราะระบบอาจดึงมาใช้แยกจากกัน)',
            'หลีกเลี่ยงข้อมูลที่ขัดแย้งกันเองในเอกสารเดียวกัน หรือระหว่างเอกสารในหัวข้อเดียวกัน',
            'อัปเดตเนื้อหาให้ตรงกับความเป็นจริงอยู่เสมอ ลบหรือแก้ไขคำตอบเดิมเมื่อข้อมูลเปลี่ยนแปลง',
        ];
        foreach ($points as $point) {
            $section->addListItem($point, 0, null, $bulletStyle);
        }
        $section->addTextBreak();

        $section->addText('ตัวอย่างการเขียน', ['bold' => true, 'size' => 13]);
        $section->addText('หัวข้อ: การสมัครเรียน', ['bold' => true]);
        $section->addText('คำค้น (Rule) ที่ควรตั้งไว้: สมัครเรียน, รับสมัคร, ใบสมัคร, enrollment, admission');
        $section->addTextBreak();

        $section->addText('คำตอบตัวอย่าง:', ['italic' => true]);
        $section->addText(
            'โรงเรียนเปิดรับสมัครนักเรียนใหม่ทุกปีการศึกษา ระหว่างวันที่ 1-31 มีนาคม ' .
            'เอกสารที่ต้องใช้ในการสมัครเรียน ได้แก่ สำเนาบัตรประชาชนนักเรียน สำเนาทะเบียนบ้าน และรูปถ่ายขนาด 1 นิ้ว จำนวน 2 รูป ' .
            'ค่าธรรมเนียมแรกเข้า 500 บาท ชำระที่ฝ่ายการเงินของโรงเรียนในวันสมัคร สอบถามเพิ่มเติมได้ที่ฝ่ายทะเบียน โทร 02-123-4567'
        );
        $section->addTextBreak();

        $section->addText('เมื่อไม่มีข้อมูลที่เกี่ยวข้อง', ['bold' => true, 'size' => 13]);
        $section->addText(
            'ตั้ง "ข้อความสำรอง" ของหัวข้อไว้ล่วงหน้า เช่น "ขออภัย ไม่พบข้อมูลเรื่องนี้ กรุณาติดต่อฝ่ายทะเบียนโดยตรง" ' .
            'ระบบจะใช้ข้อความนี้ตอบแทนโดยอัตโนมัติเมื่อค้นไม่พบเนื้อหาที่เกี่ยวข้องพอ'
        );
        $section->addTextBreak();

        $section->addText(
            'เตรียมเนื้อหาตามฟอร์มนี้แล้วนำไปกรอกในช่อง "เพิ่มคำตอบด้วยตัวเอง" ได้ทันที หรือจะบันทึกเป็นไฟล์ PDF แล้วอัปโหลดเข้าหัวข้อภายหลังก็ได้ ' .
            'ทั้งสองแบบใช้แทนกันได้และใช้ค้นหาร่วมกันในหัวข้อเดียวกัน',
            ['italic' => true]
        );

        return $phpWord;
    }
}
