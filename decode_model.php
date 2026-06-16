<?php
$content = file_get_contents('app/Models/Attendance.php');
if (preg_match('/eval\(gzinflate\(base64_decode\(\'(.*?)\'\)\)\);/', $content, $matches)) {
    $decoded = gzinflate(base64_decode($matches[1]));
    file_put_contents('decoded_attendance_model.php', $decoded);
    echo "Decoded successfully.\n";
} else {
    echo "Pattern not found.\n";
}
