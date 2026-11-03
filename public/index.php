<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Media is served through the app, not from a publicly accessible uploads directory.
if (isset($_GET['media'])) {
    try {
        $beat=one_beat((int)$_GET['media']);
        $kind=($_GET['kind']??'audio')==='cover'?'cover':'audio';
        $name=$beat[$kind.'_path']??null; $mime=$beat[$kind.'_mime']??null;
        if (!$name || !$mime || !safe_name($name)) {http_response_code(404);exit('Not found.');}
        $file=dirname(__DIR__).'/storage/'.$name;
        if (!is_file($file)) {http_response_code(404);exit('Media not found.');}
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Length: '.filesize($file));
        readfile($file); exit;
    } catch (Throwable $e) { http_response_code(404); exit('Media unavailable.'); }
}

if (isset($_GET['action'])) {
    require dirname(__DIR__).'/app/actions.php'; handle_action();
}
$page=(string)($_GET['page']??'home');
$allowed=['home','discover','beat','login','signup','studio','edit','library','about'];
if (!in_array($page,$allowed,true)) {http_response_code(404);$page='home';}
try {
    $me=current_user();
    $beats=[]; $beat=null;
    if ($page==='discover') {
        $stmt=database()->query('SELECT b.*,u.display_name creator FROM beats b JOIN users u ON b.owner_id=u.id ORDER BY b.created_at DESC LIMIT 60');
        $beats=$stmt->fetchAll();
    }
    if ($page==='beat'||$page==='edit') $beat=one_beat((int)($_GET['id']??0));
    if ($page==='studio' && $me) {
        $stmt=database()->prepare('SELECT b.*,u.display_name creator FROM beats b JOIN users u ON b.owner_id=u.id WHERE b.owner_id=? ORDER BY b.created_at DESC');
        $stmt->execute([$me['id']]);$beats=$stmt->fetchAll();
    }
    if ($page==='library' && $me) {
        $stmt=database()->prepare('SELECT b.*,u.display_name creator FROM purchases p JOIN beats b ON b.id=p.beat_id JOIN users u ON u.id=b.owner_id WHERE p.buyer_id=? ORDER BY p.purchased_at DESC');
        $stmt->execute([$me['id']]);$beats=$stmt->fetchAll();
    }
} catch (Throwable $e) {
    error_log('TuneTastic DB: '.$e->getMessage());
    http_response_code(503);
    ?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>TuneTastic | Setup needed</title><link rel="stylesheet" href="assets/style.css"><div class="container" style="padding-top:80px"><div class="panel"><h1>Database setup needed</h1><p>Start your local MySQL server, import <code>database/schema.sql</code>, and set up <code>config.local.php</code> from the example. See README.md for instructions.</p><a class="button" href="?page=home">Try again</a></div></div></html><?php exit;
}
$notice=$_SESSION['notice']??null;unset($_SESSION['notice']);
function badge_card(array $b): void { ?>
  <article class="beat-card">
    <a class="cover" href="?page=beat&id=<?= (int)$b['id'] ?>">
      <?php if ($b['cover_path']): ?><img src="?media=<?= (int)$b['id'] ?>&kind=cover" alt="Cover art for <?= escape_html($b['title']) ?>"><?php else: ?><span class="cover-icon">♫</span><?php endif; ?>
    </a>
    <div class="card-body"><span class="eyebrow"><?= escape_html($b['genre']) ?></span>
      <h3><a href="?page=beat&id=<?= (int)$b['id'] ?>"><?= escape_html($b['title']) ?></a></h3>
      <div class="card-meta"><span>by <?= escape_html($b['creator']) ?></span><strong>$<?= escape_html((string)$b['price']) ?></strong></div>
      <audio controls preload="none" src="?media=<?= (int)$b['id'] ?>&kind=audio" aria-label="Preview <?= escape_html($b['title']) ?>"></audio>
    </div>
  </article>
<?php }
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#121126"><title>TuneTastic | Music marketplace prototype</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<header class="site-header"><nav class="nav container"><a class="brand" href="?page=home"><span class="brand-symbol">♫</span> Tune<span>Tastic</span></a><div class="nav-links"><a href="?page=discover">Explore beats</a><a href="?page=about">About</a><?php if ($me): ?><a href="?page=studio">My studio</a><a href="?page=library">My library</a><form action="?action=logout" method="post" class="inline-form"><?= csrf_field() ?><button class="text-button" type="submit">Sign out</button></form><?php else: ?><a href="?page=login">Sign in</a><a class="button button-small" href="?page=signup">Join free</a><?php endif; ?></div></nav></header>
<main class="container">
<?php if ($notice): ?><div class="notice <?= $notice[1]==='error'?'notice-error':'notice-success' ?>" role="status"><?= escape_html($notice[0]) ?></div><?php endif; ?>
<?php if ($page==='home'): ?>
  <section class="hero"><div class="hero-copy"><span class="pill">CREATORS × LISTENERS</span><h1>Where new beats <em>find their people.</em></h1><p>Explore sounds, share what you create, and build your collection in a local music-marketplace demo.</p><div class="hero-actions"><a class="button" href="?page=discover">Explore beats <span aria-hidden="true">→</span></a><a class="button button-ghost" href="?page=<?= $me?'studio':'signup' ?>">Start creating</a></div><p class="micro">Academic prototype · No real payments or licensed music included.</p></div><div class="hero-art" aria-hidden="true"><div class="vinyl"><div class="vinyl-center">T</div></div><div class="soundline">♪ ♫ ♪</div></div></section>
  <section class="section"><div class="section-head"><div><span class="eyebrow">THE EXPERIENCE</span><h2>Make it. Share it. Discover it.</h2></div></div><div class="feature-grid"><div class="feature"><span>01</span><h3>Discover</h3><p>Browse uploaded tracks and play audio previews directly in the browser.</p></div><div class="feature"><span>02</span><h3>Create</h3><p>Share original beats with a title, genre, cover art, and sample price.</p></div><div class="feature"><span>03</span><h3>Collect</h3><p>Save beats in your demo library. No billing or real checkout.</p></div></div></section>
