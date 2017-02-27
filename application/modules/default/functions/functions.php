<?php
/**
 * 開発プログラム
 * 
 * 共通関数
 * 
 */


// コンポーネントをロードする
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Controller/Request/Http.php';
require_once 'Zend/Session.php';
require_once 'Zend/Validate.php';
require_once 'Zend/Validate/NotEmpty.php';
require_once 'Zend/Validate/StringLength.php';
require_once 'Zend/Validate/Alnum.php';
require_once 'Zend/Validate/Date.php';
require_once 'Zend/Validate/EmailAddress.php';
require_once 'Zend/Validate/Digits.php';
require_once 'Zend/Mail.php';
require_once 'Zend/Mail/Transport/Smtp.php';
require_once 'Zend/Date.php';
//require_once '../application/modules/default/functions/ValidateEmail.php';

require_once 'Zend/Uri.php';

require_once 'Zend/Log.php';
require_once 'Zend/Log/Writer/Stream.php';
require_once 'Zend/Db.php';


/**
 * バリデートチェックする
 *
 * @param		string	$id					フォーム識別
 * @param		string	$type				バリデータ種類（empty,length,alnum,date,email）
 * @param		string	$data				入力データ
 * @param 		int   		$min				    最小文字数
 * @param 		int	    	$max     			最大文字数
 * @return		array		$errMessage		エラーデータ
 */
