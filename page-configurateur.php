<?php
/**
 * Template Name: Page Configurateur SoEasy
 */

get_header();

?>

<div class="configurateur-wrapper container-fluid " data-current-step="1">
	
	<!-- Colonne gauche (étapes) + colonne droite (récapitulatif) -->
	<div class="row justify-content-around">
		<div class="py-4" id="config-step-content">
			<?php get_template_part('configurateur/step', '1-adresses'); ?>
		</div>
		<?php get_template_part('configurateur/sidebar-recap'); ?>
	</div>
</div>

<script>
	// On laisse la réconciliation gérer ça
	(function() {
		const soeasyAdresses = <?php echo json_encode(soeasy_get_adresses_configurateur()); ?>;
		
		// Uniquement injecter si localStorage est vide ET qu'il y a des adresses en session
		const localAdresses = localStorage.getItem('soeasyAdresses');
		
		if (!localAdresses && soeasyAdresses && soeasyAdresses.length > 0) {
			console.log('📋 Injection adresses PHP dans localStorage (localStorage vide)');
			localStorage.setItem('soeasyAdresses', JSON.stringify(soeasyAdresses));
		} else if (localAdresses) {
			console.log('⏭️ localStorage contient déjà des adresses, skip injection PHP');
		}
	})();
</script>

<?php get_footer(); ?>