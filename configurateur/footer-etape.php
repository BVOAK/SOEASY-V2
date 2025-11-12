<div class="footer-etape">
  <?php
    // Récupère le numéro de l'étape actuelle (ex : step-2 => 2)
    preg_match('/step-(\d)/', basename(__FILE__), $matches);
    $etapeActuelle = isset($matches[1]) ? intval($matches[1]) : null;

    // Fallback si include() dans un autre fichier
    if (!isset($etapeActuelle)) {
      if (isset($_GET['etape'])) {
        $etapeActuelle = intval($_GET['etape']);
      }
    }

    // Étape précédente
    if ($etapeActuelle > 1) {
      echo '<button class="btn-prev">Revenir à l’étape précédente</button>';
    }

    // Étape suivante
    if ($etapeActuelle < 5) {
      echo '<button class="btn-suivant" data-step="' . ($etapeActuelle + 1) . '">Passer à l’étape suivante</button>';
    } else {
      echo '<button class="btn-commander">Valider ma commande</button>';
    }
  ?>
</div>
