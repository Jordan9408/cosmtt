<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	// Vérification des entrées du formulaire
	$last_name = $_POST["last_name"];
	$first_name = $_POST["first_name"];
	$email = $_POST["mail"];
	$phone = $_POST["phone"];
	$message = $_POST["message"];

	// Vérification qu'il n'y ait pas de chiffres dans firstName et lastName
	if (preg_match('/[0-9]/', $first_name) || preg_match('/[0-9]/', $last_name)) {
		header("location:index.php#contact?message=NameContainsNumbers");
		exit();
	}

	// Vérification de l'adresse email
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		header("location:index.php#contact?message=InvalidEmail");
		exit();
	}
	// Vérification du numéro de téléphone
	if (!preg_match("/^\d{10}$/", $phone)) {
		header("location:index.php#contact?message=InvalidPhone");
		exit;
	}

	// Mettez ici l'adresse email de réception du message
	$recipient = "jordan.richard74@sfr.fr";

	// Mettez ici l'objet du message
	$subject = "Nouveau message de $last_name $first_name";

	// Corps du message
	$email_content = "Nom: $last_name\n";
	$email_content .= "Prénom: $first_name\n";
	$email_content .= "Email: $email\n";
	$email_content .= "Téléphone: $phone\n\n";
	$email_content .= "Message:\n$message\n";

	// Entêtes de l'email
	$email_headers = "From: $last_name $first_name <$email>\r\n";
	$email_headers .= "Reply-To: $email\r\n";
	$email_headers .= "MIME-Version: 1.0\r\n";
	$email_headers .= "Content-Type: text/plain; charset=utf-8\r\n";

	// Envoi de l'email
	if (mail($recipient, $subject, $email_content, $email_headers)) {
		$envoiMail = "Votre message a bien été envoyé.";
	}
}
?>

