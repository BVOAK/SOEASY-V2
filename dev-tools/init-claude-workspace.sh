#!/bin/bash
################################################################################
# Script d'initialisation du projet SoEasy pour Claude
# Usage: ./init-claude-workspace.sh
# 
# À exécuter au début de chaque nouvelle conversation Claude sur ce projet
################################################################################

echo "🚀 Initialisation de l'environnement Claude pour SoEasy..."
echo ""

WORK_DIR="/home/claude/SOEASY-V2-main"

# 1. Vérifier si le projet existe déjà
if [ -d "$WORK_DIR" ]; then
    echo "📁 Projet déjà présent, vérification des mises à jour..."
    bash /home/claude/SOEASY-V2-main/dev-tools/sync-repo-claude.sh
else
    echo "📦 Première initialisation, téléchargement du projet..."
    
    # Télécharger le repo
    cd /home/claude
    wget -q -O soeasy-v2.zip "https://github.com/BVOAK/SOEASY-V2/archive/refs/heads/main.zip"
    
    if [ $? -ne 0 ]; then
        echo "❌ Erreur lors du téléchargement"
        exit 1
    fi
    
    # Extraire
    unzip -q soeasy-v2.zip
    rm soeasy-v2.zip
    
    # Récupérer le SHA initial
    LATEST_SHA=$(curl -s "https://api.github.com/repos/BVOAK/SOEASY-V2/commits/main" | grep '"sha"' | head -1 | cut -d'"' -f4)
    echo "$LATEST_SHA" > "$WORK_DIR/.last_sync_sha"
    
    echo "✅ Projet initialisé avec le commit: ${LATEST_SHA:0:7}"
fi

# 2. Rendre les scripts exécutables
chmod +x "$WORK_DIR/dev-tools/"*.sh

# 3. Afficher les informations utiles
echo ""
echo "✅ Environnement prêt !"
echo ""
echo "📂 Structure du projet:"
echo "   - Code source:      $WORK_DIR"
echo "   - Configurateur:    $WORK_DIR/configurateur/"
echo "   - Assets JS:        $WORK_DIR/assets/js/"
echo "   - Functions PHP:    $WORK_DIR/functions.php"
echo ""
echo "🔧 Commandes disponibles:"
echo "   - Sync repo:        bash $WORK_DIR/dev-tools/sync-repo-claude.sh"
echo "   - Réinit:           bash $WORK_DIR/dev-tools/init-claude-workspace.sh"
echo ""
echo "💡 Pour synchroniser après un push GitHub, exécute:"
echo "   bash /home/claude/SOEASY-V2-main/dev-tools/sync-repo-claude.sh"