function validateData($id,$type,$data,$min,$max,$confirm)
{
    // 内部文字コードをUTF-8に設定する
    iconv_set_encoding('internal_encoding', 'UTF-8');

    // インスタンスを生成する
    $validator1 = new Zend_Validate_NotEmpty();
    $validator2 = new Zend_Validate_StringLength($min, $max);
    $validator3 = new Zend_Validate_Alnum();
    $validator4 = new Zend_Validate_Date();
    $validator5 = new My_Validate_EmailAddress();
    $validator7 = new Zend_Validate_Digits();

    // エラーメッセージを初期化
    $errMessage = array();

    // エラーメッセージの各種設定
    $lang = 'ja';

    // 空かどうかのチェック
    if (isset($type) and $type == 'empty') {
        // メッセージ情報を初期化する
        $empty = '入力して下さい。';
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        // チェック処理を実行する
        if (!$validator1->isValid($data)) {
            $error = $validator1->getMessages();
            foreach ($error as $value) {
                $errMessage["$id"] = $value;
            }
        }
        // 入力サイズのみのチェック
    } elseif (isset($type) and $type == 'length') {
        // メッセージを初期化する
        $empty = '入力して下さい。';
        $length = $min.'以上'.$max.'以下で入力して下さい。';
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        $validator2->setMessage($length);
        // バリデータチェインを作成する
        $validatorChain2 = new Zend_Validate();
        $validatorChain2->addValidator($validator1, true)
            ->addValidator($validator2);
        // チェック処理を実行する
        if (!$validatorChain2->isValid($data)) {
            $error = $validatorChain2->getMessages();
            foreach ($error as $value) {
                $errMessage["$id"] = $value;
            }
        }
        $validatorChain2 = null;
        // 入力サイズと英数字のチェック
    } elseif (isset($type) and $type == 'alnum') {
        // メッセージを初期化する
        $empty = '入力して下さい。';
        $length = $min.'以上'.$max.'以下で入力して下さい。';
        $alnum = '半角英数字で入力して下さい。';
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        $validator2->setMessage($length);
        $validator3->setMessage($alnum);
        // バリデータチェインを作成する
        $validatorChain3 = new Zend_Validate();
        $validatorChain3->addValidator($validator1, true)
            ->addValidator($validator2)
            ->addValidator($validator3);
        // チェック処理を実行する
        if (!$validatorChain3->isValid($data)) {
            $error = $validatorChain3->getMessages();
            foreach ($error as $value) {
                $errMessage["$id"] = $value;
            }
        }
        $validatorChain3 = null;
        // 日付形式のチェック
    } elseif (isset($type) and $type == 'date') {
        // メッセージを初期化する
        $empty = '入力して下さい。';
        $date = '日付の形で入力して下さい。';
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        $validator4->setMessage($date);
        // バリデータチェインを作成する
        $validatorChain4 = new Zend_Validate();
        $validatorChain4->addValidator($validator1, true)
            ->addValidator($validator4);
        // チェック処理を実行する
        if (!$validatorChain4->isValid($data)) {
            $error = $validatorChain4->getMessages();
            foreach ($error as $value) {
                $errMessage["$id"] = $value;
            }
        }
        $validatorChain4 = null;
        // パスワードのチェック
    } elseif (isset($type) and $type == 'passwd') {
        // メッセージを初期化する
        $empty = '入力して下さい。';
        $length = $min.'以上'.$max.'以下で入力して下さい。';
        $alnum = '半角英数字で入力して下さい。';
        $equal = 'パスワードを入力して下さい。';
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        $validator2->setMessage($length);
        $validator3->setMessage($alnum);
        // バリデータチェインを作成する
        $validatorChain6 = new Zend_Validate();
        $validatorChain6->addValidator($validator1, true)
            ->addValidator($validator2)
            ->addValidator($validator3);
        // 入力確認チェック
        if ($data !== $confirm) {
            //$str[] = $equal;
            $errMessage["$id"] = 'パスワードが一致しません。';
        }
        // チェック処理を実行する
        if (!$validatorChain6->isValid($data)) {
            $error = $validatorChain6->getMessages();
            foreach ($error as $value) {
                $ary["$id"] = $value;
            }
            //$ary["$id"] = $key;
            $errMessage = array_merge($errMessage, $ary);
        }

        $validatorChain6 = null;

        // 全角カタカナかチェックする
    }  elseif (isset($type) and $type == 'kana') {
        // メッセージを初期化する
        $empty = '入力して下さい。';
        $kana = '全角カタカナで入力して下さい。';
        // カタカナチェック
        if (!preg_match("/^[ァ-ヶー]+$/u", $data)) {
            //$errMessage["$id"] = $kana;
            $temp = $getText->getText($lang,'','','',$kana);
            $errMessage["$id"] = $temp;
        }
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        // チェック処理を実行する
        if (!$validator1->isValid($data)) {
            $error = $validator1->getMessages();
            foreach ($error as $value) {
                $ary["$id"] = $value;
            }
            $errMessage = array_merge($errMessage, $ary);
        }

        // 数字のみかチェックする
    } elseif (isset($type) and $type == 'digits') {
        // メッセージを初期化する
        $empty = '入力して下さい。';
        $digits = '数字だけで入力して下さい。';
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        $validator7->setMessage($digits);
        // バリデータチェインを作成する
        $validatorChain7 = new Zend_Validate();
        $validatorChain7->addValidator($validator1, true)
            ->addValidator($validator7);
        // チェック処理を実行する
        if (!$validatorChain7->isValid($data)) {
            $error = $validatorChain7->getMessages();
            foreach ($error as $value) {
                $errMessage["$id"] = $value;
            }
        }
        $validatorChain7 = null;
    } elseif (isset($type) and $type == 'email') {
        // メッセージを初期化する
        $empty = '入力して下さい。';
        $email = 'メールの形で入力して下さい。';
        $equal = 'メールアドレスが一致しません。';
        // エラーメッセージを設定する
        $validator1->setMessage($empty);
        $validator5->setMessage($email);
        // バリデータチェインを作成する
        $validatorChain5 = new Zend_Validate();
        $validatorChain5->addValidator($validator1, true)
            ->addValidator($validator5);
        // 入力確認チェック
        if ($data !== $confirm) {
            //$str[] = $equal;
            //$errMessage["$id"] = $equal;
            $errMessage["$id"] = 'メールアドレスが一致しません。';
        }
        // チェック処理を実行する
        if (!$validatorChain5->isValid($data)) {
            $error = $validatorChain5->getMessages();
            //$error = '正しくない';
            foreach ($error as $value) {
                $ary["$id"] = $value;
            }
            $errMessage = array_merge($errMessage, $ary);
        }
        $validatorChain5 = null;
        // URIかどうか
    } elseif (isset($type) and $type == 'url') {
        // メッセージ情報を初期化する
        $url = '正しくURLを入力してください';

        if (!Zend_Uri::check($data)) {
            $errMessage["$id"] = $url;
        }
    }
    // バリデータを消す
    $validator1 = null;
    $validator2 = null;
    $validator3 = null;
    $validator4 = null;
    $validator5 = null;
    $validator7 = null;

    return $errMessage;

}

