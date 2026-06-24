<?php

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$nama       = $data['nama'] ?? '';
$email      = $data['email'] ?? '';
$whatsapp   = $data['whatsapp'] ?? '';
$instansi   = $data['instansi'] ?? '';
$keperluan  = $data['keperluan'] ?? '';
$subjek     = $data['subjek'] ?? '';
$pesan      = $data['pesan'] ?? '';

if (
    empty($nama) ||
    empty($email) ||
    empty($keperluan) ||
    empty($subjek) ||
    empty($pesan)
) {
    echo json_encode([
        'status' => false,
        'message' => 'Data tidak lengkap'
    ]);
    exit;
}

$message = "*Halo, Admin HMSI!*\n\n";
$message .= "👤 Nama: $nama\n";
$message .= "📧 Email: $email\n";
$message .= "📱 WhatsApp: $whatsapp\n";
$message .= "🏫 Fakultas / Instansi: $instansi\n";
$message .= "📌 Keperluan: $keperluan\n";
$message .= "📝 Subjek: $subjek\n\n";
$message .= "💬 Pesan:\n$pesan";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.fonnte.com/send",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'target'  => '081901694586', // Ganti dengan nomor HMSI
        'message' => $message
    ],
    CURLOPT_HTTPHEADER => [
        'Authorization: vVWkQpPC27NoEwFWSGJ3'
    ]
]);

$response = curl_exec($curl);
$error = curl_error($curl);

curl_close($curl);

if ($error) {
    echo json_encode([
        'status' => false,
        'message' => $error
    ]);
} else {
    echo json_encode([
        'status' => true,
        'message' => 'Pesan berhasil dikirim'
    ]);
}