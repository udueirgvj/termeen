<?php

// ===== الإعدادات =====
$token = "PUT_YOUR_TOKEN_HERE";
$admin_id = "6001517585";

// Firebase
$firebase_url = "https://tygggfs-default-rtdb.asia-southeast1.firebasedatabase.app";

$api = "https://api.telegram.org/bot$token/";

// ===== دالة إرسال =====
function send($chat_id, $text) {
    global $api;
    file_get_contents($api."sendMessage?chat_id=$chat_id&text=".urlencode($text));
}

// ===== Firebase Functions =====
function fb_get($path) {
    global $firebase_url;
    return json_decode(file_get_contents($firebase_url."/$path.json"), true);
}

function fb_set($path, $data) {
    global $firebase_url;
    file_get_contents($firebase_url."/$path.json", false, stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => "Content-Type: application/json",
            'content' => json_encode($data)
        ]
    ]));
}

function fb_push($path, $data) {
    global $firebase_url;
    file_get_contents($firebase_url."/$path.json", false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json",
            'content' => json_encode($data)
        ]
    ]));
}

// ===== استقبال =====
$update = json_decode(file_get_contents("php://input"), true);
$message = $update["message"] ?? null;

if (!$message) exit;

$chat_id = $message["chat"]["id"];
$text = $message["text"] ?? "";

// ===== المستخدم =====
$user = fb_get("users/$chat_id");

if (!$user) {
    $user = [
        "balance" => 0,
        "messages" => 0,
        "banned" => false
    ];
    fb_set("users/$chat_id", $user);
}

// ===== حظر =====
if ($user["banned"]) {
    send($chat_id, "🚫 انت محظور");
    exit;
}

// ===== أوامر الأدمن =====
if ($chat_id == $admin_id) {

    if (strpos($text, "/ban") === 0) {
        $id = trim(str_replace("/ban", "", $text));
        $u = fb_get("users/$id");
        if ($u) {
            $u["banned"] = true;
            fb_set("users/$id", $u);
            send($chat_id, "✅ تم الحظر");
        }
        exit;
    }

    if (strpos($text, "/add") === 0) {
        $parts = explode(" ", $text);
        $id = $parts[1];
        $amount = $parts[2];

        $u = fb_get("users/$id");
        if ($u) {
            $u["balance"] += $amount;
            fb_set("users/$id", $u);
            send($chat_id, "✅ تم إضافة رصيد");
        }
        exit;
    }
}

// ===== رفع ملف =====
if (isset($message["document"]) && $chat_id == $admin_id) {

    $file_id = $message["document"]["file_id"];

    $file_info = json_decode(file_get_contents($api."getFile?file_id=$file_id"), true);
    $file_path = $file_info["result"]["file_path"];

    $file_url = "https://api.telegram.org/file/bot".$token."/".$file_path;

    $content = file_get_contents($file_url);
    $data = json_decode($content, true);

    if (!$data) {
        send($chat_id, "❌ الملف غير صالح");
        exit;
    }

    foreach ($data as $row) {
        fb_push("people", $row);
    }

    send($chat_id, "✅ تم رفع البيانات إلى Firebase");
    exit;
}

// ===== نظام الاشتراك =====
if ($user["messages"] >= 5 && $user["balance"] <= 0) {
    send($chat_id, "❌ انتهت المحاولات، اشترك");
    exit;
}

// ===== البحث =====
if ($text && $text != "/start") {

    $data = fb_get("people");

    if (!$data) {
        send($chat_id, "❌ لا توجد بيانات");
        exit;
    }

    $found = false;

    foreach ($data as $row) {

        if (mb_strpos($row["name"], $text) !== false) {

            $msg = "✅ تم العثور\n\n";
            $msg .= "👤 الاسم: ".$row["name"]."\n";
            $msg .= "🎂 العمر: ".$row["age"]."\n";
            $msg .= "💼 الوظيفة: ".$row["job"]."\n";
            $msg .= "🚫 مسجون: ".$row["prison_status"]."\n";
            $msg .= "📌 معلومات: ".$row["other_info"];

            send($chat_id, $msg);
            $found = true;
        }
    }

    if (!$found) {
        send($chat_id, "❌ لا توجد نتائج");
    }

    $user["messages"]++;
    fb_set("users/$chat_id", $user);
}