/**
 * メール送信処理を行う
 *
 * @param		array			$from		送信者データ
 * @param		array			$toAll		送信宛メールアドレスデータ
 * @param		string		$subject	メール件名
 * @param		string		$body		メール内容
 * @return		int				$toCount	送信先メール数
 */
function mailSending($from,$toAll,$subject,$body)
{

    // 文字コードを設定する
    $mailCharset = 'ISO-2022-JP';
    $crrCharset = 'UTF-8';

    // メールの内容を設定する
    $fromName = (isset($from['name'])) ? $from['name'] : 'Atreate Web System';
    $fromEmail = (isset($from['email'])) ? $from['email'] : 'web@atreate.com';


    // 強制的にASEANCAREER アドレスから送信
//    $fromEmail = "info@aseancareer.asia";


    // 文字コードを「ISO-2022-JP」に変更する
    $fromName = mb_convert_encoding($fromName, $mailCharset, $crrCharset);
    $subject = mb_convert_encoding($subject, $mailCharset, $crrCharset);
    $body = mb_convert_encoding($body, $mailCharset, $crrCharset);


//	// SMTP認証設定
	$config = array(	 'auth'		=>	'login',
						 'username'	=>	'atreate124@gmail.com',
						 'password'	=>	'tsuji1710',
						 'ssl'		=>	'ssl'
					);
    // SMTP認証設定
//    $config = array(	 'auth'		=>	'login',
//        'username'	=>	'AKIAJJ5S25BBAJBOG6RA',
//        'password'	=>	'AjhE8sbGgphBamNXowfRf9pVfqfCNP0P5n4/DZ5ayK24',
//        'ssl'		=>	'tls',
//        'port'		=> 587
//    );
//					repitamail12



    // SMTPメールサーバの設定
	$smtp = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $config);
//    $smtp = new Zend_Mail_Transport_Smtp('email-smtp.us-west-2.amazonaws.com', $config);
    $transport = Zend_Mail::setDefaultTransport($smtp);

    // 送信するメール全てで使う From 及び Reply-To のアドレス及び名前を設定します
    Zend_Mail::setDefaultFrom($fromEmail, $fromName);
    Zend_Mail::setDefaultReplyTo($fromEmail, $fromName);

    // 初期化
    $toCount = 0;

    // メッセージをループ処理します
    foreach ($toAll as $to) {
        $mail = new Zend_Mail($mailCharset);
        $mail->addTo($to);
        $mail->setSubject($subject);
        $mail->setBodyText($body);
        $mail->send($transport);
        $toCount ++;
    }

    // 既定値をリセットします
    Zend_Mail::clearDefaultFrom();
    Zend_Mail::clearDefaultReplyTo();

    return $toCount;
}
/**
 * メール送信処理を行う
 *
 * @param		array			$from		送信者データ
 * @param		array			$toAll		送信宛メールアドレスデータ
 * @param		string		$subject	メール件名
 * @param		string		$body		メール内容
 * @return		int				$toCount	送信先メール数
 */
