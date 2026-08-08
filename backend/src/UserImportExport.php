<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Excel import/export and single-user PDF report for the admin panel.
 * Expected import columns (header row, any order): username, password,
 * displayName, role, dailyRequestLimit, dailyTokenLimit.
 */
final class UserImportExport
{
    public static function importFromUploadedFile(string $tmpPath): array
    {
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return ['created' => 0, 'errors' => [['row' => 0, 'message' => 'ไฟล์ว่างเปล่า']]];
        }

        $header = array_map(static fn ($h) => mb_strtolower(trim((string) $h)), $rows[0]);
        $colIndex = array_flip($header);

        $created = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }

            $username = trim((string) ($row[$colIndex['username'] ?? -1] ?? ''));
            if ($username === '') {
                continue;
            }

            $password = (string) ($row[$colIndex['password'] ?? -1] ?? '');
            $displayName = (string) ($row[$colIndex['displayname'] ?? -1] ?? '');
            $role = (string) ($row[$colIndex['role'] ?? -1] ?? 'user');

            $reqLimitRaw = $row[$colIndex['dailyrequestlimit'] ?? -1] ?? null;
            $tokenLimitRaw = $row[$colIndex['dailytokenlimit'] ?? -1] ?? null;
            $reqLimit = ($reqLimitRaw === null || $reqLimitRaw === '') ? null : (int) $reqLimitRaw;
            $tokenLimit = ($tokenLimitRaw === null || $tokenLimitRaw === '') ? null : (int) $tokenLimitRaw;

            try {
                Auth::adminCreateUser(
                    $username,
                    $password !== '' ? $password : self::randomPassword(),
                    $displayName,
                    $role,
                    $reqLimit,
                    $tokenLimit
                );
                $created++;
            } catch (Throwable $e) {
                $errors[] = ['row' => $i + 1, 'message' => "{$username}: {$e->getMessage()}"];
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private static function randomPassword(): string
    {
        return bin2hex(random_bytes(6));
    }

    public static function buildUsersSpreadsheet(array $users): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

        $headers = ['ID', 'Username', 'DisplayName', 'Role', 'Active', 'DailyRequestLimit', 'DailyTokenLimit', 'CreatedAt', 'TodayRequests', 'TodayTokens'];
        $sheet->fromArray($headers, null, 'A1');

        $r = 2;
        foreach ($users as $u) {
            $sheet->fromArray([
                $u['id'],
                $u['username'],
                $u['displayName'],
                $u['role'],
                $u['isActive'] ? 'Yes' : 'No',
                $u['dailyRequestLimit'] ?? '',
                $u['dailyTokenLimit'] ?? '',
                $u['createdAt'],
                $u['todayRequests'],
                $u['todayTokens'],
            ], null, "A{$r}");
            $r++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public static function buildUserPdf(array $user, array $usageToday, array $recentUsage): string
    {
        $fontDir = '/var/www/fonts';
        $options = new Options();
        $options->set('defaultFont', 'Sarabun');
        $options->set('isRemoteEnabled', false);
        // chroot ดีฟอลต์ของ Dompdf ไม่ครอบคลุม /var/www/fonts ต้องเปิดเผื่อไว้เอง
        // ไม่งั้น registerFont() จะล้มเหลวเงียบ ๆ (คืนค่า false โดยไม่มี exception)
        $options->setChroot(['/var/www']);
        // เก็บ cache ฟอนต์ไว้ในโฟลเดอร์ที่ www-data เขียนได้ (โฟลเดอร์ vendor/ ดีฟอลต์เขียนไม่ได้ตอนรันจริง)
        $options->setFontDir('/var/www/fontcache');
        $options->setFontCache('/var/www/fontcache');

        $dompdf = new Dompdf($options);
        $fontMetrics = $dompdf->getFontMetrics();
        $fontMetrics->registerFont(['family' => 'Sarabun', 'style' => 'normal', 'weight' => 'normal'], 'file://' . $fontDir . '/Sarabun-Regular.ttf');
        $fontMetrics->registerFont(['family' => 'Sarabun', 'style' => 'normal', 'weight' => 'bold'], 'file://' . $fontDir . '/Sarabun-Bold.ttf');

        $dompdf->loadHtml(self::renderUserPdfHtml($user, $usageToday, $recentUsage), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function renderUserPdfHtml(array $user, array $usageToday, array $recentUsage): string
    {
        $rowsHtml = '';
        foreach ($recentUsage as $day) {
            $rowsHtml .= '<tr><td>' . htmlspecialchars($day['day']) . '</td><td>' . (int) $day['requests'] . '</td><td>' . (int) $day['tokens'] . '</td></tr>';
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="3">ยังไม่มีการใช้งาน</td></tr>';
        }

        $username = htmlspecialchars($user['username']);
        $displayName = htmlspecialchars($user['displayName']);
        $role = $user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป';
        $status = $user['isActive'] ? 'ใช้งานได้' : 'ถูกระงับ';
        $reqLimit = $user['dailyRequestLimit'] ?? 'ไม่จำกัด';
        $tokenLimit = $user['dailyTokenLimit'] ?? 'ไม่จำกัด';
        $todayRequests = (int) $usageToday['requests'];
        $todayTokens = (int) $usageToday['tokens'];
        $generatedAt = htmlspecialchars(date('Y-m-d H:i'));

        return <<<HTML
            <html>
            <head>
            <style>
                body { font-family: Sarabun, sans-serif; font-size: 13px; color: #111; }
                h1 { font-size: 20px; margin-bottom: 4px; }
                h2 { font-size: 15px; margin-top: 24px; }
                .meta { color: #555; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
                th { background: #f3f0ff; }
            </style>
            </head>
            <body>
                <h1>รายงานผู้ใช้งาน: {$displayName}</h1>
                <p class="meta">FAONEX.AI &middot; @{$username} &middot; ออกรายงานเมื่อ {$generatedAt}</p>
                <table>
                    <tr><th>สิทธิ์</th><td>{$role}</td></tr>
                    <tr><th>สถานะ</th><td>{$status}</td></tr>
                    <tr><th>ลิมิตครั้ง/วัน</th><td>{$reqLimit}</td></tr>
                    <tr><th>ลิมิต token/วัน</th><td>{$tokenLimit}</td></tr>
                    <tr><th>คำขอวันนี้</th><td>{$todayRequests}</td></tr>
                    <tr><th>Token วันนี้</th><td>{$todayTokens}</td></tr>
                </table>
                <h2>ประวัติการใช้งาน 30 วันล่าสุด</h2>
                <table>
                    <tr><th>วันที่</th><th>จำนวนครั้ง</th><th>Token</th></tr>
                    {$rowsHtml}
                </table>
            </body>
            </html>
            HTML;
    }
}
