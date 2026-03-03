<?php include('./html_partials/header.php') ?>
<?php include('./admin/pages/envoi_mail.php') ?>
<?php require_once('./config/config.php') ?>

<main id="main_index">
	<div id="gymnase">
		<h1>COS MARCILLY-EN-VILLETTE<br>TENNIS DE TABLE</h1>
	</div>
	<div id="container">
		<h2>DERNIERS ARTICLES</h2>
		<hr>
		<section id="criterium1">
			<article>
				<a href="criterium.php#reg_A"><img src="img/photos_regional_a/IMG_1907.JPG" alt="criterium_08/01/2023" class="img_criterium"></a>
				<aside>
					<a href="criterium.php#reg_A">
						<h3 class="title_article">Critérium Ufolep Régionnal A – 8 janvier 2023</h3>
					</a>
					<p>Pour la première compétition de l'année 2023 avec le critérium Ufolep Régional A qui ont eu
						lieu ce dimanche 8 janvier 2023...</p>
				</aside>
			</article>
		</section>
		<hr>
		<section id="criterium2">
			<article>
				<a href="criterium.php#dep_A"><img src="img/criterium_04_12.jpg" alt="criterium_04/12/2022" class="img_criterium"></a>
				<aside>
					<a href="criterium.php#dep_A">
						<h3 class="title_article">Critérium Ufolep Départemental A – 4 décembre 2022</h3>
					</a>
					<p>Le critérium Ufolep Départemental A qui ont eu lieu ce dimanche 4 décembre 2022...</p>
				</aside>
			</article>
		</section>
		<hr>
		<section>
			<div class="button">
				<a href="./criterium.php" id="abtn"><button class="btn">VISITEZ NOTRE BLOG</button></a>
			</div>
		</section>
	</div>
	<h2 id="title-contact">Contact</h2>

	<div id="contact">

		<?php
		if (isset($envoiMail)) {
			echo "<p class='envoiMail'>$envoiMail</p>";
		}
		?>
		<section class="align-horiz">
			<form action="" method="POST" enctype="multipart/form-data">
				<div>
					<label for="last_name">Nom :</label><br>
					<input type="text" id="last_name" name="last_name" placeholder="Martin" autocomplete="family-name" required>
				</div>
				<div>
					<label for="first_name">Prénom :</label><br>
					<input type="text" id="first_name" name="first_name" placeholder="Pierre" autocomplete="given-name" required>
				</div>
				<div>
					<label for="mail">Mail:</label><br>
					<input type="email" id="mail" name="mail" placeholder="cosmarcillytennisdetable@laposte.net" autocomplete="email" required>
				</div>
				<div>
					<label for="phone">Téléphone:</label><br>
					<input type="tel" id="phone" name="phone" placeholder="Numero" autocomplete="tel" required>
				</div>
				<div>
					<label for="message">Message :</label><br>
					<textarea id="message" name="message" placeholder="Message" required></textarea>
				</div>
				<input type="submit" id="submit" value="Envoyer">
			</form>
		</section>
	</div>
</main>
<?php include('./html_partials/footer.php') ?>