function GccMailSending($from,$toAll,$subject,$body)
{

    // 文字コードを設定する
    $mailCharset = 'ISO-2022-JP';
    $crrCharset = 'UTF-8';

    // メールの内容を設定する
    $fromName = (isset($from['name'])) ? $from['name'] : '';
    $fromEmail = (isset($from['email'])) ? $from['email'] : 'info@gcc-kanagawa.jp';

    // 文字コードを「ISO-2022-JP」に変更する
    $fromName = mb_convert_encoding($fromName, $mailCharset, $crrCharset);
    $subject = mb_convert_encoding($subject, $mailCharset, $crrCharset);
    $body = mb_convert_encoding($body, $mailCharset, $crrCharset);


//	// SMTP認証設定
//	$config = array(	 'auth'		=>	'login',
//						 'username'	=>	'atreate124@gmail.com',
//						 'password'	=>	'tsuji1710',
//						 'ssl'		=>	'ssl'
//					);
    // SMTP認証設定
    $config = array(	 'auth'		=>	'login',
        'username'	=>	'info@gcc-kanagawa.jp',
        'password'	=>	'c1feyi7lee',
        'ssl'		=>	'tls',
        'port'		=> 587
    );
//					repitamail12



    // SMTPメールサーバの設定
//	$smtp = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $config);
    $smtp = new Zend_Mail_Transport_Smtp('gcc-kanagawa.jp', $config);
    $transport = Zend_Mail::setDefaultTransport($smtp);

    // 送信するメール全てで使う From 及び Reply-To のアドレス及び名前を設定します
    Zend_Mail::setDefaultFrom($fromEmail, $fromName);
    Zend_Mail::setDefaultReplyTo($fromEmail, $fromName);

    // 初期化
    $toCount = 0;

    // メッセージをループ処理します
    foreach ($toAll as $to) {
        $mail = new Zend_Mail($mailCharset);
        $mail->addTo($to);
        $mail->setSubject($subject);
        $mail->setBodyText($body);
        $mail->send($transport);
        $toCount ++;
    }

    // 既定値をリセットします
    Zend_Mail::clearDefaultFrom();
    Zend_Mail::clearDefaultReplyTo();

    return $toCount;
}
/**
 * メール送信処理を行う
 *
 * @param		array			$from		送信者データ
 * @param		array			$toAll		送信宛メールアドレスデータ
 * @param		string		$subject	メール件名
 * @param		string		$body		メール内容
 * @param		string		$type		メール内容
 * @return		int				$toCount	送信先メール数
 */
function TonomachiMailSending($from,$toAll,$subject,$body,$type)
{

    // 文字コードを設定する
    $mailCharset = 'ISO-2022-JP';
    $crrCharset = 'UTF-8';

    // メールの内容を設定する
    $fromName = (isset($from['name'])) ? $from['name'] : '';
    $fromEmail = (isset($from['email'])) ? $from['email'] : 'school@tonomachi-rc.jp';

    // 文字コードを「ISO-2022-JP」に変更する
    $fromName = mb_convert_encoding($fromName, $mailCharset, $crrCharset);
    $subject = mb_convert_encoding($subject, $mailCharset, $crrCharset);
    $body = mb_convert_encoding($body, $mailCharset, $crrCharset);


//	// SMTP認証設定
//	$config = array(	 'auth'		=>	'login',
//						 'username'	=>	'atreate124@gmail.com',
//						 'password'	=>	'tsuji1710',
//						 'ssl'		=>	'ssl'
//					);
    // SMTP認証設定
    $config = array(	 'auth'		=>	'login',
        'username'	=>	'school@tonomachi-rc.jp',
        'password'	=>	'vacs55vacs',
        'ssl'		=>	'tls',
        'port'		=> 587
    );
//					repitamail12



    // SMTPメールサーバの設定
//	$smtp = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $config);
    $smtp = new Zend_Mail_Transport_Smtp('tonomachi-rc.sakura.ne.jp', $config);
    $transport = Zend_Mail::setDefaultTransport($smtp);

    // 送信するメール全てで使う From 及び Reply-To のアドレス及び名前を設定します
    Zend_Mail::setDefaultFrom($fromEmail, $fromName);
    Zend_Mail::setDefaultReplyTo($fromEmail, $fromName);

    // 初期化
    $toCount = 0;

    $file_data = file_get_contents('/mnt/data/html/public/tonomachi_lunce.xlsx');

    // メッセージをループ処理します
    foreach ($toAll as $to) {
        $mail = new Zend_Mail($mailCharset);
        $mail->addTo($to);
        $mail->setSubject($subject);
        $mail->setBodyText($body);

        // 添付データ作成
        if ($type == 'normal') {
            $at = $mail->createAttachment($file_data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $at->filename = '殿町サマースクールお弁当お申込書.xlsx';
        }

        $mail->send($transport);
        $toCount ++;
    }

    // 既定値をリセットします
    Zend_Mail::clearDefaultFrom();
    Zend_Mail::clearDefaultReplyTo();

    return $toCount;
}



/**
 * 時間設定（SQL向け）
 * 
 * @param		array		$htmlSpe			入力データ
 * @return		array		$htmlSpe			対策後データ
 */
function timeRest() {
	
	$date = new Zend_Date();
	
	$mainTime = $date->toString("yyyy-MM-dd HH:mm:ss");
//	$mainTime = $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second;
	
	return $mainTime;
}