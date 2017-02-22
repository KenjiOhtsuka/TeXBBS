<?php
mb_internal_encoding("UTF-8");

$error_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (md5($_SESSION['taskId']) == $_POST['taskId']) {
    $title = $_POST['title'];
    if (empty($title) || (mb_strlen($title) > 30)) {
      $error_message .= "タイトルを30文字以内で入力してください。<br />";
    } else {
      $title = htmlspecialchars($title);
      $title = mysqli_real_escape_string($title);
    }
 
    $writer = $_POST['writer'];
    unset($_SESSION['taskId']);
    if (empty($writer) || (mb_strlen($writer) > 20)) {
      $error_message .= "名前を20文字以内で入力してください。<br />";
    } else {
      $writer = htmlspecialchars($writer);
      $writer = mysqli_real_escape_string($writer);
    }
    $message = $_POST['message'];
    if (empty($message) || (mb_strlen($message) > 20000)) {
      $error_message .= "メッセージを20000文字以内で入力してください。<br />";
    } else {
      //$message = htmlspecialchars($message);
      $message = str_replace('<', '&lt;', $message);
      $message = str_replace('>', '&gt;', $message);
      $message = mysqli_real_escape_string($message);
    }
    //$message = nl2br($message);
    $twitter_id = htmlspecialchars($_POST['twitterID']);
    $twitter_id = mysqli_real_escape_string($twitter_id);
    $mixi_id = $_POST['mixiID'];
    if (!empty($mixi_id)) {
      if (preg_match('/^[1-9][0-9]{0,9}$/', $mixi_id) == 1) {
        $mixi_id = htmlspecialchars($mixi_id);
      } else {
        $error_message .= "mixiIDは10桁以内の整数値で入力してください。<br />";
      }
    }
    $facebook_id = htmlspecialchars($_POST['facebookID']);
    $facebook_id = mysqli_real_escape_string($facebook_id);
    //$url = htmlspecialchars($_POST['url']);
    $color = htmlspecialchars($_POST['color']);
    $password = $_POST['password'];
    if (mb_strlen($password, "utf-8") <= 20 &&
        preg_match('/[a-zA-Z0-9]*/', $password)) {
      $password = htmlspecialchars($password);
      $password = mysqli_real_escape_string($password);
      $password = sha1($password.ConstText::PasswordSalt);
    } else {
      $error_message .= 'パスワードは半角英数20文字で入力してください。<br />';
    }

    $post_pattern = PostType::Topic;
    if (isset($_POST['topic_id']) && ctype_digit($_POST['topic_id']) && !empty($_POST['topic_id'])) {
      $topic_id = $_POST['topic_id'];
      if (isset($_POST['post_id']) && ctype_digit($_POST['post_id'])) {
        $post_pattern = PostType::Edit;
        $post_id = $_POST['post_id'];
      } else {
        $post_pattern = PostType::Reply;
      }
    }
    
    if ($error_message == '') {
      switch ($post_pattern) {
        case PostType::Edit:
          $result = mysqli_query($dbCon, "SELECT password FROM BBSposts WHERE topic_id = ".$topic_id." AND id = ".$post_id);
          if ($result && ($temp = mysqli_fetch_array($result))) {
            if (!($temp['password'] == $password)) {
              $error_message .=  "パスワードが違います。<br />";
              break;
            }
          } else {
            //echo mysqli_error();
            $error_message .= "その投稿は存在しません。";
            break;
          } 
          if ($error_message == '') {
            $sqlPost = "UPDATE BBSposts SET writer = '{$writer}', title = '{$title}', "
                      ."title = '{$title}', message = '{$message}', "
                      ."twitter_id = '{$twitter_id}', mixi_id = '{$mixi_id}', facebook_id = '{$facebook_id}', "
                      ."color = '{$color}', modified = NOW() + INTERVAL 14 HOUR "
                      ."WHERE topic_id = {$topic_id} AND id = {$post_id} AND password = '{$password}'";
            if (!mysqli_query($sqlPost)) {
              echo mysqli_error();
              $error_mesage .= "更新に失敗しました。<br />";
              break;
            }
            if ($error_message == '') {
              $sqlPost = "UPDATE BBStopics SET updated = now() + INTERVAL 14 HOUR WHERE id = {$topic_id}";
              if (!mysqli_query($sqlPost)) {
                echo mysqli_error();
                $error_message .= "トピック時刻更新に失敗しました。";
                break;
              }
            }
          }
          break;

        case PostType::Reply:
          $result = mysqli_query("SELECT max(id) max_id FROM BBSposts WHERE topic_id = ".$topic_id);
          if (!$result) {
            echo mysqli_error();
            echo "そのトピックは存在しません。";
            break;
          }
          if ($error_message == '') {
            $post_id = 0;
            if ($temp = mysqli_fetch_array($result)) {
              $post_id = $temp['max_id'] + 1;
            } else {
              $error_message .= "そのトピックは存在しません。";
              break;
            }
            
            $sqlPost = "INSERT INTO BBSposts(id, topic_id, writer, title, message, "
                      ."twitter_id, mixi_id, facebook_id, url, color, password, "
                      ."created) ";
            $sqlPost .= "VALUES({$post_id}, {$topic_id}, '{$writer}', '{$title}', '{$message}', "
                      ."'{$twitter_id}', '{$mixi_id}', '{$facebook_id}', '{$url}', '{$color}', "
                      ."'{$password}', now() + INTERVAL 14 HOUR)";
            if (!mysqli_query($sqlPost)) {
              echo mysqli_error();
              $error_message .= "投稿に失敗しました。<br />";
              break;
            }
            $sqlPost = "UPDATE BBStopics SET updated = now() + INTERVAL 14 HOUR WHERE id = {$topic_id}";
            if (!mysqli_query($sqlPost)) {
              echo mysqli_error();
              $error_message .= "トピック時刻更新に失敗しました。<br />";
              break;
            }
          }
          break;

        case PostType::Topic:
          if (!mysqli_query($myCon, "INSERT INTO BBStopics(updated) VALUES(now() + INTERVAL 14 HOUR)")) {
            //die(mysqli_error());
            $error_message .= "データベースエラーが発生しました。";
          }
          if ($error_message == '') {
            $result = mysqli_query($myCon, "SELECT max(id) max_id FROM BBStopics");
            $topic_id = 0;
            if (!$result) {
              //echo mysqli_error();
              $error_message .= "データベースエラーが発生しました。";
              break;
            } else {
              global $topic_id, $result;
              if ($temp = mysqli_fetch_array($result)) {
                global $topic_id;
                $topic_id = $temp['max_id'];
              }
            }
            $sqlPost = "INSERT INTO BBSposts(id, topic_id, writer, title, message, "
                      ."twitter_id, mixi_id, facebook_id, url, color, password, "
                      ."created) ";
            $sqlPost .= "VALUES(0, {$topic_id}, '{$writer}', '{$title}', '{$message}', "
                      ."'{$twitter_id}', '{$mixi_id}', '{$facebook_id}', '{$url}', '{$color}', "
                      ."'{$password}', now() + INTERVAL 14 HOUR)";
            if (!mysqli_query($myCon, $sqlPost)) {
              echo mysqli_error();
              mysqli_query($myCon, "DELETE FROM BBStopics WHERE id = {$topic_id}");
              echo mysqli_error();
              break;
            }
          }
          break;
      }
    }
  } else {
    $error_message = "セッションが切れたため、投稿できませんでした。<br />";
  }
} 
if ($error_message != '') {
  echo $error_message;
}

