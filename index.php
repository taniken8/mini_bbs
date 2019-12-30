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
}

//リツイートが押された場合
if (isset($_REQUEST['retweet'])) {
  //ログインしているユーザーが各記事をいいねしているかチェック
    $retweet_check = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE member_id=? AND retweet_id=?');
    $retweet_check->execute(array(
      $member['id'],
      $_REQUEST['retweet']
    ));
    $retweet_result = $retweet_check->fetch();
  //リツイート元のメッセージを取得
    $retweet = $db->prepare('SELECT m.name, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $retweet->execute(array($_REQUEST['retweet']));
    $retweet_table = $retweet->fetch();
  //リツイートであることがわかるように表示
    $retweet_message = '@' . $member['name'] . 'さんがリツイートしました' . '  ' . $retweet_table['message'] . '(' . $retweet_table['name'] . ')';
  
  //リツイートの削除
    if ($retweet_result['cnt'] > 0) {
      $del = $db->prepare('DELETE FROM posts WHERE member_id=? AND retweet_id=?');
      $del->execute(array(
        $member['id'],
        $_REQUEST['retweet']
      ));
      header('Location: index.php');
      exit();
    } else {
  //リツイート
      $insert_retweet = $db->prepare('INSERT INTO posts SET message=?, member_id=?, retweet_id=?, created=NOW()');
      $insert_retweet->execute(array(
      $retweet_message,
      $member['id'],
      $_REQUEST['retweet']
      ));
      header('Location: index.php');
      exit();
  }
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

//htmlspecialcharsのショートカット
function h($value) {
  return htmlspecialchars($value, ENT_QUOTES);
}

//本文内のURLにリンクを設定
function makeLink($value) {
  return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)",'<a href="\1\2">\1\2</a>' , $value);
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>
  <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">

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
        <dt><?php print h($member['name']); ?>さん、メッセージをどうぞ</dt>
        <dd>
          <textarea name="message" cols="50" rows="5"><?php print h($message); ?></textarea>
          <input type="hidden" name="reply_post_id" value="<?php print h($_REQUEST['res']); ?>" />
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
    <img class="member_picture" src="member_picture/<?php print h($post['picture']); ?>" width="48" height="48" alt="" />
    <p><?php print makeLink(h($post['message'])); ?>
    <?php if ($retweet_result === ''): ?>
    <span class="name">（<?php print h($post['name']); ?>）
    <?php endif; ?>
  </span>[<a href="index.php?res=<?php print h($post['id']); ?>">Re</a>]</p>
    <p class="day"><a href="view.php?id=<?php print h($post['id']); ?>"><?php print h($post['created']); ?></a>
  <?php if ($post['reply_message_id'] > 0): ?>
    <a href="view.php?id=<?php print h($post['reply_message_id']); ?>">
    返信元のメッセージ</a>
  <?php endif; ?>
  <?php if ($_SESSION['id'] === $post['member_id']): ?>
[<a href="delete.php?id=<?php print h($post['id']); ?>"
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
  <a href="index.php?favorites=<?php print h($post['id']); ?>"><span class="fa fa-heart like_btn"></span></a>
<?php else: ?>
  <a href="index.php?favorites=<?php print h($post['id']); ?>"><span class="fa fa-heart like_btn_unlike"></span></a>
<?php endif; ?>


<?php //いいねの数の集計
$favorite_counts = $db->prepare('SELECT COUNT(*) as cnt FROM favorites WHERE post_id=?');
$favorite_counts->execute(array($post['id']));
$favorite_count = $favorite_counts->fetch();
?>

<?php print h($favorite_count['cnt']); ?>

<?php
//ログインユーザーが各記事に対してリツイートしているかをチェック
  $retweet_check = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE member_id=? AND retweet_id=?');
  $retweet_check->execute(array(
  $member['id'],
  $post['id']
  ));
  $retweet = $retweet_check->fetch();
?>
<?php if ($retweet['cnt'] > 0): ?>
<!--リツイート数が０より大きければ-->
  <a href="index.php?retweet=<?php print h($post['id']); ?>"><i class="fas fa-retweet retweet"></i></a>
<?php else: ?>
  <a href="index.php?retweet=<?php print h($post['id']); ?>"><i class="fas fa-retweet unretweet"></i></a>
<?php endif; ?>

<?php //リツイート数の集計
$retweet_counts = $db->prepare('SELECT COUNT(*) as cnt FROM posts WHERE retweet_id=?');
$retweet_counts->execute(array($post['id']));
$retweet_count = $retweet_counts->fetch();
?>

<!--リツイート数の表示-->
<?php print h($retweet_count['cnt']); ?>

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