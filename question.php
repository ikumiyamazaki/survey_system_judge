<head>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>アンケート</title>
</head>
<?php
// 設定関係を config.php に分離
require('config.php');

$DEBUG = FALSE; // TRUEにしたら色々出力する

$xml = simplexml_load_file( "drive.xml" );

//jsからデータを受け取る
// $request_raw_data = file_get_contents('php://input');

//jsからのデータをデコードする
// $data = json_decode($request_raw_data);

if( $xml == FALSE ){
    echo "error: xml load error: drive.xml";
    exit(1);
}

if( !isset($_POST['order_type']) ){
    // 個人の識別IDを生成する
    $uuid = uniqid(bin2hex(random_bytes(1)));
    $uuid_shuffle = str_shuffle($uuid);

    // 初回〜4問目：まだ順序は同じなので0を入れておく
    // $order_type = 0;
    $order_type = rand(1, $xml->config->orders->judge->count());
    //分岐後どっちに行ったか（はじめはまだ分岐してないから0）
    $branch_type = 0;
    //最初は共通の問題順序
    // $question_array_str = $xml->config->orders->judge[0];
    $question_array_str = $xml->config->orders->judge[$order_type-1];
    $no_of_question = 1;

    //最初デバイスサイズは0、デバイス情報はnothingにしておく
    // $device_w = $data->WinWidth;
    // $device_h = $data->WinHeight;
    // $device_info = $data->device_info;
    // $browser_info = $data->browser_info;

    // try {
    //     // DB接続
    //     $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;", $db_user, $db_pass, $db_connect_options);

    //     // SQL文をセット
    //     $stmt = $pdo->prepare('INSERT INTO test_manga (uuid, crowd, device_Width, device_Height, device_Info, browser_Info) VALUES(:uuid, :order_type, :device_w, :device_h, :device_info, :browser_info)');

    //     // 値をセット
    //     $stmt->bindValue(':uuid', $uuid_shuffle);
    //     $stmt->bindValue(':order_type', $order_type);
    //     $stmt->bindValue(':device_w', $device_w);
    //     $stmt->bindValue(':device_h', $device_h);
    //     $stmt->bindValue(':device_info', $device_info);
    //     $stmt->bindValue(':browser_info', $browser_info);

    //     if($DEBUG){
    //         $stmt->debugDumpParams();
    //     }        // SQL実行
    //     $stmt->execute();
    // } catch (PDOException $e) {
    //     // エラー発生
    //     echo "database error";
    // } finally {
    //     // DB接続を閉じる
    //     $pdo = null;
    // }
} else {
    // 2回目以降
    $order_type = $_POST['order_type'];
    $branch_type = $_POST['branch_type'];
    $no_of_question = $_POST['no_of_question'];
    $question_array_str =  $_POST['question_array'];
    $uuid_shuffle = $_POST['uuid_shuffle'];

    //デバイスサイズとデバイス情報の取得は最初のアクセスの次からしかできない
    // $device_w = $_POST["WinWidth"];
    // $device_h = $_POST["WinHeight"];
    // $device_info = $_POST["deviceInfo"];
    // $browser_info = $_POST["browserInfo"];
    $answerTime = $_POST["answerTime"];

    try {
        // DB接続
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;", $db_user, $db_pass, $db_connect_options);

        // SQL文をセット
        $stmt = $pdo->prepare('INSERT INTO test_manga_answer (uuid, questionNum, ans, answerTime) VALUES(:uuid, :question_num, :ans, :answerTime)');

        //$post_answer = $xml->questions->question[(int)$order_type-1]->name;
        //$answer = $_POST[(string)$post_answer];
        foreach($_POST as $key => $value) {
            //echo $key.":".$value."<br>" ;
            if( preg_match('/^Q\-[a-z]/', $key)){
                $answer = $value;
            }
        }
        // 値をセット
        $stmt->bindValue(':uuid', $uuid_shuffle);
        $stmt->bindValue(':question_num', $no_of_question-1);
        $stmt->bindValue(':answerTime', $answerTime);
        $stmt->bindValue(':ans', $answer);

        if($DEBUG){
            $stmt->debugDumpParams();
        }
        // SQL実行
        $stmt->execute();

        //デバイスサイズのupdate文
        // $stmt = $pdo->prepare("UPDATE test_manga SET device_Width = :device_w, device_Height = :device_h, device_Info = :device_info, browser_Info = :browser_info WHERE uuid = :uuid");

        // $stmt->bindValue(':uuid', $uuid_shuffle);
        // $stmt->bindValue(':device_w', $device_w);
        // $stmt->bindValue(':device_h', $device_h);
        // $stmt->bindValue(':device_info', $device_info);
        // $stmt->bindValue(':browser_info', $browser_info);

        // $stmt->execute();

        if( $no_of_question == 5 ){
            //3つの設問の回答時間とってくるselect文
            // $stmt = $pdo->prepare("SELECT answerTime FROM answer_table WHERE uuid = :uuid AND questionNum = :questionNum");
            $stmt = $pdo->prepare("SELECT answerTime FROM test_manga_answer WHERE uuid = :uuid");

            $stmt->bindValue(':uuid', $uuid_shuffle);
            // $stmt->bindValue(':questionNum', $no_of_question-1);

            $stmt->execute();

            foreach ($stmt as $raw) {
                //回答時間をforで3問持ってきて、足していく
                $time = (int)$raw['answerTime'];
                $total_time += $time;
            }
            //3問の合計回答時間
            // var_dump($total_time);

            //3問の合計回答時間が10000ミリ秒以下だったら
            if( $total_time <= 15000 ){
                //最初群
                $branch_type = 1;
            } else {
                //最後群
                $branch_type = 2;
            }
            $question_array_str_forking = $xml->config_test->orders->order[$branch_type-1];
            // var_dump($question_array_str_forking);

            //branch_typeをUPDATEする文
            $stmt = $pdo->prepare("UPDATE test_manga SET branchType = :branch_type WHERE uuid = :uuid");

            $stmt->bindValue(':uuid', $uuid_shuffle);
            $stmt->bindValue(':branch_type', $branch_type);

            $stmt->execute();
        }
        if( $no_of_question >= 4 ){
            $question_array_str_forking = $xml->config_test->orders->order[$branch_type-1];
        }

    } catch (PDOException $e) {
        // エラー発生
        echo "database error";
    } finally {
        // DB接続を閉じる
        $pdo = null;
    }
}
$question_array = preg_split("/,/", $question_array_str . "," . $question_array_str_forking);

