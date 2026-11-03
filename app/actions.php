<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function handle_action(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
    require_csrf();
    $action = (string) ($_GET['action'] ?? '');
    $newFiles = [];
    try {
        if ($action === 'signup') {
            $name = text_field('display_name', 80);
            $email = strtolower(text_field('email', 190));
            $password = (string) ($_POST['password'] ?? '');
            if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
                throw new RuntimeException('Use a name, valid email, and password of at least 10 characters.');
            }
            $stmt = database()->prepare('INSERT INTO users (display_name,email,password_hash) VALUES (?,?,?)');
            try { $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); }
            catch (PDOException $e) {
                if ($e->getCode() === '23000') throw new RuntimeException('An account already uses that email.');
                throw $e;
            }
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) database()->lastInsertId();
            flash('Account created. Welcome to TuneTastic!', 'success');
            redirect('?page=studio');
        }
        if ($action === 'login') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $stmt = database()->prepare('SELECT id,password_hash FROM users WHERE email=?');
            $stmt->execute([$email]);
            $account = $stmt->fetch();
            if (!$account || !password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
                throw new RuntimeException('Invalid email or password.');
            }
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $account['id'];
            flash('Signed in successfully.', 'success'); redirect('?page=discover');
        }
        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('You have signed out.'); redirect('?page=home');
        }
        if ($action === 'upload') {
            $id = require_login();
            $title = text_field('title',120);
            $genre = text_field('genre',60) ?: 'Other';
            $description = text_field('description',500);
            $price = valid_price(trim((string) ($_POST['price'] ?? '')));
            if (!$title || $price === null) throw new RuntimeException('Provide a title and valid price between 0 and 999999.99.');
            $audio = upload_media($_FILES['audio'] ?? null,'audio',true); $newFiles[]=$audio['path'];
            $cover = upload_media($_FILES['cover'] ?? null,'image',false);
            if ($cover) $newFiles[]=$cover['path'];
            $stmt = database()->prepare('INSERT INTO beats (owner_id,title,genre,description,price,audio_path,audio_mime,cover_path,cover_mime) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$id,$title,$genre,$description,$price,$audio['path'],$audio['mime'],$cover['path']??null,$cover['mime']??null]);
            flash('Beat published to the demo marketplace.', 'success'); redirect('?page=studio');
        }
        if ($action === 'update' || $action === 'delete') {
            $id = require_login();
            $beat = one_beat((int) ($_POST['beat_id'] ?? 0));
            if (!$beat || (int)$beat['owner_id'] !== $id) { http_response_code(403); exit('You cannot change this beat.'); }
            if ($action === 'delete') {
                $stmt=database()->prepare('DELETE FROM beats WHERE id=? AND owner_id=?');
                $stmt->execute([$beat['id'],$id]);
                delete_media($beat['audio_path']); delete_media($beat['cover_path']);
                flash('Beat deleted.', 'success'); redirect('?page=studio');
            }
            $title=text_field('title',120); $genre=text_field('genre',60)?:'Other';
            $description=text_field('description',500); $price=valid_price(trim((string)($_POST['price']??'')));
            if (!$title || $price === null) throw new RuntimeException('Provide a valid title and price.');
            $audio=upload_media($_FILES['audio']??null,'audio',false);
            $cover=upload_media($_FILES['cover']??null,'image',false);
            if ($audio) $newFiles[]=$audio['path']; if ($cover) $newFiles[]=$cover['path'];
            $stmt=database()->prepare('UPDATE beats SET title=?,genre=?,description=?,price=?,audio_path=?,audio_mime=?,cover_path=?,cover_mime=? WHERE id=? AND owner_id=?');
            $stmt->execute([$title,$genre,$description,$price,$audio['path']??$beat['audio_path'],$audio['mime']??$beat['audio_mime'], $cover['path']??$beat['cover_path'],$cover['mime']??$beat['cover_mime'],$beat['id'],$id]);
            if ($audio) delete_media($beat['audio_path']); if ($cover) delete_media($beat['cover_path']);
            flash('Beat updated.', 'success'); redirect('?page=studio');
        }
        if ($action === 'purchase') {
            $id=require_login(); $beat=one_beat((int)($_POST['beat_id']??0));
            if (!$beat) throw new RuntimeException('Beat not found.');
            if ((int)$beat['owner_id']===$id) throw new RuntimeException('You cannot add your own beat to your library.');
            $stmt=database()->prepare('INSERT IGNORE INTO purchases (buyer_id,beat_id) VALUES (?,?)');
            $stmt->execute([$id,$beat['id']]);
            flash('Added to your demo library. No payment was made.', 'success'); redirect('?page=library');
        }
        http_response_code(404); exit('Unknown action.');
    } catch (RuntimeException $e) {
        foreach ($newFiles as $name) delete_media($name);
        flash($e->getMessage(),'error'); redirect('?page=' . urlencode(match($action){'signup'=>'signup','login'=>'login','upload','update','delete'=>'studio','purchase'=>'discover',default=>'home'}));
    } catch (Throwable $e) {
        foreach ($newFiles as $name) delete_media($name);
        error_log('TuneTastic error: ' . $e->getMessage());
        flash('Something went wrong. Check your local server and database configuration.','error'); redirect('?page=home');
    }
}