switch ($input_type) {
  case InputType::None:
    echo outputForm('index.php', '', $writer, '', $color,
                   $mixi_id, 0, $facebook_id);
    break;

  case InputType::Edit:
    $topic_id = $_GET[GetParam::TopicId];
    if (isset($_GET[GetParam::PostId])) {
      $post_id = $_GET[GetParam::PostId];
    } else {
      $post_id = 0;
    }
    
    $sqlForm = "SELECT writer, title, message, twitter_id, CASE mixi_id WHEN 0 THEN '' ELSE mixi_id END mixi_id, facebook_id, color FROM BBSposts "
              ."WHERE topic_id = {$topic_id} AND id = {$post_id}";
    $rowsForm = mysqli_query($dbCon, $sqlForm);
    if (!$rowsForm) {
      echo mysqli_error();
      break;
    }

    if ($rowForm = mysqli_fetch_array($rowsForm)) {
      echo outputForm($_SESSION['PHP_SELF']."?".GetParam::TopicId."=".$topic_id."&".GetParam::PostId."=".$post_id, 
                     $rowForm['title'], $rowForm['writer'], $rowForm['message'], $rowForm['color'],
                     $rowForm['mixi_id'], $rowForm['twitter_id'], $rowForm['facebook_id'], $topic_id, $post_id);
    }
    break;

  case InputType::CommentReply:
    $topic_id = $_GET[GetParam::TopicId];
    $post_id = $_GET[GetParam::PostId];
    $preMessage = "\n\n---- No. {$topic_id} Comment {$post_id} ----\n";
      
    $sqlForm = "SELECT title, message FROM BBSposts "
              ."WHERE topic_id = {$topic_id} AND id = {$post_id}";
    $rowsForm = mysqli_query($dbCon, $sqlForm);
    if (!$rowsForm) {
      echo mysqli_error();
      break;
    }
    if ($rowForm = mysqli_fetch_array($rowsForm)) {
      $title = "Re: ".$rowForm['title'];
      $title = mb_substr($title, 0, 30, "utf-8");
      echo outputForm($_SESSION['PHP_SELF']."?".GetParam::TopicId."=".$_GET['topic_id'], $title, $writer, $preMessage.$rowForm['message'], $color,
                     $mixi_id, $twitter_id, $facebook_id, $_GET['topic_id']);
    }
    break;
    
  case InputType::TopicReply:
    $topic_id = $_GET[GetParam::TopicId];
    $preMessage = "\n\n------------ No. {$topic_id} ------------\n";
    $post_id = 0;
      
    $sqlForm = "SELECT title, message FROM BBSposts "
              ."WHERE topic_id = {$topic_id} AND id = {$post_id}";
    $rowsForm = mysqli_query($dbCon, $sqlForm);
    if (!$rowsForm) {
      echo mysqli_error();
      break;
    }
    if ($rowForm = mysqli_fetch_array($rowsForm)) {
      $title = "Re: ".$rowForm['title'];
      $title = mb_substr($title, 0, 30, "utf-8");
      echo outputForm($_SESSION['PHP_SELF']."?".GetParam::TopicId."=".$_GET['topic_id'], $title, $writer, $preMessage.$rowForm['message'], $color,
                     $mixi_id, $twitter_id, $facebook_id, $_GET['topic_id']);
    }
    break;
}

?>