<?php elseif ($page==='discover'): ?>
  <section class="page-heading"><span class="eyebrow">FIND YOUR NEXT SOUND</span><h1>Explore beats</h1><p>Browse independent creators' tracks and previews.</p></section>
  <?php if ($beats): ?><div class="cards"><?php foreach($beats as $b) badge_card($b); ?></div><?php else: ?><div class="empty"><span>♫</span><h2>Be the first to drop a beat.</h2><p>No tracks have been uploaded yet. Sign up and share your own music to get started.</p><a class="button" href="?page=<?= $me?'studio':'signup' ?>">Go to studio</a></div><?php endif; ?>
<?php elseif ($page==='beat'): ?>
  <?php if (!$beat): ?><div class="empty"><h2>Beat not found</h2><a href="?page=discover">Back to explore</a></div><?php else: ?><section class="detail-layout"><div class="cover detail-cover"><?php if($beat['cover_path']): ?><img src="?media=<?= (int)$beat['id'] ?>&kind=cover" alt="Cover art"><?php else: ?><span class="cover-icon">♫</span><?php endif; ?></div><div><span class="eyebrow"><?= escape_html($beat['genre']) ?> · BY <?= escape_html($beat['creator']) ?></span><h1><?= escape_html($beat['title']) ?></h1><p><?= nl2br(escape_html($beat['description'])) ?></p><audio controls src="?media=<?= (int)$beat['id'] ?>" aria-label="Listen to <?= escape_html($beat['title']) ?>"></audio><div class="detail-bottom"><strong class="price">$<?= escape_html((string)$beat['price']) ?></strong><?php if ($me && (int)$beat['owner_id']===(int)$me['id']): ?><a class="button" href="?page=edit&id=<?= (int)$beat['id'] ?>">Edit this beat</a><?php else: ?><form action="?action=purchase" method="post"><?= csrf_field() ?><input type="hidden" name="beat_id" value="<?= (int)$beat['id'] ?>"><button class="button" type="submit">Add to demo library</button></form><?php endif; ?></div><p class="micro">Prices are illustrative. No payment is collected.</p></div></section><?php endif; ?>
<?php elseif ($page==='login'||$page==='signup'): ?>
  <section class="form-section"><div class="form-card"><span class="eyebrow">YOUR CREATIVE SPACE</span><h1><?= $page==='signup'?'Create an account':'Welcome back' ?></h1><p>Sign <?= $page==='signup'?'up':'in' ?> to upload tracks and save favorites.</p><form method="post" action="?action=<?= $page==='signup'?'signup':'login' ?>"><?= csrf_field() ?><?php if ($page==='signup'): ?><label>Display name<input name="display_name" type="text" minlength="2" maxlength="80" required autocomplete="name"></label><?php endif; ?><label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" required></label><label>Password<input type="password" name="password" minlength="<?= $page==='signup'?10:1 ?>" autocomplete="<?= $page==='signup'?'new-password':'current-password' ?>" required></label><button class="button full" type="submit"><?= $page==='signup'?'Create account':'Sign in' ?></button></form><p class="form-foot"><?= $page==='signup'?'Already registered?':'New here?' ?> <a href="?page=<?= $page==='signup'?'login':'signup' ?>"><?= $page==='signup'?'Sign in':'Create an account' ?></a></p></div></section>