//設問数の取得
$total_questions = $xml->questions->question->count();

if( $DEBUG ){
    echo "----------- for debug -----------<br>";
    foreach($_POST as $key => $value) {
        //値が空の場合？
        if(is_array($value)){
            echo $key . " [";
            for($i=0; $i<count($value); $i++){
                echo $value[$i] . ", ";
            }
            echo "]<BR />";
        } else {
            echo $key . " : " . $value . "<BR />";
        }
    }
    echo "----------- for debug -----------<br>";
}

if( $no_of_question <= count($question_array) ){
    echo "<div id=question>";
    echo "<h3>" . $no_of_question . "問目</h3>";
    echo "<form method=\"POST\" action=\"question.php\" id=\"question_form\">";
    // echo "<span id=\"WinWidth\"></span>";

    foreach ($xml->questions->question as $question) {
        if( $question->id == $question_array[$no_of_question-1] ){
            echo "<div id=question_text>" . $question->text . "</div>";
            echo "<div id=question_sub_text>" . $question->subtext."</div><br>";
            echo "<div id=answer>";
            if( $question->answer_type == "text" ){
                echo "<input id=input_text type=text size=30 name=" . $question->name . " onkeyup=\"userInput(this)\">";
            } elseif( $question->answer_type == "textarea" ){
                echo "<textarea id=input_textarea rows=10 cols=50 name=" . $question->name . " onkeyup=\"userInput(this)\"></textarea><br>";
            } elseif( $question->answer_type == "radio" ){
                foreach ($question->answer->item as $item){
                    echo "<label><input type='radio' name='".$question->name."' value='".$item->value."' onclick=\"userInput(this)\">" . $item->text . "</label><br>";
                }
            } elseif( $question->answer_type == "check" ){
                foreach ($question->answer->item as $item){
                    echo "<label><input type='checkbox' name='".$question->name."' value='".$item->value."' onclick=\"userInput(this)\">" . $item->text . "</label><br>";
                }
            }
            echo "</div>";
            // どんなAnswer Typeかも送信しよう！（チェックボックスを受け取るため）
            echo "<input type=\"hidden\" name=\"answer_type\" value=\"" . $question->answer_type ."\">";
        }
    }
    echo "</div>";
?>
    <input type="hidden" name="order_type" value="<?php echo $order_type; ?>">
    <input type="hidden" name="branch_type" value="<?php echo $branch_type; ?>">
    <input type="hidden" name="no_of_question" value="<?php echo $no_of_question+1; ?>">
    <input type="hidden" name="question_array" value="<?php echo $question_array_str; ?>">
    <input type="hidden" name="uuid_shuffle" value="<?php echo $uuid_shuffle; ?>">
    <div id="question_info">現在<?php echo $total_questions; ?>問中<?php echo $no_of_question; ?>問目です&nbsp;&nbsp;&nbsp;<input id=submit_button type="submit" disabled value="次へ" onclick="stop()"></div>
</form>
<?php
} else {
    echo "<div class=\"last_page\">";
    echo "<h4>アンケートへの回答は以上です。ありがとうございました。<br>";
    echo "以下のコードとIDをクラウドソーシングの画面に戻って入力してください。<br>";
    echo "こちらを終わらせないと、アンケートに回答したことにならないためご注意ください。</h4><br>";
    //最後のコードとidを表示するやつ
    echo "<p><strong>コード&nbsp;&nbsp;&nbsp;W</strong></p><br>";
    echo "<p><strong>ID&nbsp;&nbsp;&nbsp;<input id='copyTarget' type='text' value='".$uuid_shuffle."' readonly></strong></p>";
    echo "<p><button onclick=\"copyToClipboard()\">IDをクリップボードにコピー</button></p>";
    echo "</div>";
}
?>

