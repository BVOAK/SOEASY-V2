# 🤖 Guide rapide - Travailler avec Claude sur SoEasy

## 🎯 Démarrage rapide

### Nouvelle conversation Claude
Au début de chaque nouveau chat sur ce projet, demande à Claude:
```
"Initialise le workspace SoEasy"
```

Ou donne-lui directement cette commande:
```bash
bash /home/claude/SOEASY-V2-main/dev-tools/init-claude-workspace.sh
```

### Après avoir pushé sur GitHub
Pour que Claude récupère tes dernières modifications:
```
"Synchronise le repo GitHub"
```

Ou:
```bash
bash /home/claude/SOEASY-V2-main/dev-tools/sync-repo-claude.sh
```

## 📚 Documentation complète

Pour plus de détails, voir: `/dev-tools/README.md`

## ⚡ Commandes rapides
```bash
# Voir le commit actuel
cat /home/claude/SOEASY-V2-main/.last_sync_sha

# Forcer une re-synchronisation
rm /home/claude/SOEASY-V2-main/.last_sync_sha
bash /home/claude/SOEASY-V2-main/dev-tools/sync-repo-claude.sh

# Nettoyer les backups
rm -rf /home/claude/SOEASY-V2-main.backup.*
```

## 🔗 Repo GitHub
https://github.com/BVOAK/SOEASY-V2