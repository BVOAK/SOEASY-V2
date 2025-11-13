#!/bin/bash
################################################################################
# Script de synchronisation GitHub pour Claude
# Usage: ./sync-repo-claude.sh
# 
# Ce script contourne les limitations du proxy git en utilisant l'API GitHub
################################################################################

REPO_OWNER="BVOAK"
REPO_NAME="SOEASY-V2"
BRANCH="main"
WORK_DIR="/home/claude/SOEASY-V2-main"

echo "🔄 Synchronisation du repo ${REPO_OWNER}/${REPO_NAME}..."

# 1. Récupérer le dernier commit SHA via l'API GitHub
LATEST_SHA=$(curl -s "https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/commits/${BRANCH}" | grep '"sha"' | head -1 | cut -d'"' -f4)

if [ -z "$LATEST_SHA" ]; then
    echo "❌ Erreur : impossible de récupérer le SHA du dernier commit"
    echo "   Vérifiez que le repo est public et accessible"
    exit 1
fi

echo "📌 Dernier commit sur GitHub: ${LATEST_SHA:0:7}"

# 2. Vérifier si on a déjà ce commit
if [ -f "$WORK_DIR/.last_sync_sha" ]; then
    CURRENT_SHA=$(cat "$WORK_DIR/.last_sync_sha")
    if [ "$CURRENT_SHA" == "$LATEST_SHA" ]; then
        echo "✅ Le repo est déjà à jour !"
        echo "   Commit actuel: ${CURRENT_SHA:0:7}"
        exit 0
    fi
    echo "🆕 Nouvelle version disponible"
    echo "   Local:  ${CURRENT_SHA:0:7}"
    echo "   Remote: ${LATEST_SHA:0:7}"
fi

# 3. Télécharger la nouvelle version
cd /home/claude
echo "⬇️  Téléchargement de la dernière version..."
wget -q -O soeasy-v2-new.zip "https://github.com/${REPO_OWNER}/${REPO_NAME}/archive/refs/heads/${BRANCH}.zip"

if [ $? -ne 0 ]; then
    echo "❌ Erreur lors du téléchargement"
    exit 1
fi

# 4. Backup de l'ancienne version
if [ -d "$WORK_DIR" ]; then
    BACKUP_NAME="${WORK_DIR}.backup.$(date +%Y%m%d-%H%M%S)"
    echo "💾 Backup de l'ancienne version..."
    mv "$WORK_DIR" "$BACKUP_NAME"
    echo "   Sauvegardé dans: $BACKUP_NAME"
fi

# 5. Décompresser
echo "📦 Extraction..."
unzip -q soeasy-v2-new.zip
rm soeasy-v2-new.zip

# 6. Sauvegarder le SHA du commit synchronisé
echo "$LATEST_SHA" > "$WORK_DIR/.last_sync_sha"

# 7. Vérifier que les fichiers principaux sont présents
if [ ! -f "$WORK_DIR/functions.php" ]; then
    echo "⚠️  Attention : functions.php introuvable, sync peut-être incomplet"
fi

echo ""
echo "✅ Synchronisation terminée avec succès !"
echo "📁 Projet disponible dans: $WORK_DIR"
echo "📝 Commit: ${LATEST_SHA:0:7}"
echo ""
echo "💡 Conseil: Garde les backups au cas où, tu peux les supprimer avec:"
echo "   rm -rf /home/claude/SOEASY-V2-main.backup.*"