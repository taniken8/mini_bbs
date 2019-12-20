<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
  $_SESSION['time'] = time();
  $members = $db->prepare('SELECT * FROM members WHERE id=?');
  $members->execute(array($_SESSION['id']));
  $member = $members->fetch();
} else {
  header('Location: login.php');
  exit();
}

if (!empty($_POST)) {
  if ($_POST['message'] !== '') {
    $statement = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_message_id=?, created=NOW()');
    $statement->execute(array(
      $_POST['message'],
      $member['id'],
      $_POST['reply_post_id']
    ));
    header('Location: index.php');
    exit();
  }
}

//いいねマークが押された場合
if (isset($_REQUEST['favorites'])) {
  $check = $db->prepare('SELECT COUNT(*) AS cnt FROM favorites WHERE member_id=? AND post_id=?');
  $check->execute(array(
    $member['id'],
    $_REQUEST['favorites']
  ));
  $result = $check->fetch();
  if ($result['cnt'] > 0) {
    $del = $db->prepare('DELETE FROM favorites WHERE member_id=? AND post_id=?');
    $del->execute(array(
      $member['id'],
      $_REQUEST['favorites']
    ));
  } else {
    $favorite = $db->prepare('INSERT INTO favorites SET member_id=?, post_id=?, created=NOW()');
    $favorite->execute(array(
    $member['id'],
    $_REQUEST['favorites']
  ));
}
header("Location: index.php?page={$page}");
exit();

}

//返信ボタンが押された場合
if (isset($_REQUEST['res'])) {
  $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=?');
  $response->execute(array($_REQUEST['res']));
  $table = $response->fetch();
  $message = '@' . $table['name'] . '  ' . $table['message'];
}


//ページネーション
$page = $_REQUEST['page'];
if ($page == '') {
  $page = 1;
}
$page = max($page, 1);

$counts = $db->query('SELECT COUNT(*) as cnt FROM posts');
$count = $counts->fetch();
$maxPage = ceil($count['cnt']/5);
$page = min($page, $maxPage);

$start = ($page-1)*5;

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">

	<link rel="stylesheet" href="style.css" />
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>ひとこと掲示板</h1>
  </div>
  <div id="content">
  	<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
    <form action="" method="post">
      <dl>
        <dt><?php print(htmlspecialchars($member['name'], ENT_QUOTES)); ?>さん、メッセージをどうぞ</dt>
        <dd>
          <textarea name="message" cols="50" rows="5"><?php print(htmlspecialchars($message, ENT_QUOTES)); ?></textarea>
          <input type="hidden" name="reply_post_id" value="<?php print(htmlspecialchars($_REQUEST['res'])); ?>" />
        </dd>
      </dl>
      <div>
        <p>
          <input type="submit" value="投稿する" />
        </p>
      </div>
    </form>

    <?php foreach($posts as $post): ?>
    <div class="msg">
    <img class="member_picture" src="member_picture/<?php print(htmlspecialchars($post['picture'], ENT_QUOTES)); ?>" width="48" height="48" alt="" />
    <p><?php print(htmlspecialchars($post['message'], ENT_QUOTES)); ?><span class="name">（<?php print(htmlspecialchars($post['name'], ENT_QUOTES)); ?>）</span>[<a href="index.php?res=<?php print(htmlspecialchars($post['id'], ENT_QUOTES)); ?>">Re</a>]</p>
    <p class="day"><a href="view.php?id=<?php print(htmlspecialchars($post['id'], ENT_QUOTES)); ?>"><?php print(htmlspecialchars($post['created'], ENT_QUOTES)); ?></a>
  <?php if ($post['reply_message_id'] > 0): ?>
    <a href="view.php?id=<?php print(htmlspecialchars($post['reply_message_id'], ENT_QUOTES)); ?>">
    返信元のメッセージ</a>
  <?php endif; ?>
  <?php if ($_SESSION['id'] === $post['member_id']): ?>
[<a href="delete.php?id=<?php print(htmlspecialchars($post['id'], ENT_QUOTES)); ?>"
style="color: #F33;">削除</a>]
<?php endif; ?>

<?php
//ログインユーザーが各記事に対していいねしているかをチェック
  $favorites_check = $db->prepare('SELECT COUNT(*) AS cnt FROM favorites WHERE member_id=? AND post_id=?');
  $favorites_check->execute(array(
  $member['id'],
  $post['id']
  ));
  $favorite_check = $favorites_check->fetch();
?>
<?php if ($favorite_check['cnt'] > 0): ?>
<!--いいね数が０より大きければ-->
  <a href="index.php?favorites=<?php print(htmlspecialchars($post['id'], ENT_QUOTES)); ?>&page=<?php print(htmlspecialchars($page, ENT_QUOTES)); ?>"><span class="fa fa-heart like_btn"></span></a>
<?php else: ?>
  <a href="index.php?favorites=<?php print(htmlspecialchars($post['id'], ENT_QUOTES)); ?>&page=<?php print(htmlspecialchars($page, ENT_QUOTES)); ?>"><span class="fa fa-heart like_btn_unlike"></span></a>
<?php endif; ?>


<?php //いいねの数の集計
$favorite_counts = $db->prepare('SELECT COUNT(*) as cnt FROM favorites WHERE post_id=?');
$favorite_counts->execute(array($post['id']));
$favorite_count = $favorite_counts->fetch();
?>

<?php print(htmlspecialchars($favorite_count['cnt'], ENT_QUOTES)); ?>
    </div>
<?php endforeach; ?>


<ul class="paging">
<?php if ($page >= 2): ?>
<li><a href="index.php?page=<?php print($page-1); ?>">前のページへ</a></li>
<?php endif; ?>
<?php if ($page < $maxPage): ?>
<li><a href="index.php?page=<?php print($page+1); ?>">次のページへ</a></li>
<?php endif; ?>
</ul>
  </div>
</div>
</body>
</html>