<script>
    var startTime = null;
    function userInput( node ){
        //console.log(node.value);
        // 入力があったら #submit_button を使えるようにする
        document.querySelector("#submit_button").disabled = false;
    }

    function copyToClipboard() {
        // コピー対象をJavaScript上で変数として定義する
        var copyTarget = document.getElementById("copyTarget");
        // コピー対象のテキストを選択する
        copyTarget.select();
        // 選択しているテキストをクリップボードにコピーする
        document.execCommand("Copy");
        // コピーをお知らせする
        //alert("コピーできました！ : " + copyTarget.value);
    }

    //デバイスサイズとデバイス情報を取得する
    window.addEventListener = ('load', function(){

        // hidden に埋め込んで送信
        var ow = window.outerWidth;
        var oh = window.outerHeight;

        var ua = navigator.userAgent;
        if( ua.indexOf('iPhone') > 0){
            var deviceInfo = "iPhone";
        } else if( ua.indexOf('Android') > 0 ){
            var deviceInfo = "Android";
        } else if( ua.indexOf('iPad') > 0 && 'ontouchend' ){
            var deviceInfo = "iPad";
        } else if( ua.indexOf('Mobile') > 0 ) {
            var deviceInfo = "Mobile";
        } else {
            var deviceInfo = "PC";
        }

        var agent = window.navigator.userAgent.toLowerCase();
        if (agent.indexOf("msie") != -1 || agent.indexOf("trident") != -1) {
            var browserInfo = "Internet Explorer";
        } else if (agent.indexOf("edg") != -1 || agent.indexOf("edge") != -1) {
            var browserInfo = "Edge";
        } else if (agent.indexOf("opr") != -1 || agent.indexOf("opera") != -1) {
            var browserInfo = "Opera";
        } else if (agent.indexOf("chrome") != -1) {
            var browserInfo = "Chrome";
        } else if (agent.indexOf("safari") != -1) {
            var browserInfo = "Safari";
        } else if (agent.indexOf("firefox") != -1) {
            var browserInfo = "FireFox";
        }

        // document.querySelector("#question_form").innerHTML
        //     += "<input type=hidden name=\"WinWidth\" value=\"" + ow + "\"><input type=hidden name=\"WinHeight\" value=\"" + oh + "\"><input type=hidden name=\"deviceInfo\" value=\"" + deviceInfo + "\"><input type=hidden name=\"browserInfo\" value=\"" + browserInfo + "\">";
    
        //データを非同期で送って、1問目からデバイスサイズやデバイス情報をデータベースに入れる
        var uuidShuffle = "<?php echo $uuid_shuffle; ?>";
        
        const parameter = {
            uuid_shuffle: uuidShuffle,
            order_type: <?php echo $order_type; ?>,
            branch_type: <?php echo $branch_type; ?>,
            WinWidth: ow,
            WinHeight: oh,
            device_info: deviceInfo,
            browser_info: browserInfo
        };

        //問題番号を取得
        var noOfQuestion = <?php echo $no_of_question; ?>;
        //設問が1問目なら、デバイスサイズやデバイス情報をfetchして送る
        if( noOfQuestion == 1 ){
            fetch('api.php',
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(parameter),
            }
            )
            .then(response => response.json())
            .then(res => {
                //consoleで出力
                console.log({res});
            })
            .catch(error => {
                // エラー発生の場合の catch & console出力
                console.log({error});
            });
        }
    })

    function start(){
        startTime = new Date();
    }
    function stop() {
        var stopTime = new Date();
        var answerTime = stopTime.getTime() - startTime.getTime();
        console.log("経過時間(ミリ秒):", answerTime);
        document.querySelector("#question_form").insertAdjacentHTML("afterbegin", "<input type=hidden name=\"answerTime\" value=\"" + answerTime + "\">");

    }
    window.addEventListener('load', start());

</script>