<?php elseif ($page==='studio'): ?>
  <?php if (!$me): ?><div class="empty"><h2>Sign in to use your studio.</h2><a class="button" href="?page=login">Sign in</a></div><?php else: ?><section class="page-heading"><span class="eyebrow">YOUR CREATOR DASHBOARD</span><h1>Welcome, <?= escape_html($me['display_name']) ?></h1><p>Upload music you own or have permission to share.</p></section><section class="studio-grid"><div class="panel"><h2>Publish a beat</h2><form action="?action=upload" method="post" enctype="multipart/form-data"><?= csrf_field() ?><label>Track title<input name="title" maxlength="120" required placeholder="Midnight Drive"></label><label>Genre<input name="genre" maxlength="60" placeholder="Lo-fi, R&B, ambient..."></label><label>Description<textarea name="description" rows="3" maxlength="500" placeholder="What inspired this sound?"></textarea></label><label>Illustrative price (USD)<input type="number" name="price" value="0" min="0" max="999999.99" step="0.01" required></label><label>Audio file (MP3, WAV, OGG · max 12 MB)<input type="file" name="audio" accept="audio/mpeg,audio/wav,audio/ogg,.mp3,.wav,.ogg" required></label><label>Cover (optional JPG, PNG, WebP · max 3 MB)<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label><button class="button full" type="submit">Publish beat</button></form></div><div class="panel"><h2>Your tracks</h2><?php if(!$beats): ?><p class="muted">Nothing here yet. Upload a beat to get started.</p><?php else: ?><div class="track-list"><?php foreach($beats as $b): ?><div class="track-row"><div><strong><?= escape_html($b['title']) ?></strong><span><?= escape_html($b['genre']) ?> · $<?= escape_html((string)$b['price']) ?></span></div><div class="track-actions"><a href="?page=edit&id=<?= (int)$b['id'] ?>">Edit</a><form method="post" action="?action=delete" onsubmit="return confirm('Delete this beat?')"><?= csrf_field() ?><input type="hidden" name="beat_id" value="<?= (int)$b['id'] ?>"><button class="text-button danger" type="submit">Delete</button></form></div></div><?php endforeach; ?></div><?php endif; ?></div></section><?php endif; ?>
<?php elseif ($page==='edit'): ?>
  <?php if (!$me || !$beat || (int)$beat['owner_id']!==(int)$me['id']): ?><div class="empty"><h2>Beat unavailable.</h2><a href="?page=studio">Back to studio</a></div><?php else: ?><section class="form-section"><div class="form-card"><span class="eyebrow">EDIT TRACK</span><h1><?= escape_html($beat['title']) ?></h1><form action="?action=update" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="beat_id" value="<?= (int)$beat['id'] ?>"><label>Title<input name="title" required maxlength="120" value="<?= escape_html($beat['title']) ?>"></label><label>Genre<input name="genre" maxlength="60" value="<?= escape_html($beat['genre']) ?>"></label><label>Description<textarea name="description" maxlength="500" rows="3"><?= escape_html($beat['description']) ?></textarea></label><label>Illustrative price (USD)<input name="price" type="number" min="0" max="999999.99" step="0.01" required value="<?= escape_html((string)$beat['price']) ?>"></label><label>Replace audio (optional)<input type="file" name="audio" accept=".mp3,.wav,.ogg"></label><label>Replace cover (optional)<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label><button class="button full" type="submit">Save changes</button></form><p><a href="?page=studio">← Back to studio</a></p></div></section><?php endif; ?>
<?php elseif ($page==='library'): ?>
  <?php if (!$me): ?><div class="empty"><h2>Sign in to view your library.</h2><a class="button" href="?page=login">Sign in</a></div><?php else: ?><section class="page-heading"><span class="eyebrow">DEMO COLLECTION</span><h1>My library</h1><p>Tracks you've saved through the sample purchase workflow. No payments are involved.</p></section><?php if($beats): ?><div class="cards"><?php foreach($beats as $b) badge_card($b); ?></div><?php else: ?><div class="empty"><h2>Your library is empty</h2><p>Explore creators and add a track to your collection.</p><a class="button" href="?page=discover">Find tracks</a></div><?php endif; ?><?php endif; ?>
<?php elseif ($page==='about'): ?><section class="page-heading"><span class="eyebrow">ABOUT THE PROJECT</span><h1>Made for creators, built for learning.</h1><p>TuneTastic is a student-developed music-marketplace concept, rebuilt as a presentable software-engineering portfolio demonstration.</p></section><div class="feature-grid"><div class="feature"><h3>Full-stack foundations</h3><p>Server-side PHP, relational data modeling with MySQL, sessions, and reusable interfaces.</p></div><div class="feature"><h3>Creator tools</h3><p>Upload, edit, and remove original tracks with audio and optional cover art.</p></div><div class="feature"><h3>Safety by design</h3><p>Parameterized queries, hashed passwords, verified ownership, CSRF tokens, and basic upload validation.</p></div></div><div class="panel about-note"><h2>Demo only</h2><p>This project is not a production store, licensed music service, or payment processor. No real transactions occur. Uploaded media must be yours or properly licensed.</p></div>
<?php endif; ?>
</main><footer class="footer container"><span class="brand">♫ Tune<span>Tastic</span></span><p>Academic music-platform prototype · PHP + MySQL · No real payments.</p><a href="?page=about">About this project</a></footer>
</body></html>
