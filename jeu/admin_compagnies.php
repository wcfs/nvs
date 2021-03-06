<?php
session_start();
require_once("../fonctions.php");

$mysqli = db_connexion();

include ('../nb_online.php');
include ('../forum/config.php');

if(isset($_SESSION["id_perso"])){
	
	$id_perso = $_SESSION['id_perso'];
	
	// recupération config jeu
	$admin = admin_perso($mysqli, $id_perso);
	
	if($admin){
		
		$mess_err 	= "";
		$mess 		= "";
		
		if(isset($_POST['select_compagnie']) && $_POST['select_compagnie'] != '') {
			
			$id_compagnie_select = $_POST['select_compagnie'];
			
		}
		
		/***********************************************************/
		/* Modification de la thune dans la banque de la compagnie */
		/***********************************************************/
		if (isset($_POST['thune_compagnie']) && $_POST['thune_compagnie'] != "") {
			
			$id_compagnie_select = $_POST['hid_id_compagnie'];
			
			$thune_compagnie = $_POST['thune_compagnie'];
			
			$verif = preg_match("#^[0-9]*[0-9]$#i","$thune_compagnie");
			
			if ($verif) {
			
				$sql = "UPDATE banque_as_compagnie SET montant='$thune_compagnie' WHERE id_compagnie='$id_compagnie_select'";
				$mysqli->query($sql);
				
				$mess = "La thune de la banque de la compagnie est passée à ".$thune_compagnie;
			
			}
			else {
				$mess_err = "valeur thune incorrecte";
			}
		}
		
		/****************************************/
		/*		Suppression d'une compagnie		*/
		/****************************************/
		if (isset($_POST['delete_compagnie'])) {
			
			$id_compagnie_to_delete = $_POST['hid_id_compagnie_to_delete'];
			
			// recuperation des information sur la compagnie
			$sql = "SELECT nom_compagnie FROM compagnies WHERE id_compagnie=$id_compagnie_to_delete";
			$res = $mysqli->query($sql);
			$sec = $res->fetch_assoc();
			
			$nom_compagnie		= addslashes($sec["nom_compagnie"]);
			
			// Récupération de l'id du group de la compagnie sur le forum
			$sql = "SELECT group_id FROM ".$table_prefix."groups WHERE group_name='$nom_compagnie'";
			$res = $mysqli->query($sql);
			$t = $res->fetch_assoc();
			
			$id_group_forum = $t['group_id'];
			
			// récupération des persos dans la compagnie 
			$sql = "SELECT id_perso FROM perso_in_compagnie WHERE id_compagnie='$id_compagnie_to_delete'";
			$res_perso_a_virer = $mysqli->query($sql);
			
			while ($t = $res_perso_a_virer->fetch_assoc()) {
				
				$id_perso_a_virer = $t['id_perso'];
				
				// on vire le perso de la compagnie
				$sql = "DELETE FROM perso_in_compagnie WHERE id_perso='$id_perso_a_virer'";
				$mysqli->query($sql);
				
				// on enleve le perso de la banque
				$sql = "DELETE FROM banque_compagnie WHERE id_perso='$id_perso_a_virer'";
				$mysqli->query($sql);
				
				// -- FORUM
				// Récupération de l'id de l'utilisateur sur le forum 
				$sql = "SELECT user_id FROM ".$table_prefix."users WHERE username IN 
							(SELECT nom_perso FROM perso WHERE idJoueur_perso IN 
								(SELECT idJoueur_perso FROM perso WHERE id_perso='$id_perso_a_virer') AND chef='1')";
				$res_forum = $mysqli->query($sql);
				$t = $res_forum->fetch_assoc();
				
				$id_user_forum = $t['user_id'];
				
				// Suppression de l'utilisateur du groupe sur le forum
				$sql = "DELETE FROM ".$table_prefix."user_group WHERE group_id='$id_group_forum' AND user_id='$id_user_forum'";
				$mysqli->query($sql);
				
			}
			
			// Suppression du groupe sur le forum 
			$sql = "DELETE FROM ".$table_prefix."groups WHERE group_name='$nom_compagnie'";
			$mysqli->query($sql);
			
			// Suppression de la compagnie sur le jeu 
			$sql = "DELETE FROM compagnies WHERE id_compagnie='$id_compagnie_to_delete'";
			$mysqli->query($sql);
			
			// Suppression de la banque de la compagnie
			$sql = "DELETE FROM banque_as_compagnie WHERE id_compagnie='$id_compagnie_to_delete'";
			$mysqli->query($sql);
			
			// Suppression l'historique de la banque de la compagnie
			$sql = "DELETE FROM histobanque_compagnie WHERE id_compagnie='$id_compagnie_to_delete'";
			$mysqli->query($sql);
			
			// Suppression de toutes le demandes liées à cette compagnie
			$sql = "DELETE FROM compagnie_demande_anim WHERE id_compagnie='$id_compagnie_to_delete'";
			$mysqli->query($sql);
			
			$mess = "la compagnie d'id ".$id_compagnie_to_delete." a bien été supprimée";
		}
		
		/****************************************/
		/* 	On vire un perso d'une compagnie 	*/
		/****************************************/
		if (isset($_POST['hid_id_perso_virer']) || isset($_POST['hid_id_perso_valider_depart'])) {
			
			$id_compagnie_select 	= $_POST['hid_id_compagnie'];
			if (isset($_POST['hid_id_perso_virer'])) {
				$id_perso_a_virer 		= $_POST['hid_id_perso_virer'];
			}
			else {
				$id_perso_a_virer 		= $_POST['hid_id_perso_valider_depart'];
			}
			
			// recuperation des information sur la compagnie
			$sql = "SELECT genie_civil, nom_compagnie FROM compagnies WHERE id_compagnie=$id_compagnie_select";
			$res = $mysqli->query($sql);
			$sec = $res->fetch_assoc();
			
			$genie_compagnie 	= $sec["genie_civil"];
			$nom_compagnie		= addslashes($sec["nom_compagnie"]);
			
			$sql = "SELECT SUM(montant) as thune_en_banque FROM histobanque_compagnie 
					WHERE id_perso='$id_perso_a_virer' 
					AND id_compagnie='$id_compagnie_select'";
			$res = $mysqli->query($sql);
			$tab = $res->fetch_assoc();
			
			$thune_en_banque = $tab["thune_en_banque"];
			
			$sql = "DELETE FROM histobanque_compagnie WHERE id_perso='$id_perso_a_virer'";
			$mysqli->query($sql);
			
			if ($thune_en_banque > 0) {
				$sql = "UPDATE banque_as_compagnie SET montant = montant - $thune_en_banque 
						WHERE id_compagnie='$id_compagnie_select')";
				$mysqli->query($sql);
			}
		
			// on vire le perso de la compagnie
			$sql = "DELETE FROM perso_in_compagnie WHERE id_perso=$id_perso_a_virer AND id_compagnie=$id_compagnie_select";
			$mysqli->query($sql);
			
			// on enleve le perso de la banque
			$sql = "DELETE FROM banque_compagnie WHERE id_perso=$id_perso_a_virer";
			$mysqli->query($sql);
			
			if ($genie_compagnie) {
				// On suprime les competences de construction
				
				// Construire pont
				$sql = "DELETE FROM perso_as_competence WHERE id_perso='$id_perso_a_virer' AND id_competence='23'";
				$mysqli->query($sql);
				
				// Construire tour de visu
				$sql = "DELETE FROM perso_as_competence WHERE id_perso='$id_perso_a_virer' AND id_competence='24'";
				$mysqli->query($sql);
				
				// Construire Hopital
				$sql = "DELETE FROM perso_as_competence WHERE id_perso='$id_perso_a_virer' AND id_competence='27'";
				$mysqli->query($sql);
				
				// Construire Fortin
				$sql = "DELETE FROM perso_as_competence WHERE id_perso='$id_perso_a_virer' AND id_competence='28'";
				$mysqli->query($sql);
				
				// Construire Gare
				$sql = "DELETE FROM perso_as_competence WHERE id_perso='$id_perso_a_virer' AND id_competence='63'";
				$mysqli->query($sql);
				
				// Construire Rails
				$sql = "DELETE FROM perso_as_competence WHERE id_perso='$id_perso_a_virer' AND id_competence='64'";
				$mysqli->query($sql);
			}
			
			$mess = "le perso d'id ".$id_perso_a_virer." a bien été viré de la compagnie ".$nom_compagnie;
		}
		
		/************************************************************/
		/* 	On valide l'intégration d'un perso dans une compagnie 	*/
		/************************************************************/
		if (isset($_POST['hid_id_perso_valider'])) {
			
			$id_compagnie_select 	= $_POST['hid_id_compagnie'];
			$id_perso_a_valider 	= $_POST['hid_id_perso_valider'];
			
			// recuperation des information sur la compagnie
			$sql = "SELECT genie_civil, nom_compagnie FROM compagnies WHERE id_compagnie=$id_compagnie_select";
			$res = $mysqli->query($sql);
			$sec = $res->fetch_assoc();
			
			$genie_compagnie 	= $sec["genie_civil"];
			$nom_compagnie		= addslashes($sec["nom_compagnie"]);
			
			// récupération des informations du perso 
			$sql = "SELECT nom_perso FROM perso WHERE id_perso='$id_perso_a_valider'";
			$res = $mysqli->query($sql);
			$t = $res->fetch_assoc();
			
			$nom_recrue = $t['nom_perso'];
			
			// on met a jour le champ attenteValidation de la table perso_in_compagnie
			$sql = "UPDATE perso_in_compagnie SET attenteValidation_compagnie='0' WHERE id_perso=$id_perso_a_valider";
			$mysqli->query($sql);
			
			// insertion dans la table banque compagnie
			$sql = "INSERT INTO banque_compagnie VALUES ($id_perso_a_valider,'0','0','0')";
			$mysqli->query($sql);
			
			if ($genie_compagnie) {
				// Nouvelles compétences de construction pour le perso
				
				// Construire pont
				$sql = "INSERT INTO perso_as_competence (id_perso, id_competence, nb_points) VALUES ('$id_perso_a_valider', '23', '1')";
				$mysqli->query($sql);
				
				// Construire tour de visu
				$sql = "INSERT INTO perso_as_competence (id_perso, id_competence, nb_points) VALUES ('$id_perso_a_valider', '24', '1')";
				$mysqli->query($sql);
				
				// Construire Hopital
				$sql = "INSERT INTO perso_as_competence (id_perso, id_competence, nb_points) VALUES ('$id_perso_a_valider', '27', '1')";
				$mysqli->query($sql);
				
				// Construire Fortin
				$sql = "INSERT INTO perso_as_competence (id_perso, id_competence, nb_points) VALUES ('$id_perso_a_valider', '28', '1')";
				$mysqli->query($sql);
				
				// Construire Gare
				$sql = "INSERT INTO perso_as_competence (id_perso, id_competence, nb_points) VALUES ('$id_perso_a_valider', '63', '1')";
				$mysqli->query($sql);
				
				// Construire Rails
				$sql = "INSERT INTO perso_as_competence (id_perso, id_competence, nb_points) VALUES ('$id_perso_a_valider', '64', '1')";
				$mysqli->query($sql);
				
				// Compteur Génie sur le perso
				$sql = "UPDATE perso SET genie='8' WHERE id_perso='$id_perso_a_valider'";
				$mysqli->query($sql);
			}
			
			// on lui envoi un mp
			$message = "Bonjour $nom_recrue,
						J\'ai le plaisir de t\'annoncer que ton entrée dans la compagnie ". $nom_compagnie ." a été acceptée.";
			$objet = "Incorporation dans la compagnie";
			
			$lock = "LOCK TABLE (joueur) WRITE";
			$mysqli->query($lock);
			
			$sql = "INSERT INTO message (expediteur_message, date_message, contenu_message, objet_message) VALUES ( '" . $nom_compagnie . "', NOW(), '" . $message . "', '" . $objet . "')";
			$res = $mysqli->query($sql);
			$id_message = $mysqli->insert_id;
			
			$unlock = "UNLOCK TABLES";
			$mysqli->query($unlock);
			
			$sql = "INSERT INTO message_perso VALUES ('$id_message','$id_perso_a_valider','1','0','1','0')";
			$res = $mysqli->query($sql);
			
			$mess = "le perso ".$nom_recrue." [".$id_perso_a_valider."] a bien été intégré à la compagnie ".$nom_compagnie;
			
		}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title>Nord VS Sud</title>
		
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		
		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	</head>
	<body>
		<div class="container-fluid">
			<div class="row">
				<div class="col-12">

					<div align="center">
						<h2>Administration</h2>
					</div>
				</div>
			</div>
		
			<p align="center"><a class="btn btn-primary" href="admin_nvs.php">Retour à l'administration</a> <a class="btn btn-primary" href="jouer.php">Retour au jeu</a></p>
		
			<div class="row">
				<div class="col-12">
				
					<h3>Administration des compagnies</h3>
					
					<center><font color='red'><?php echo $mess_err; ?></font></center>
					<center><font color='blue'><?php echo $mess; ?></font></center>
					
					<form method='POST' action='admin_compagnies.php'>
					
						<select name="select_compagnie" onchange="this.form.submit()">
						
							<?php
							$sql = "SELECT id_compagnie, nom_compagnie, id_clan FROM compagnies ORDER BY id_compagnie ASC";
							$res = $mysqli->query($sql);
							
							while ($t = $res->fetch_assoc()) {
								
								$id_compagnie 	= $t["id_compagnie"];
								$nom_compagnie 	= $t["nom_compagnie"];
								$id_clan		= $t["id_clan"];
								
								echo "<option value='".$id_compagnie."'";
								if (isset($id_compagnie_select) && $id_compagnie_select == $id_compagnie) {
									echo " selected";
								}
								echo ">".$nom_compagnie." [".$id_compagnie."] - ".$id_clan."</option>";
							}
							?>
						
						</select>
						
						<input type="submit" value="choisir">
						
					</form>
				</div>
			</div>
			
			<div class="row">
				<div class="col-12">
					<div align="center">					
						<div id="table_batiments" class="table-responsive">	
					
						<?php 
						if (isset($id_compagnie_select)) {
							
							$sql = "SELECT nom_compagnie, id_clan, montant
									FROM compagnies, banque_as_compagnie
									WHERE compagnies.id_compagnie = banque_as_compagnie.id_compagnie
									AND compagnies.id_compagnie='$id_compagnie_select'";
							$res = $mysqli->query($sql);
							$t = $res->fetch_assoc();
							
							$nom_compagnie 	= $t['nom_compagnie'];
							$id_clan		= $t["id_clan"];
							$montant_banque	= $t["montant"];
							
							echo "<form method='POST' action='admin_compagnies.php'>";
							echo "	<h3>".$nom_compagnie." <input type='submit' name='delete_compagnie' class='btn btn-danger' value='Supprimer cette compagnie'></h3>";
							echo "	<input type='hidden' name='hid_id_compagnie_to_delete' value='$id_compagnie_select'>";
							echo "</form>";
							echo "<form method='POST' action='admin_compagnies.php'>";
							echo "	<input type='text' style='text-align:center;' name='thune_compagnie' value='".$montant_banque."'> thunes ";
							echo "	<input type='hidden' name='hid_id_compagnie' value='$id_compagnie_select'>";
							echo "	<input type='submit' class='btn btn-warning' value='modifier'>";
							echo "</form>";

							$sql = "SELECT nom_perso, perso_in_compagnie.id_perso, attenteValidation_compagnie, nom_poste 
									FROM perso_in_compagnie, perso, poste
									WHERE perso_in_compagnie.id_perso = perso.id_perso
									AND perso_in_compagnie.poste_compagnie = poste.id_poste
									AND perso_in_compagnie.id_compagnie='$id_compagnie_select'";
							$res = $mysqli->query($sql);
							
							echo "<br /><br /><h4>Liste des persos de la compagnie</h4>";
							
							echo "<table class='table'>";
							echo "	<thead>";
							echo "		<tr>";
							echo "			<th>Perso</th><th>Poste</th><th>Action</th>";
							echo "		</tr>";
							echo "	</thead>";
							echo "	<tbody>";
							
							while ($t = $res->fetch_assoc()) {
								
								$id_perso						= $t['id_perso'];
								$nom_perso						= $t['nom_perso'];
								$poste_perso_compagnie			= $t['nom_poste'];
								$attenteValidation_compagnie	= $t['attenteValidation_compagnie'];
								
								echo "		<tr>";
								echo "			<td>".$nom_perso." [<a href='evenement.php?infoid=".$id_perso."'>".$id_perso."</a>]</td>";
								echo "<form method='POST' action='admin_compagnies.php'>";
								echo "			<td>".$poste_perso_compagnie;
								echo "	<input type='submit' class='btn btn-warning' value='changer de poste'>";
								echo "	<input type='hidden' name='hid_id_compagnie' value='$id_compagnie_select'>";
								echo "			</td>";
								echo "</form>";
								echo "			<td>";
								if ($attenteValidation_compagnie == 1) {
									echo "<form method='POST' action='admin_compagnies.php'>";
									echo "	<input type='submit' class='btn btn-warning' value='Valider l'intégration de ce perso'>";
									echo "	<input type='hidden' name='hid_id_compagnie' value='$id_compagnie_select'>";
									echo "	<input type='hidden' name='hid_id_perso_valider' value='$id_perso'>";
									echo "</form>";
								}
								else if ($attenteValidation_compagnie == 2) {
									echo "<form method='POST' action='admin_compagnies.php'>";
									echo "	<input type='submit' class='btn btn-warning' value='Valider le départ ce perso'>";
									echo "	<input type='hidden' name='hid_id_compagnie' value='$id_compagnie_select'>";
									echo "	<input type='hidden' name='hid_id_perso_valider_depart' value='$id_perso'>";
									echo "</form>";
								}
								echo "<form method='POST' action='admin_compagnies.php'>";
								echo "	<input type='submit' class='btn btn-danger' value='Virer ce perso'>";
								echo "	<input type='hidden' name='hid_id_compagnie' value='$id_compagnie_select'>";
								echo "	<input type='hidden' name='hid_id_perso_virer' value='$id_perso'>";
								echo "</form>";
								echo "			</td>";
								echo "</form>";
								echo "		</tr>";
							}
							
							echo "	</tbody>";
						}
						?>
						
						</div>
					</div>
				</div>
			</div>
		
		</div>
		
		<!-- Optional JavaScript -->
		<!-- jQuery first, then Popper.js, then Bootstrap JS -->
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	</body>
</html>
<?php
	}
}
else{
	echo "<font color=red>Vous ne pouvez pas acceder a cette page, veuillez vous logguer.</font>";
}?>
