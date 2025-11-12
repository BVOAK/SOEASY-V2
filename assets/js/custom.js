jQuery(document).ready(function ($) {

	// Fonction pour initialiser les tooltips
	function initBootstrapTooltips() {
		// Détruire les anciens tooltips pour éviter les doublons
		const existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
		existingTooltips.forEach(el => {
			const tooltip = bootstrap.Tooltip.getInstance(el);
			if (tooltip) {
				tooltip.dispose();
			}
		});

		// Réinitialiser les tooltips
		const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
		const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

		console.log('✅ Tooltips Bootstrap initialisés:', tooltipList.length);
	}

	// Initialiser au chargement
	initBootstrapTooltips();

	// Exposer la fonction globalement pour l'utiliser après chargement AJAX
	window.initBootstrapTooltips = initBootstrapTooltips;

});