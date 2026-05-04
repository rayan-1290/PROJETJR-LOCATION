<?php
require_once 'config.php';
$erreur = ''; $succes = '';
if(!estConnecte()) { header("Location: connexion.php"); exit; }

$stmt = $conn->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

$r = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(prix_total),0) as depense FROM reservations WHERE user_id = ?");
$r->bind_param("i", $_SESSION['user_id']); $r->execute();
$stats = $r->get_result()->fetch_assoc(); $r->close();

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $tel = trim($_POST['telephone'] ?? '');
    $nmdp = $_POST['nouveau_mdp'] ?? '';
    
    if(empty($prenom) || empty($nom)) { $erreur = 'Prénom et nom obligatoires.'; }
    else {
        if(!empty($nmdp)) {
            if(strlen($nmdp) < 6) { $erreur = 'Mot de passe min 6 caractères.'; }
            else {
                $h = password_hash($nmdp, PASSWORD_DEFAULT);
                $s = $conn->prepare("UPDATE utilisateurs SET prenom=?, nom=?, telephone=?, mdp=? WHERE id=?");
                $s->bind_param("ssssi", $prenom, $nom, $tel, $h, $_SESSION['user_id']);
            }
        } else {
            $s = $conn->prepare("UPDATE utilisateurs SET prenom=?, nom=?, telephone=? WHERE id=?");
            $s->bind_param("sssi", $prenom, $nom, $tel, $_SESSION['user_id']);
        }
        if(empty($erreur)) {
            if($s->execute()) { $_SESSION['prenom'] = $prenom; $succes = 'Profil mis à jour !'; $user['prenom']=$prenom; $user['nom']=$nom; $user['telephone']=$tel; }
            else { $erreur = 'Erreur.'; }
            $s->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil | JR LOCATION</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%); min-height: 100vh; color: #fff; }
        a { text-decoration: none; color: inherit; }

        header { position: fixed; width: 100%; top: 0; z-index: 1000; background: rgba(0, 0, 0, 0.95); padding: 20px 0; }
        .menu { display: flex; justify-content: center; align-items: center; list-style: none; padding: 0 50px; margin: 0; gap: 60px; }
        .menu li { display: inline-block; }
        .menu a { color: white; text-decoration: none; font-size: 18px; font-weight: bold; letter-spacing: 1px; transition: color 0.3s; padding: 10px 0; }
        .menu a:hover { color: #ff0000; }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; color: #ccc; margin-bottom: 8px; font-size: 14px; font-weight: bold; }
        .input-group input, .input-group select, .input-group textarea {
            width: 100%; padding: 14px 18px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; color: white; font-size: 15px; font-family: Arial, sans-serif;
            transition: all 0.3s;
        }
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus {
            outline: none; border-color: #ff0000; background: rgba(255,0,0,0.05);
        }
        .input-group input::placeholder { color: #555; }
        .erreur { background: rgba(255,0,0,0.1); border: 1px solid rgba(255,0,0,0.3); border-radius: 10px; padding: 12px; color: #ff6b6b; font-size: 13px; margin-bottom: 20px; text-align: center; }
        .succes { background: rgba(46,160,67,0.1); border: 1px solid rgba(46,160,67,0.3); border-radius: 10px; padding: 12px; color: #4ade80; font-size: 13px; margin-bottom: 20px; text-align: center; }
        .btn-action {
            width: 100%; padding: 14px; background: #ff0000; color: white;
            border: none; border-radius: 10px; font-size: 18px; font-weight: bold;
            cursor: pointer; transition: all 0.3s; font-family: Arial, sans-serif;
        }
        .btn-action:hover { background: #cc0000; transform: translateY(-2px); box-shadow: 0 5px 20px rgba(255,0,0,0.3); }

        .profil-section { min-height: 100vh; padding: 120px 5% 80px 5%; }
        .profil-container { max-width: 800px; margin: 0 auto; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        .profil-header { background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%); padding: 40px 50px; border-bottom: 1px solid rgba(255,0,0,0.3); text-align: center; }
        .profil-header h1 { color: white; font-size: 36px; font-weight: bold; margin-bottom: 5px; }
        .profil-header h1 span { color: #ff0000; }
        .profil-header p { color: #888; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .stat { padding: 25px; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); }
        .stat:last-child { border-right: none; }
        .stat .num { font-size: 28px; font-weight: bold; color: #ff0000; }
        .stat .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; }
        .profil-form { padding: 40px 50px; }
        .profil-form h2 { color: #ff0000; font-size: 22px; margin-bottom: 25px; }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .mdp-note { font-size: 12px; color: #666; margin-top: 5px; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } .row-2 { grid-template-columns: 1fr; } .profil-form { padding: 30px; } }
    </style>
</head>
<body>

    <header>
        <ul class="menu">
            <li><a href="catalogue.php">CATALOGUE VÉHICULES</a></li>
            <li><a href="contact.php">CONTACT</a></li>
            <li><a href="apropos.php">A PROPOS</a></li>
            <?php if(estConnecte()): ?>
                <li><a href="profil.php">PROFIL</a></li>
                <li><a href="historique.php">MES RÉSERVATIONS</a></li>
                <?php if(estAdmin()): ?><li><a href="admin.php" style="color:#ff0000">ADMIN</a></li><?php endif; ?>
                <li><a href="deconnexion.php">DÉCONNEXION</a></li>
            <?php else: ?>
                <li><a href="connexion.php">CONNEXION</a></li>
            <?php endif; ?>
        </ul>
    </header>


    <section class="profil-section">
        <div class="profil-container">
            <div class="profil-header">
                <h1>Mon <span>Profil</span></h1>
                <p>Membre depuis <?= date('d/m/Y', strtotime($user['date_inscription'])) ?></p>
            </div>

            <div class="stats-grid">
                <div class="stat"><div class="num"><?= $stats['total'] ?? 0 ?></div><div class="label">Réservations</div></div>
                <div class="stat"><div class="num"><?= number_format($stats['depense'] ?? 0, 0) ?>€</div><div class="label">Total dépensé</div></div>
                <div class="stat"><div class="num"><?= ucfirst($user['role']) ?></div><div class="label">Statut</div></div>
            </div>

            <div class="profil-form">
                <h2>Mes informations</h2>
                <?php if($erreur): ?><div class="erreur"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>
                <?php if($succes): ?><div class="succes"><?= htmlspecialchars($succes) ?></div><?php endif; ?>

                <form method="POST">
                    <div class="row-2">
                        <div class="input-group"><label>Prénom</label><input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required></div>
                        <div class="input-group"><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required></div>
                    </div>
                    <div class="input-group"><label>Email</label><input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:0.5"></div>
                    <div class="input-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>" placeholder="+971 XX XXX XXXX"></div>
                    <div class="input-group"><label>Nouveau mot de passe</label><input type="password" name="nouveau_mdp" placeholder="Laisser vide pour ne pas changer"><div class="mdp-note">Minimum 6 caractères</div></div>
                    <button type="submit" class="btn-action">ENREGISTRER</button>
                </form>
            </div>
        </div>
    </section>


    <footer style="background:rgba(0,0,0,0.8);padding:20px 40px;border-top:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;flex-wrap:wrap;gap:15px;font-size:13px;color:#666">
        <span>&copy; 2026 JR LOCATION - Tous droits réservés</span>
        <span>Showroom 17, 18 & 20 - Al Asayel Street, Al Quoz, Dubai</span>
    </footer>

</body>
</html>
