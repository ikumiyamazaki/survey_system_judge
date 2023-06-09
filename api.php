<?php

// データベースと繋ぐ
require('config.php');

// 1. POSTされたデータを受け取る
$request_raw_data = file_get_contents('php://input');

// 2. JSON形式のデータをデコードする => データをPHP上で処理できるような形にする。
$data = json_decode($request_raw_data, true);

// 3. データをPHP(Server-Side)上で処理する！
if( isset($data['WinWidth']) ){
    $uuid_shuffle = $data['uuid_shuffle'];
    $order_type = $data['order_type'];
    $branch_type = $data['branch_type'];
    $device_w = $data['WinWidth'];
    $device_h = $data['WinHeight'];
    $device_info = $data['device_info'];
    $browser_info = $data['browser_info'];

    try {
        // DB接続
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;", $db_user, $db_pass, $db_connect_options);

        // SQL文をセット
        $stmt = $pdo->prepare('INSERT INTO test_manga (uuid, crowd, branchType, device_Width, device_Height, device_Info, browser_Info) VALUES(:uuid, :order_type, :branch_type, :device_w, :device_h, :device_info, :browser_info)');

        // 値をセット
        $stmt->bindValue(':uuid', $uuid_shuffle);
        $stmt->bindValue(':order_type', $order_type);
        $stmt->bindValue(':branch_type', $branch_type);
        $stmt->bindValue(':device_w', $device_w);
        $stmt->bindValue(':device_h', $device_h);
        $stmt->bindValue(':device_info', $device_info);
        $stmt->bindValue(':browser_info', $browser_info);

        if($DEBUG){
            $stmt->debugDumpParams();
        }        // SQL実行
        $stmt->execute();
    } catch (PDOException $e) {
        // エラー発生
        echo "database error";
    } finally {
        // DB接続を閉じる
        $pdo = null;
    }
    // echo "{\"status\": \"success\"}";

    // 4. echo するとClient-Sideにデータを返却することができる！ => JSON形式にして返す
    $response = $data;
    echo json_encode($response);
} else {
    echo "{\"status\": \"error\"}";
}

$response = $data;

?>